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

    // Bon-Druck (optionaler Printing-Service)
    public bool $printModalShow = false;
    public ?int $printBookingId = null;
    public string $printTarget = 'printer'; // printer|group
    public ?int $selectedPrinterId = null;
    public ?int $selectedPrinterGroupId = null;

    private const PRINTING_INTERFACE = 'Platform\\Printing\\Contracts\\PrintingServiceInterface';

    /** Printing-Service, wenn im System verfügbar – sonst null. */
    protected function printingService()
    {
        return (interface_exists(self::PRINTING_INTERFACE) && app()->bound(self::PRINTING_INTERFACE))
            ? app(self::PRINTING_INTERFACE)
            : null;
    }

    #[Computed]
    public function printingAvailable(): bool
    {
        return $this->printingService() !== null;
    }

    #[Computed]
    public function printers(): \Illuminate\Support\Collection
    {
        return $this->printingService()?->listPrinters() ?? collect();
    }

    #[Computed]
    public function printerGroups(): \Illuminate\Support\Collection
    {
        return $this->printingService()?->listPrinterGroups() ?? collect();
    }

    public function openPrintModal(int $bookingId): void
    {
        if (! $this->printingAvailable) {
            return;
        }

        $this->printBookingId = $bookingId;
        $this->printTarget = 'printer';
        $this->selectedPrinterId = null;
        $this->selectedPrinterGroupId = null;

        // Einzigen Drucker automatisch vorwählen.
        if ($this->printers->count() === 1) {
            $this->selectedPrinterId = (int) $this->printers->first()->id;
        }

        $this->printModalShow = true;
    }

    public function updatedPrintTarget(): void
    {
        $this->selectedPrinterId = null;
        $this->selectedPrinterGroupId = null;
    }

    public function closePrintModal(): void
    {
        $this->printModalShow = false;
        $this->printBookingId = null;
        $this->selectedPrinterId = null;
        $this->selectedPrinterGroupId = null;
    }

    public function printBookingConfirm(): void
    {
        $service = $this->printingService();

        if (! $service || ! $this->printBookingId) {
            return;
        }

        if (! $this->selectedPrinterId && ! $this->selectedPrinterGroupId) {
            session()->flash('booking_error', 'Bitte einen Drucker oder eine Gruppe wählen.');
            return;
        }

        $booking = Booking::with(['items.menuItem', 'table.floorPlan', 'event', 'slot'])
            ->where('team_id', Auth::user()?->current_team_id)
            ->find($this->printBookingId);

        if (! $booking) {
            $this->closePrintModal();
            return;
        }

        $service->createJob(
            printable: $booking,
            data: ['requested_by' => Auth::user()?->name],
            printerId: $this->selectedPrinterId ? (int) $this->selectedPrinterId : null,
            printerGroupId: $this->selectedPrinterGroupId ? (int) $this->selectedPrinterGroupId : null,
        );

        $this->closePrintModal();
        session()->flash('booking_message', 'Bon-Druckauftrag wurde erstellt.');
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
