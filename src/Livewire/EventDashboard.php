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
 * VA-Dashboard: operativer Hub einer Veranstaltung. Bündelt Kennzahlen und
 * die Einstiege in die vollwertigen Views (Küche, Laufzettel) sowie die
 * druckbaren Ansichten.
 */
class EventDashboard extends Component
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
            ->with(['slots', 'venue'])
            ->findOrFail($this->eventId);
    }

    /** Operative Kennzahlen (aktive Buchungen, ohne storniert/No-Show). */
    #[Computed]
    public function stats(): array
    {
        $base = Booking::where('event_id', $this->eventId)
            ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW]);

        $revenue = (float) BookingItem::query()
            ->join('reservation_bookings as b', 'b.id', '=', 'reservation_booking_items.booking_id')
            ->where('b.event_id', $this->eventId)
            ->whereNotIn('b.status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW])
            ->selectRaw('COALESCE(SUM(reservation_booking_items.unit_price * reservation_booking_items.quantity), 0) as s')
            ->value('s');

        return [
            'bookings' => (clone $base)->count(),
            'guests'   => (int) (clone $base)->sum('guest_count'),
            'revenue'  => $revenue,
            'pauses'   => $this->event->slots->count(),
        ];
    }

    /**
     * Bestellte Artikel des gesamten Termins (aktive Buchungen), als
     * Gesamtmenge je Artikel – geclustert nach Kategorie.
     *
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, array{name: string, quantity: int}>>
     */
    #[Computed]
    public function itemsByCategory(): \Illuminate\Support\Collection
    {
        $totals = BookingItem::query()
            ->join('reservation_bookings as b', 'b.id', '=', 'reservation_booking_items.booking_id')
            ->where('b.event_id', $this->eventId)
            ->whereNotIn('b.status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW])
            ->groupBy('reservation_booking_items.menu_item_id')
            ->selectRaw('reservation_booking_items.menu_item_id, SUM(reservation_booking_items.quantity) as qty')
            ->pluck('qty', 'menu_item_id');

        if ($totals->isEmpty()) {
            return collect();
        }

        return MenuItem::with('category')
            ->whereIn('id', $totals->keys())
            ->get()
            ->sortBy([['category.sort_order', 'asc'], ['sort_order', 'asc'], ['name', 'asc']])
            ->groupBy(fn (MenuItem $item) => $item->category?->name ?? 'Sonstiges')
            ->map(fn ($items) => $items->map(fn (MenuItem $item) => [
                'name'     => $item->name,
                'quantity' => (int) $totals[$item->id],
            ])->values());
    }

    /** Gesamtmenge bestellter Artikel (für die Section-Überschrift). */
    #[Computed]
    public function totalItems(): int
    {
        return (int) $this->itemsByCategory->flatten(1)->sum('quantity');
    }

    public function render()
    {
        return view('reservation::livewire.event-dashboard')
            ->layout('platform::layouts.app');
    }
}
