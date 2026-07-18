<?php

namespace Platform\Reservation\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Platform\Reservation\Exceptions\GuestOrderException;
use Platform\Reservation\Models\CheckoutSetting;
use Platform\Reservation\Models\Order;
use Platform\Reservation\Services\GuestOrderService;

/**
 * Schreib-/Status-Endpunkte der Gast-API: Bestellung anlegen (Order + N
 * Slot-Buchungen, serverseitig validiert und eingefroren) und Status abfragen.
 */
class GuestBookingController extends GuestApiController
{
    /** POST /guest/bookings – Bestellung anlegen. */
    public function store(Request $request, GuestOrderService $service): JsonResponse
    {
        // #520/#521: Pflicht/optional der Kontaktfelder kommt aus den Team-Settings.
        $settings = CheckoutSetting::forTeam($this->guestTeamId());

        $data = $request->validate([
            'event_uuid'       => 'required|string',
            'guest'            => 'required|array',
            'guest.name'       => ['required', 'string', 'max:255'],
            'guest.email'      => $settings->guestFieldRule('email', ['email', 'max:255']),
            'guest.phone'      => $settings->guestFieldRule('phone', ['string', 'max:30']),
            'guest.count'      => ['required', 'integer', 'min:1', 'max:20'],
            'guest.notes'      => $settings->guestFieldRule('notes', ['string']),
            'legal_accepted'   => 'accepted',
            'age_confirmed'    => 'nullable|boolean',
            'slots'            => 'required|array|min:1',
            'slots.*.slot_id'  => 'required|integer',
            'slots.*.table_id' => 'required|integer',
            'slots.*.items'    => 'required|array|min:1',
        ]);

        $event = $this->findEvent($data['event_uuid']);

        if (!$event) {
            return response()->json(['message' => 'Termin nicht gefunden.'], 404);
        }

        $event->loadMissing(['slots', 'eventRooms']);

        try {
            $result = $service->place(
                $event,
                [
                    'name'  => $data['guest']['name'],
                    'email' => $data['guest']['email'] ?? null,
                    'phone' => $data['guest']['phone'] ?? null,
                    'count' => (int) $data['guest']['count'],
                    'notes' => $data['guest']['notes'] ?? null,
                ],
                $data['slots'],
                (bool) ($data['age_confirmed'] ?? false),
            );
        } catch (GuestOrderException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code'    => $e->errorCode,
            ], 422);
        }

        return response()->json([
            'order_uuid'   => $result['order']->uuid,
            'total_amount' => round((float) $result['order']->total_amount, 2),
            'status'       => $result['order']->status,
            'checkout_url' => $result['checkout_url'], // null = keine Online-Zahlung nötig
        ], 201);
    }

    /** GET /guest/bookings/{uuid} – Status einer Bestellung. */
    public function show(string $uuid): JsonResponse
    {
        $order = Order::withoutGlobalScope('team')
            ->where('team_id', $this->guestTeamId())
            ->where('uuid', $uuid)
            ->with(['payment', 'bookings' => fn ($q) => $q->withoutGlobalScope('team')->with(['slot', 'items'])])
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Bestellung nicht gefunden.'], 404);
        }

        return response()->json([
            'order_uuid'     => $order->uuid,
            'status'         => $order->status,
            'total_amount'   => round((float) $order->total_amount, 2),
            'payment_status' => $order->payment?->status,
            'bookings'       => $order->bookings->map(fn ($b) => [
                'uuid'   => $b->uuid,
                'slot'   => $b->slot?->name,
                'status' => $b->status,
            ])->values(),
        ]);
    }
}
