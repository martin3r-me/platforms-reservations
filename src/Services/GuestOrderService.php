<?php

namespace Platform\Reservation\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Platform\Reservation\Exceptions\GuestOrderException;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Models\Order;
use Platform\Reservation\Models\Table;

/**
 * Autoritative Erstellung einer Gast-Bestellung (Order + N Slot-Buchungen) aus
 * einem API-Payload. Preise/Steuer kommen aus der DB (nie aus dem Request),
 * Artikel werden auf die Verkaufsliste des Events beschränkt, Mengen begrenzt,
 * Plätze je Pause geprüft. Genutzt von der Gast-API; teilt die Kalkulation
 * (CartCalculator) mit dem In-App-Wizard.
 */
class GuestOrderService
{
    public function __construct(
        protected CartCalculator $calc,
        protected SeatAvailabilityService $seats,
        protected MolliePaymentService $payments,
    ) {
    }

    /**
     * @param array{name:string,email:?string,phone:?string,count:int,notes:?string} $guest
     * @param array<int, array{slot_id:int, table_id:int, items:array<int,int>}> $slotOrders
     * @return array{order: Order, checkout_url: ?string}
     *
     * @throws GuestOrderException
     */
    public function place(Event $event, array $guest, array $slotOrders, bool $ageConfirmed): array
    {
        if (!$event->isOrderable()) {
            throw new GuestOrderException('Der Bestellschluss für diesen Termin ist erreicht.', 'ORDER_CLOSED');
        }

        if (empty($slotOrders)) {
            throw new GuestOrderException('Es wurde keine Pause mit Produkten übermittelt.', 'EMPTY_ORDER');
        }

        $allowedFloorPlanIds = $event->eventRooms->pluck('floor_plan_id')->all();

        // Weiche Tisch-Kapazität (Großgruppen auf leere Tische) je Team-Setting.
        $checkout     = \Platform\Reservation\Models\CheckoutSetting::forTeam((int) $event->team_id);
        $softCapacity = $checkout->softTableCapacity();
        $maxGroup     = $checkout->maxGroupEmptyTable();

        // Vorbereiten & validieren je Pause (ohne zu schreiben).
        $prepared = [];  // [ ['slot'=>EventSlot, 'table'=>Table, 'lines'=>Collection], ... ]
        $allLines = collect();

        foreach ($slotOrders as $slotOrder) {
            $slot = $event->slots->firstWhere('id', (int) ($slotOrder['slot_id'] ?? 0));
            if (!$slot) {
                throw new GuestOrderException('Unbekannte Pause im Auftrag.', 'SLOT_NOT_FOUND');
            }

            $lines = $this->calc->lines((array) ($slotOrder['items'] ?? []), $event);
            if ($lines->isEmpty()) {
                throw new GuestOrderException('Für eine Pause wurden keine gültigen Produkte übermittelt.', 'INVALID_ITEMS');
            }

            $table = Table::withoutGlobalScope('team')->find((int) ($slotOrder['table_id'] ?? 0));
            if (!$table || !in_array($table->floor_plan_id, $allowedFloorPlanIds, true)) {
                throw new GuestOrderException('Der gewählte Tisch gehört nicht zu diesem Termin.', 'TABLE_NOT_IN_EVENT');
            }

            if (! $this->seats->canSeat($table, $slot, (int) $guest['count'], $softCapacity, $maxGroup)) {
                throw new GuestOrderException('Ein gewählter Tisch hat nicht genügend freie Plätze.', 'TABLE_FULL');
            }

            $prepared[] = ['slot' => $slot, 'table' => $table, 'lines' => $lines];
            $allLines    = $allLines->merge($lines);
        }

        if ($this->calc->containsAgeRestricted($allLines) && !$ageConfirmed) {
            throw new GuestOrderException('Die Bestellung enthält alkoholische Getränke – Altersbestätigung erforderlich.', 'AGE_REQUIRED');
        }

        $order = DB::transaction(function () use ($event, $guest, $prepared, $allLines) {
            $order = Order::create([
                'team_id'  => $event->team_id,
                'event_id' => $event->id,
                'status'   => Order::STATUS_PENDING,
            ]);

            $ageAt = $this->calc->containsAgeRestricted($allLines) ? now() : null;

            foreach ($prepared as $p) {
                $booking = Booking::create([
                    'order_id'               => $order->id,
                    'team_id'                => $event->team_id,
                    'event_id'               => $event->id,
                    'event_slot_id'          => $p['slot']->id,
                    'table_id'               => $p['table']->id,
                    'guest_name'             => $guest['name'],
                    'guest_email'            => $guest['email'] ?: null,
                    'guest_phone'            => $guest['phone'] ?: null,
                    'guest_count'            => (int) $guest['count'],
                    'notes'                  => $guest['notes'] ?: null,
                    'date'                   => $event->date->toDateString(),
                    'time_start'             => $p['slot']->time_start,
                    'time_end'               => $p['slot']->time_end,
                    'status'                 => Booking::STATUS_PENDING,
                    'payment_method'         => null,
                    'age_check_confirmed_at' => $ageAt,
                    'legal_accepted_at'      => now(),
                ]);

                foreach ($this->calc->frozenItemAttributes($p['lines']) as $attributes) {
                    $booking->items()->create($attributes);
                }
            }

            return $order;
        });

        // Scope-sicher nachladen (API-Kontext ist authentifiziert → Global Scope aktiv).
        $order->load(['bookings' => fn ($q) => $q->withoutGlobalScope('team')->with('items')]);

        $checkoutUrl = null;
        if ($order->total_amount > 0 && $this->payments->isEnabledForTeam($event->team_id)) {
            $checkoutUrl = $this->payments->createForOrder($order);
        }

        return ['order' => $order, 'checkout_url' => $checkoutUrl];
    }
}
