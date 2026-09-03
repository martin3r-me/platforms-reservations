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
use Platform\Reservation\Models\Order;

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
            // Vorher stand hier die Zahl der ausstehenden Buchungen unter der
            // Überschrift „warten auf Bestätigung". Das versprach eine Aufgabe,
            // die es nicht gibt: Eine ausstehende Buchung wird von Mollie
            // bestätigt oder gar nicht - von Hand bestätigt sie niemand, es gibt
            // im ganzen Modul keinen solchen Weg. Die Zahl war ein abgebrochener
            // Bestellvorgang, kein Posteingang.
            //
            // Was dort hingehört, ist etwas, das jemand tun kann: die
            // ungesehenen Vorgänge.
            'unseen_inbox'     => Order::ungeseheneImPosteingang($teamId),
            'upcoming_events'  => Event::forTeam($teamId)->upcoming()->count(),
            'month_revenue'    => $monthRevenue,
            'approved_items'   => MenuItem::forTeam($teamId)->approved()->count(),
            'total_items'      => MenuItem::forTeam($teamId)->count(),
            // Die Kachel behauptete fest „Vier-Augen-Freigabe" – auch dort, wo
            // das Team die Pflicht abgeschaltet hat. Was auf MICH wartet, zählt
            // nach derselben Regel wie der Zähler am Menü.
            'four_eyes'        => CheckoutSetting::forTeam($teamId)->fourEyesRequired(),
            'awaiting_me'      => Auth::user()
                ? MenuItem::forTeam($teamId)->awaitingApprovalBy(Auth::user(), $teamId)->count()
                : 0,
        ];
    }

    /**
     * Die naechsten Termine.
     *
     * Acht, weil daneben acht Buchungen stehen: Die beiden Karten liegen
     * nebeneinander, und mit fuenf Zeilen links blieb rechts der halbe Kasten
     * leer. Wer diese Zahl aendert, sollte recentBookings() gleich mitnehmen -
     * sonst franst es wieder aus.
     */
    #[Computed]
    public function upcomingEvents(): \Illuminate\Database\Eloquent\Collection
    {
        return Event::forTeam($this->getTeamId())
            ->upcoming()
            ->with(['venue', 'slots'])
            ->withCount('bookings')
            ->orderBy('date')
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function recentBookings(): \Illuminate\Database\Eloquent\Collection
    {
        return Booking::forTeam($this->getTeamId())
            ->with(['event', 'table', 'pickupStation'])
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
