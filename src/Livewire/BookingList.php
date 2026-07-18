<?php

namespace Platform\Reservation\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use Platform\Reservation\Models\Booking;
use Illuminate\Support\Facades\Auth;

class BookingList extends Component
{
    use WithPagination;

    public string $filterDate = '';
    public string $filterStatus = '';
    public string $search = '';

    // Detail-Modal
    public bool $showDetail = false;
    public ?int $detailBookingId = null;

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

    #[Computed]
    public function detailBooking(): ?Booking
    {
        if (!$this->detailBookingId) {
            return null;
        }

        return Booking::with(['items.menuItem', 'table.floorPlan.venue', 'event', 'slot', 'order.payment'])
            ->where('team_id', Auth::user()?->current_team_id)
            ->find($this->detailBookingId);
    }

    public function openDetail(int $bookingId): void
    {
        $this->detailBookingId = $bookingId;
        $this->showDetail = true;
    }

    public function confirmBooking(int $bookingId): void
    {
        Booking::findOrFail($bookingId)->update(['status' => Booking::STATUS_CONFIRMED]);
        unset($this->bookings);
    }

    public function cancelBooking(int $bookingId): void
    {
        Booking::findOrFail($bookingId)->update(['status' => Booking::STATUS_CANCELLED]);
        unset($this->bookings);
    }

    public function markNoShow(int $bookingId): void
    {
        Booking::findOrFail($bookingId)->update(['status' => Booking::STATUS_NO_SHOW]);
        unset($this->bookings);
    }

    public function markCompleted(int $bookingId): void
    {
        Booking::findOrFail($bookingId)->update(['status' => Booking::STATUS_COMPLETED]);
        unset($this->bookings);
    }

    public function render()
    {
        return view('reservation::livewire.booking-list')
            ->layout('platform::layouts.app');
    }
}
