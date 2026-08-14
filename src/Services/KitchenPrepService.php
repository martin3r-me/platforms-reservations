<?php

namespace Platform\Reservation\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\BookingItem;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Models\MenuItem;

/**
 * Vorbereitungsplan für die Küche.
 *
 * Lag ursprünglich im EventOrders-Component. Herausgezogen, weil der
 * Freigabe-Link für Veranstaltungsleiter dieselbe Ansicht braucht – ohne
 * Anmeldung und damit ohne Team-Scope. Zwei Fassungen derselben Rechnung
 * liefen bei uns schon einmal auseinander; einmal reicht.
 *
 * Bundles sind hier bereits aufgelöst: Gezählt werden Buchungspositionen,
 * und die zeigen auf die Bestandteile. Genau so soll es sein – Brezel und
 * Bier haben verschiedene Standzeiten und gehören getrennt auf den Zettel.
 */
class KitchenPrepService
{
    /**
     * Mengen je Pause, gruppiert nach Standzeit-Klasse.
     *
     * Stornierte Buchungen und No-Shows zählen nicht mit.
     *
     * @return Collection<int, array{slot: mixed, total: int, groups: Collection}>
     */
    public function prepBySlot(Event $event): Collection
    {
        $rows = BookingItem::query()
            ->join('reservation_bookings as b', 'b.id', '=', 'reservation_booking_items.booking_id')
            ->where('b.event_id', $event->id)
            ->whereNotIn('b.status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW])
            ->groupBy('reservation_booking_items.menu_item_id', 'b.event_slot_id')
            ->selectRaw('reservation_booking_items.menu_item_id as item_id, b.event_slot_id as slot_id, SUM(reservation_booking_items.quantity) as qty')
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $items = MenuItem::withoutGlobalScope('team')
            ->with(['holdingClass' => fn ($q) => $q->withoutGlobalScope('team')])
            ->whereIn('id', $rows->pluck('item_id')->unique())
            ->get()
            ->keyBy('id');

        return $event->slots
            ->sortBy(fn ($s) => (string) $s->time_start)
            ->map(function ($slot) use ($rows, $items) {
                $slotRows = $rows->where('slot_id', $slot->id);

                $groups = $slotRows
                    ->groupBy(fn ($r) => $items[$r->item_id]?->holding_class_id ?? 0)
                    ->map(function ($grp) use ($items, $slot) {
                        $hc   = $items[$grp->first()->item_id]?->holdingClass;
                        $lead = $hc?->lead_time_minutes;
                        $target = ($lead !== null && $slot->time_start)
                            ? Carbon::createFromFormat('H:i', substr((string) $slot->time_start, 0, 5))->subMinutes((int) $lead)->format('H:i')
                            : null;

                        return [
                            'name'        => $hc?->name ?? 'Zeitlich egal / vorab',
                            'color'       => $hc?->color,
                            'lead'        => $lead,
                            'target_time' => $target,
                            'sort_order'  => $hc?->sort_order ?? 9999,
                            'total'       => (int) $grp->sum('qty'),
                            'items'       => $grp->map(fn ($r) => [
                                'name' => $items[$r->item_id]?->name ?? 'Artikel',
                                'qty'  => (int) $r->qty,
                            ])->sortByDesc('qty')->values(),
                        ];
                    })
                    // Zeitlich egal/vorab zuerst, danach nach Zubereitungszeit.
                    ->sortBy(fn ($g) => ($g['target_time'] === null ? '0' : '1') . ($g['target_time'] ?? str_pad((string) $g['sort_order'], 5, '0', STR_PAD_LEFT)))
                    ->values();

                return [
                    'slot'   => $slot,
                    'total'  => (int) $slotRows->sum('qty'),
                    'groups' => $groups,
                ];
            })
            ->filter(fn ($s) => $s['groups']->isNotEmpty())
            ->values();
    }

    /**
     * Buchungen und Gäste je Pause; Schlüssel 0 trägt die Gesamtzahlen.
     */
    public function slotStats(Event $event): Collection
    {
        $stats = Booking::withoutGlobalScope('team')
            ->where('event_id', $event->id)
            ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW])
            ->groupBy('event_slot_id')
            ->selectRaw('event_slot_id, COUNT(*) as bookings, SUM(guest_count) as guests')
            ->get()
            ->keyBy('event_slot_id');

        $stats->put(0, (object) [
            'bookings' => (int) $stats->sum('bookings'),
            'guests'   => (int) $stats->sum('guests'),
        ]);

        return $stats;
    }
}
