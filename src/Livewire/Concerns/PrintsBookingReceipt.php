<?php

namespace Platform\Reservation\Livewire\Concerns;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Support\PrintingBridge;

/**
 * Bon-Druck für eine einzelne Buchung: Modal-Zustand, Drucker-/Gruppenwahl
 * und der Druckauftrag selbst.
 *
 * Liegt hier, weil derselbe Ablauf an mehreren Stellen gebraucht wird –
 * in „Alle Buchungen" und im VA-Dashboard. Die Oberfläche dazu steht einmal
 * in resources/views/partials/booking-print-modal.blade.php.
 *
 * Das Druck-Modul ist optional: Ist es nicht installiert, meldet
 * $this->printingAvailable false und der Einstieg wird gar nicht erst gezeigt.
 */
trait PrintsBookingReceipt
{
    public bool $printModalShow = false;
    public ?int $printBookingId = null;
    public string $printTarget = 'printer'; // printer|group
    public ?int $selectedPrinterId = null;
    public ?int $selectedPrinterGroupId = null;

    #[Computed]
    public function printingAvailable(): bool
    {
        return PrintingBridge::available();
    }

    #[Computed]
    public function printers(): \Illuminate\Support\Collection
    {
        return PrintingBridge::printers();
    }

    #[Computed]
    public function printerGroups(): \Illuminate\Support\Collection
    {
        return PrintingBridge::printerGroups();
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
        $service = PrintingBridge::service();

        if (! $service || ! $this->printBookingId) {
            return;
        }

        if (! $this->selectedPrinterId && ! $this->selectedPrinterGroupId) {
            session()->flash('booking_error', 'Bitte einen Drucker oder eine Gruppe wählen.');
            return;
        }

        $booking = Booking::with(['items.menuItem', 'items.bundleMenuItem', 'table.floorPlan', 'event', 'slot'])
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
}
