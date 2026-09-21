<?php

namespace Platform\Reservation\Tools;

use Illuminate\Support\Facades\Validator;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Exceptions\GuestOrderException;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Services\GuestOrderService;

/**
 * Legt eine Buchung an - den Weg, den auch das Backoffice nimmt.
 *
 * Geschrieben wird ausschliesslich ueber GuestOrderService::placeForStaff():
 * dieselbe Platzpruefung, dieselbe Raum- und Stationslogik, dieselben
 * eingefrorenen Preise wie beim Gast-Checkout. Eine zweite Fassung dieser
 * Regeln waere die Art Fehler, die man erst bemerkt, wenn die Zahlen
 * auseinanderlaufen - deshalb steht hier nur die Uebersetzung der Parameter.
 *
 * Bewusst EINE Pause je Aufruf, wie in der Oberflaeche. Eine Bestellung ueber
 * mehrere Pausen ist eine Klammer mit einer Zahlung; die entsteht im
 * Gast-Checkout, nicht am Telefon.
 *
 * Wie am Telefon gilt die Bestellung sofort als bestaetigt (Zahlungsart
 * "onsite") - kassiert wird vor Ort, nicht ueber Mollie.
 */
class BookingCreateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.bookings.POST';
    }

    public function getDescription(): string
    {
        return 'POST /reservation/bookings - Legt eine Buchung an (Backoffice-/Telefonweg). REST-Parameter: '
            . 'event_uuid (Pflicht), slot_id (Pflicht, Pause des Termins), items (Pflicht, Objekt '
            . '{menu_item_id: menge}), guest_count (Pflicht), first_name + last_name (Pflicht), '
            . 'ORT: entweder table_id ODER station_id (genau eines), email, phone, notes (optional). '
            . 'Gebucht wird ueber denselben Dienst wie die Oberflaeche: Platzpruefung, Raumfreigabe und '
            . 'Stationskapazitaet gelten, Preise und MwSt werden eingefroren. Die Buchung ist sofort '
            . 'bestaetigt, Zahlungsart "onsite" (kassiert wird vor Ort). Fachliche Ablehnungen kommen '
            . 'mit sprechendem Code zurueck (z.B. TABLE_NOT_IN_EVENT, TABLE_FULL, INVALID_ITEMS).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'event_uuid'  => ['type' => 'string'],
                'slot_id'     => ['type' => 'integer', 'description' => 'Pause des Termins (reservation.event-slots.GET).'],
                'table_id'    => ['type' => 'integer', 'description' => 'Tisch als Zielort - entweder table_id oder station_id.'],
                'station_id'  => ['type' => 'integer', 'description' => 'Abholstation als Zielort - entweder table_id oder station_id.'],
                'items'       => ['type' => 'object', 'description' => 'Artikel als {menu_item_id: menge}, z.B. {"40": 2, "35": 1}.'],
                'guest_count' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Anzahl Personen.'],
                'first_name'  => ['type' => 'string'],
                'last_name'   => ['type' => 'string'],
                'email'       => ['type' => 'string'],
                'phone'       => ['type' => 'string'],
                'notes'       => ['type' => 'string', 'description' => 'Anmerkung des Gastes (steht auf Bon und Laufzettel).'],
            ],
            'required'   => ['event_uuid', 'slot_id', 'items', 'guest_count', 'first_name', 'last_name'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $context->team?->id;

            if (!$teamId) {
                return ToolResult::error('Kein Team-Kontext vorhanden.', 'MISSING_TEAM');
            }

            $validator = Validator::make($arguments, [
                'event_uuid'  => 'required|string',
                'slot_id'     => 'required|integer',
                'table_id'    => 'nullable|integer',
                'station_id'  => 'nullable|integer',
                'items'       => 'required|array|min:1',
                'guest_count' => 'required|integer|min:1|max:100',
                'first_name'  => 'required|string|max:255',
                'last_name'   => 'required|string|max:255',
                'email'       => 'nullable|string|max:255',
                'phone'       => 'nullable|string|max:255',
                'notes'       => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return ToolResult::error($validator->errors()->first(), 'VALIDATION_ERROR');
            }

            $event = Event::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->where('uuid', (string) $arguments['event_uuid'])
                ->first();

            if (!$event) {
                return ToolResult::error('Termin nicht gefunden.', 'NOT_FOUND');
            }

            // Mengen als int, Schluessel als int: aus JSON kommen beide als
            // Zeichenkette, und der Warenkorb rechnet mit Zahlen.
            $items = [];
            foreach ((array) $arguments['items'] as $id => $menge) {
                $items[(int) $id] = (int) $menge;
            }

            $slotOrder = array_filter([
                'slot_id'    => (int) $arguments['slot_id'],
                'table_id'   => isset($arguments['table_id']) ? (int) $arguments['table_id'] : null,
                'station_id' => isset($arguments['station_id']) ? (int) $arguments['station_id'] : null,
                'items'      => $items,
            ], fn ($wert) => $wert !== null);

            $order = app(GuestOrderService::class)->placeForStaff(
                $event,
                [
                    'first_name' => (string) $arguments['first_name'],
                    'last_name'  => (string) $arguments['last_name'],
                    'email'      => $arguments['email'] ?? null,
                    'phone'      => $arguments['phone'] ?? null,
                    'count'      => (int) $arguments['guest_count'],
                    'notes'      => $arguments['notes'] ?? null,
                ],
                [$slotOrder],
            );

            $order->loadMissing('bookings');

            return ToolResult::success([
                'order_uuid' => $order->uuid,
                'bookings'   => $order->bookings->map(fn ($booking) => [
                    'id'            => $booking->id,
                    'uuid'          => $booking->uuid,
                    'event_slot_id' => $booking->event_slot_id,
                    'place'         => $booking->place_label,
                    'guest_name'    => $booking->guest_name,
                    'guest_count'   => $booking->guest_count,
                    'status'        => $booking->status,
                ])->all(),
            ], ['created' => true]);
        } catch (GuestOrderException $e) {
            // Fachliche Ablehnung, kein Programmfehler: Der Code sagt, WAS im
            // Weg stand (voller Tisch, gesperrter Raum, unbekannter Artikel).
            return ToolResult::error($e->getMessage(), $e->errorCode);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Anlegen der Buchung: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'bookings', 'create'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['creates'],
            'risk_level'    => 'write',
        ];
    }
}
