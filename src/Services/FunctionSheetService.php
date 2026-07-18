<?php

namespace Platform\Reservation\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\Event;

/**
 * Berechnet den Laufzettel (#523) eines Termins: pro Pause die Laufrunden,
 * abgeleitet aus der Standzeit-Klasse der bestellten Artikel und deren
 * Vorlaufzeit. Je Laufrunde: an welchen Tisch was muss, zu welcher Bestellung.
 *
 * Laufrunden-Reihenfolge: zeitunkritische ("egal"/ohne Vorlaufzeit) zuerst
 * (vorab platzierbar), danach nach Ziel-Uhrzeit aufsteigend – Ziel-Uhrzeit =
 * Pausenbeginn − Vorlaufzeit (großer Vorlauf = früher, kleiner = kurz vor Pause).
 */
class FunctionSheetService
{
    /** Standard: alle Buchungen außer storniert / no-show (inkl. pending). */
    public const DEFAULT_STATUSES = [
        Booking::STATUS_PENDING,
        Booking::STATUS_CONFIRMED,
        Booking::STATUS_COMPLETED,
    ];

    /**
     * @param  array<int,string>|null  $statuses  zu berücksichtigende Buchungs-Status
     * @return array{event: array, generated_at: \Carbon\Carbon, pauses: array}
     */
    public function build(Event $event, ?array $statuses = null): array
    {
        $statuses ??= self::DEFAULT_STATUSES;

        $event->loadMissing(['slots', 'venue']);

        $bookings = $event->bookings()
            ->whereIn('status', $statuses)
            ->with(['items.menuItem.holdingClass', 'table.floorPlan'])
            ->get();

        $pauses = $event->slots
            ->sortBy('sort_order')
            ->values()
            ->map(fn ($slot) => [
                'slot' => [
                    'id'         => $slot->id,
                    'name'       => $slot->name,
                    'time_start' => $slot->time_start ? substr((string) $slot->time_start, 0, 5) : null,
                ],
                'runs' => $this->buildRuns($bookings->where('event_slot_id', $slot->id), $slot),
            ])
            ->all();

        return [
            'event' => [
                'id'    => $event->id,
                'uuid'  => $event->uuid,
                'name'  => $event->name,
                'date'  => $event->date,
                'venue' => $event->venue?->name,
            ],
            'generated_at' => now(),
            'pauses'       => $pauses,
        ];
    }

    /**
     * Laufrunden einer Pause aufbauen (gruppiert nach Standzeit-Klasse,
     * darunter Tisch → Bestellung → Artikel).
     */
    protected function buildRuns(Collection $slotBookings, $slot): array
    {
        $runs = [];

        foreach ($slotBookings as $booking) {
            foreach ($booking->items as $item) {
                $class = $item->menuItem?->holdingClass;
                $key   = $class?->id ?? 'none';

                if (! isset($runs[$key])) {
                    $lead   = $class?->lead_time_minutes;
                    $target = ($lead !== null && $slot->time_start)
                        ? $this->targetTime((string) $slot->time_start, (int) $lead)
                        : null;

                    $runs[$key] = [
                        'holding_class' => $class ? [
                            'id'                => $class->id,
                            'name'              => $class->name,
                            'color'             => $class->color,
                            'lead_time_minutes' => $lead,
                        ] : null,
                        'label'       => $class?->name ?? 'Ohne Standzeit-Klasse',
                        'target_time' => $target,
                        'sort_order'  => $class?->sort_order ?? 9999,
                        'tables'      => [],
                    ];
                }

                $tableId = $booking->table_id ?? 0;
                if (! isset($runs[$key]['tables'][$tableId])) {
                    $runs[$key]['tables'][$tableId] = [
                        'table'    => $booking->table ? ['id' => $booking->table->id, 'label' => $booking->table->label] : null,
                        'room'     => $booking->table?->floorPlan?->name,
                        'bookings' => [],
                    ];
                }

                $bookingId = $booking->id;
                if (! isset($runs[$key]['tables'][$tableId]['bookings'][$bookingId])) {
                    $runs[$key]['tables'][$tableId]['bookings'][$bookingId] = [
                        'booking_uuid' => $booking->uuid,
                        'guest_name'   => $booking->guest_name,
                        'items'        => [],
                    ];
                }

                $runs[$key]['tables'][$tableId]['bookings'][$bookingId]['items'][] = [
                    'name'     => $item->menuItem?->name ?? 'Artikel',
                    'quantity' => (int) $item->quantity,
                ];
            }
        }

        return $this->sortAndFlatten($runs);
    }

    /**
     * Laufrunden sortieren (egal zuerst, dann nach Ziel-Uhrzeit) und die
     * assoziativen Zwischen-Container in Listen überführen.
     */
    protected function sortAndFlatten(array $runs): array
    {
        usort($runs, function ($a, $b) {
            // Ohne Ziel-Uhrzeit ("egal") zuerst.
            if ($a['target_time'] === null && $b['target_time'] !== null) {
                return -1;
            }
            if ($a['target_time'] !== null && $b['target_time'] === null) {
                return 1;
            }
            // Beide mit Zeit: aufsteigend (früher zuerst).
            if ($a['target_time'] !== null && $b['target_time'] !== null && $a['target_time'] !== $b['target_time']) {
                return strcmp($a['target_time'], $b['target_time']);
            }

            return $a['sort_order'] <=> $b['sort_order'];
        });

        return array_map(function ($run) {
            $run['tables'] = array_map(function ($table) {
                $table['bookings'] = array_values(array_map(function ($booking) {
                    return $booking;
                }, $table['bookings']));

                return $table;
            }, array_values($run['tables']));

            // Tische stabil nach Label sortieren.
            usort($run['tables'], fn ($a, $b) => strnatcasecmp((string) ($a['table']['label'] ?? ''), (string) ($b['table']['label'] ?? '')));

            unset($run['sort_order']);

            return $run;
        }, $runs);
    }

    /** Ziel-Uhrzeit = Startzeit (HH:MM[:SS]) minus Vorlaufminuten, als "HH:MM". */
    protected function targetTime(string $start, int $leadMinutes): string
    {
        return Carbon::createFromFormat('H:i', substr($start, 0, 5))
            ->subMinutes($leadMinutes)
            ->format('H:i');
    }
}
