<?php

namespace Platform\Reservation\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\BookingItem;
use Platform\Reservation\Models\Event;

/**
 * VA-Dashboard: operativer Hub einer Veranstaltung. Bündelt Kennzahlen und
 * die Einstiege in die vollwertigen Views (Küche, Laufzettel) sowie die
 * druckbaren Ansichten.
 */
class EventDashboard extends Component
{
    #[Locked]
    public int $eventId;

    /** Seed für die generative Ambient-Komposition – pro Seitenaufruf neu, stabil über Re-Renders. */
    #[Locked]
    public int $artSeed = 0;

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
        $this->artSeed = random_int(1, 999_999);
        $this->event; // Team-Scope prüfen (404 bei fremdem Team)
    }

    #[Computed]
    public function event(): Event
    {
        return Event::forTeam(Auth::user()?->current_team_id ?? 0)
            ->with(['slots', 'venue', 'imageFile.variants'])
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
     * Aktive Buchungen des Termins, gruppiert nach Pause (Slot). Eine VA kann
     * mehrere Pausen haben; alle Slots erscheinen (auch leere), am Ende ggf.
     * eine „Ohne Pause"-Gruppe.
     *
     * @return \Illuminate\Support\Collection<int, array{label: string, bookings: \Illuminate\Support\Collection, count: int, guests: int, revenue: float}>
     */
    #[Computed]
    public function bookingsBySlot(): \Illuminate\Support\Collection
    {
        $bookings = Booking::where('event_id', $this->eventId)
            ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW])
            ->with('table')
            ->withCount('items')
            ->orderBy('guest_name')
            ->get();

        $bySlot = $bookings->groupBy('slot_id');

        $groups = $this->event->slots
            ->sortBy(fn ($s) => (string) $s->time_start)
            ->map(fn ($slot) => $this->slotGroup($slot->displayLabel(), $bySlot->get($slot->id, collect())))
            ->values();

        $noSlot = $bookings->filter(fn ($b) => $b->slot_id === null);
        if ($noSlot->isNotEmpty()) {
            $groups->push($this->slotGroup('Ohne Pause', $noSlot));
        }

        return $groups;
    }

    /** @param  \Illuminate\Support\Collection  $bookings */
    private function slotGroup(string $label, $bookings): array
    {
        return [
            'label'    => $label,
            'bookings' => $bookings->values(),
            'count'    => $bookings->count(),
            'guests'   => (int) $bookings->sum('guest_count'),
            'revenue'  => (float) $bookings->sum('total_amount'),
        ];
    }

    public function render()
    {
        return view('reservation::livewire.event-dashboard')
            ->layout('platform::layouts.app');
    }
}
