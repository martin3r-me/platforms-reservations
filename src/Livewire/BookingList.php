<?php

namespace Platform\Reservation\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use Platform\Reservation\Models\Booking;
use Illuminate\Support\Facades\Auth;
use Platform\Reservation\Livewire\Concerns\ChangesBookingStatus;
use Platform\Reservation\Livewire\Concerns\PrintsBookingReceipt;
use Platform\Reservation\Livewire\Concerns\ShowsBookingDetail;

class BookingList extends Component
{
    use ChangesBookingStatus;
    use PrintsBookingReceipt;
    use ShowsBookingDetail;
    use WithPagination;

    public string $filterDate = '';
    public string $filterStatus = '';
    public string $search = '';

    /**
     * Jeder Filterwechsel führt zurück auf Seite 1.
     *
     * Ohne das bleibt die Seitenzahl stehen: Wer auf Seite 4 filtert und
     * danach nur noch zwei Seiten übrig hat, sieht eine leere Tabelle - und
     * das sieht aus, als wären die Buchungen weg.
     *
     * Namentlich und nicht über das allgemeine updated(): Die Züge dieser
     * Komponente bringen eigene updated*-Haken mit, und ein gemeinsamer
     * Sammelhaken hier würde sich mit ihnen ins Gehege kommen.
     */
    public function updatedFilterDate(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function bookings()
    {
        $user   = Auth::user();
        $teamId = $user?->current_team_id;

        $query = Booking::with(['table.floorPlan.venue', 'order.payment', 'event', 'slot'])
            ->withCount('items')
            ->where('team_id', $teamId)
            ->orderByDesc('date')
            ->orderByDesc('time_start');

        if ($this->filterDate) {
            $query->whereDate('date', $this->filterDate);
        }

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('guest_name', 'like', "%{$this->search}%")
                  ->orWhere('guest_email', 'like', "%{$this->search}%");
            });
        }

        return $query->paginate(25);
    }

    public function confirmBooking(int $bookingId): void
    {
        Booking::findOrFail($bookingId)->update(['status' => Booking::STATUS_CONFIRMED]);
        unset($this->bookings);
    }

    /** Nach einem Statuswechsel: die Liste neu holen. */
    protected function afterBookingStatusChanged(): void
    {
        unset($this->bookings);
    }

    public function render()
    {
        return view('reservation::livewire.booking-list')
            ->layout('platform::layouts.app');
    }
}
