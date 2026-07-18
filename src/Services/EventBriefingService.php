<?php

namespace Platform\Reservation\Services;

use Platform\Reservation\Models\Event;

/**
 * Abend-Übersicht eines Termins: kompakte Kennzahlen (Gäste, Bestellungen,
 * Tische, Speisen, Umsatz), Aufschlüsselung je Pause, Top-Speisen und die
 * Gästeliste (je Bestellung/Party). Grundlage für den druckbaren One-Pager.
 */
class EventBriefingService
{
    /**
     * @param  array<int,string>|null  $statuses
     */
    public function build(Event $event, ?array $statuses = null): array
    {
        $statuses ??= FunctionSheetService::DEFAULT_STATUSES;

        $event->loadMissing(['slots', 'venue']);

        $bookings = $event->bookings()
            ->whereIn('status', $statuses)
            ->with(['items.menuItem', 'table', 'slot'])
            ->get();

        // Party = Bestellung (Order); ohne Order jede Buchung einzeln – damit die
        // Personenzahl nicht über mehrere Pausen doppelt gezählt wird.
        $parties = $bookings->groupBy(fn ($b) => $b->order_id ?? 'b' . $b->id);

        $totals = [
            'guests'   => (int) $parties->sum(fn ($grp) => (int) $grp->first()->guest_count),
            'parties'  => $parties->count(),
            'bookings' => $bookings->count(),
            'tables'   => $bookings->pluck('table_id')->filter()->unique()->count(),
            'items'    => (int) $bookings->flatMap->items->sum('quantity'),
            'revenue'  => (float) $bookings->sum('total_amount'),
            'pauses'   => $event->slots->count(),
        ];

        $topItems = $bookings->flatMap->items
            ->groupBy('menu_item_id')
            ->map(fn ($grp) => [
                'name'     => $grp->first()->menuItem?->name ?? 'Artikel',
                'quantity' => (int) $grp->sum('quantity'),
            ])
            ->sortByDesc('quantity')
            ->values()
            ->take(12)
            ->all();

        $pauses = $event->slots->sortBy('sort_order')->values()->map(function ($slot) use ($bookings) {
            $slotBookings = $bookings->where('event_slot_id', $slot->id);
            $slotParties  = $slotBookings->groupBy(fn ($b) => $b->order_id ?? 'b' . $b->id);

            return [
                'name'     => $slot->name,
                'time'     => $slot->time_start ? substr((string) $slot->time_start, 0, 5) : null,
                'guests'   => (int) $slotParties->sum(fn ($grp) => (int) $grp->first()->guest_count),
                'bookings' => $slotBookings->count(),
                'tables'   => $slotBookings->pluck('table_id')->filter()->unique()->count(),
                'items'    => (int) $slotBookings->flatMap->items->sum('quantity'),
            ];
        })->all();

        $guests = $parties->map(function ($grp) {
            $first = $grp->first();

            return [
                'name'   => $first->guest_name,
                'count'  => (int) $first->guest_count,
                'tables' => $grp->map(fn ($b) => $b->table?->label)->filter()->unique()->values()->all(),
                'pauses' => $grp->map(fn ($b) => $b->slot?->name)->filter()->unique()->values()->all(),
                'items'  => (int) $grp->flatMap->items->sum('quantity'),
            ];
        })->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();

        return [
            'event' => [
                'id'    => $event->id,
                'name'  => $event->name,
                'date'  => $event->date,
                'venue' => $event->venue?->name,
            ],
            'generated_at' => now(),
            'totals'       => $totals,
            'pauses'       => $pauses,
            'top_items'    => $topItems,
            'guests'       => $guests,
        ];
    }
}
