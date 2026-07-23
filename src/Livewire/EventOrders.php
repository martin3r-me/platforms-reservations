<?php

namespace Platform\Reservation\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\BookingItem;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Models\MenuItem;

/**
 * Küchen-Übersicht: Gesamtbestellungen eines Termins, aufgeschlüsselt
 * nach Pausen-Slot – damit die Küche bereitstellen kann.
 *
 * Zählt alle aktiven Buchungen (ohne storniert/No-Show).
 */
class EventOrders extends Component
{
    #[Locked]
    public int $eventId;

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
        $this->event; // Team-Scope prüfen (404 bei fremdem Team)
    }

    #[Computed]
    public function event(): Event
    {
        return Event::forTeam(Auth::user()?->current_team_id ?? 0)
            ->with('slots')
            ->findOrFail($this->eventId);
    }

    /**
     * Vorbereitungsplan je Pause, gruppiert nach Standzeit-Klasse (Timing):
     * pro Gruppe die aggregierten Mengen je Artikel + „Zubereiten ab"-Zeit
     * (Pausenbeginn − Vorlaufzeit). Zeitlich egal/vorab zuerst, dann nach Zeit.
     *
     * @return \Illuminate\Support\Collection<int, array{slot: mixed, total: int, groups: \Illuminate\Support\Collection}>
     */
    #[Computed]
    public function prepBySlot(): \Illuminate\Support\Collection
    {
        $rows = BookingItem::query()
            ->join('reservation_bookings as b', 'b.id', '=', 'reservation_booking_items.booking_id')
            ->where('b.event_id', $this->eventId)
            ->whereNotIn('b.status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW])
            ->groupBy('reservation_booking_items.menu_item_id', 'b.event_slot_id')
            ->selectRaw('reservation_booking_items.menu_item_id as item_id, b.event_slot_id as slot_id, SUM(reservation_booking_items.quantity) as qty')
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $items = MenuItem::with('holdingClass')
            ->whereIn('id', $rows->pluck('item_id')->unique())
            ->get()
            ->keyBy('id');

        return $this->event->slots
            ->sortBy(fn ($s) => (string) $s->time_start)
            ->map(function ($slot) use ($rows, $items) {
                $slotRows = $rows->where('slot_id', $slot->id);

                $groups = $slotRows
                    ->groupBy(fn ($r) => $items[$r->item_id]?->holding_class_id ?? 0)
                    ->map(function ($grp) use ($items, $slot) {
                        $hc   = $items[$grp->first()->item_id]?->holdingClass;
                        $lead = $hc?->lead_time_minutes;
                        $target = ($lead !== null && $slot->time_start)
                            ? \Carbon\Carbon::createFromFormat('H:i', substr((string) $slot->time_start, 0, 5))->subMinutes((int) $lead)->format('H:i')
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

    /** Buchungs-/Gäste-Statistik je Slot (+ Gesamt unter Key 0). */
    #[Computed]
    public function slotStats(): \Illuminate\Support\Collection
    {
        $stats = Booking::query()
            ->where('event_id', $this->eventId)
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

    public function render()
    {
        return view('reservation::livewire.event-orders')
            ->layout('platform::layouts.app');
    }
}
