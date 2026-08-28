<?php

namespace Platform\Reservation\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\BookingItem;
use Platform\Reservation\Models\CheckoutSetting;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Models\MenuItem;

/**
 * PausePlus-Startseite: Kennzahlen, nächste Termine, neueste Buchungen.
 */
class Dashboard extends Component
{
    protected function getTeamId(): int
    {
        return (int) (Auth::user()?->current_team_id ?? 0);
    }

    #[Computed]
    public function stats(): object
    {
        $teamId = $this->getTeamId();

        // Abgegrenzt wie in den Finanzen und nicht mehr nach eigener Regel.
        // Vorher zaehlten hier auch ausstehende Buchungen mit - bestellt, aber
        // nicht bezahlt -, und die Startseite nannte deshalb einen hoeheren
        // Monatsumsatz als die Finanzen zwei Klicks weiter.
        $monthRevenue = (float) BookingItem::query()
            ->join('reservation_bookings as b', 'b.id', '=', 'reservation_booking_items.booking_id')
            ->where('b.team_id', $teamId)
            ->whereIn('b.status', CheckoutSetting::forTeam($teamId)->umsatzStatus())
            ->whereBetween('b.date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum(\Illuminate\Support\Facades\DB::raw('reservation_booking_items.quantity * reservation_booking_items.unit_price'));

        return (object) [
            'pending_bookings' => Booking::forTeam($teamId)->where('status', Booking::STATUS_PENDING)->count(),
            'upcoming_events'  => Event::forTeam($teamId)->upcoming()->count(),
            'month_revenue'    => $monthRevenue,
            'approved_items'   => MenuItem::forTeam($teamId)->approved()->count(),
            'total_items'      => MenuItem::forTeam($teamId)->count(),
        ];
    }

    #[Computed]
    public function upcomingEvents(): \Illuminate\Database\Eloquent\Collection
    {
        return Event::forTeam($this->getTeamId())
            ->upcoming()
            ->with(['venue', 'slots'])
            ->withCount('bookings')
            ->orderBy('date')
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function recentBookings(): \Illuminate\Database\Eloquent\Collection
    {
        return Booking::forTeam($this->getTeamId())
            ->with(['event', 'table'])
            ->withCount('items')
            ->latest()
            ->limit(8)
            ->get();
    }

    public function render()
    {
        return view('reservation::livewire.dashboard')
            ->layout('platform::layouts.app');
    }
}
