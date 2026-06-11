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
     * Aggregierte Mengen: [menu_item_id => [event_slot_id => qty]].
     */
    #[Computed]
    public function quantities(): \Illuminate\Support\Collection
    {
        return BookingItem::query()
            ->join('reservation_bookings as b', 'b.id', '=', 'reservation_booking_items.booking_id')
            ->where('b.event_id', $this->eventId)
            ->whereNotIn('b.status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW])
            ->groupBy('reservation_booking_items.menu_item_id', 'b.event_slot_id')
            ->selectRaw('reservation_booking_items.menu_item_id, b.event_slot_id, SUM(reservation_booking_items.quantity) as qty')
            ->get()
            ->groupBy('menu_item_id')
            ->map(fn ($rows) => $rows->pluck('qty', 'event_slot_id')->map(fn ($q) => (int) $q));
    }

    /** Artikel der Bestellungen, nach Kategorie gruppiert. */
    #[Computed]
    public function itemsByCategory(): \Illuminate\Support\Collection
    {
        if ($this->quantities->isEmpty()) {
            return collect();
        }

        return MenuItem::with('category')
            ->whereIn('id', $this->quantities->keys())
            ->get()
            ->sortBy([['category.sort_order', 'asc'], ['sort_order', 'asc'], ['name', 'asc']])
            ->groupBy(fn (MenuItem $item) => $item->category?->name ?? 'Sonstiges');
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

    #[Computed]
    public function totalQuantity(): int
    {
        return (int) $this->quantities->sum(fn ($bySlot) => $bySlot->sum());
    }

    public function render()
    {
        return view('reservation::livewire.event-orders')
            ->layout('platform::layouts.app');
    }
}
