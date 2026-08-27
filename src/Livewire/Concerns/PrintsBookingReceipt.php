<?php

namespace Platform\Reservation\Livewire\Concerns;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\CheckoutSetting;
use Platform\Reservation\Support\PrintingBridge;

/**
 * Bon-Druck: Modal-Zustand, Drucker-/Gruppenwahl und der Druckauftrag selbst.
 *
 * Zwei Wege, ein Ablauf: eine einzelne Buchung (openPrintModal) oder ein
 * ganzer Stapel (openBatchPrintModal) – etwa alle Buchungen einer
 * Veranstaltung. Auch der Stapel druckt je Buchung einen eigenen Bon; das
 * Druck-Modul kennt nur Einzelaufträge, und mehr braucht es nicht: Die
 * Aufträge stehen in einer Warteschlange, die der Drucker der Reihe nach
 * abarbeitet. Gespart wird der Knopfdruck, nicht das Papier.
 *
 * Was ein Stapel umfasst, entscheidet die Komponente über
 * batchPrintBookings() – hier ist er leer.
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
    public bool $printBatch = false;
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
        $this->printBatch = false;
        $this->resetPrintSelection();
    }

    /** Stapel: alles, was batchPrintBookings() der Komponente liefert. */
    public function openBatchPrintModal(): void
    {
        if (! $this->printingAvailable) {
            return;
        }

        $this->printBookingId = null;
        $this->printBatch = true;
        $this->resetPrintSelection();
    }

    /**
     * Ziel vorwählen, damit der Dialog möglichst nur noch bestätigt werden muss.
     *
     * Zuerst das Ziel des automatischen Bon-Drucks: Hat das Team eines gesetzt,
     * ist genau das der Drucker, auf dem seine Bons sonst auch herauskommen.
     * Sonst der einzige vorhandene Drucker – gibt es nur einen, ist die Frage
     * ohnehin keine.
     */
    private function resetPrintSelection(): void
    {
        $this->printTarget = 'printer';
        $this->selectedPrinterId = null;
        $this->selectedPrinterGroupId = null;

        $settings = CheckoutSetting::forTeam((int) (Auth::user()?->current_team_id ?? 0));

        if ($settings->auto_print_printer_group_id) {
            $this->printTarget = 'group';
            $this->selectedPrinterGroupId = (int) $settings->auto_print_printer_group_id;
        } elseif ($settings->auto_print_printer_id) {
            $this->selectedPrinterId = (int) $settings->auto_print_printer_id;
        } elseif ($this->printers->count() === 1) {
            $this->selectedPrinterId = (int) $this->printers->first()->id;
        }

        $this->printModalShow = true;
        unset($this->printBookings);
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
        $this->printBatch = false;
        $this->selectedPrinterId = null;
        $this->selectedPrinterGroupId = null;
        unset($this->printBookings);
    }

    /**
     * Die Buchungen des aktuellen Auftrags – eine oder viele.
     *
     * Der Dialog zeigt daraus die Anzahl an; niemand soll erst am Papierstoß
     * merken, wie viele Bons er gerade ausgelöst hat.
     *
     * Nur bei offenem Dialog gefüllt: sonst liefe bei jedem Render eine
     * Abfrage für einen Auftrag, den es gar nicht gibt.
     *
     * @return \Illuminate\Support\Collection<int, Booking>
     */
    #[Computed]
    public function printBookings(): \Illuminate\Support\Collection
    {
        if (! $this->printModalShow) {
            return collect();
        }

        if ($this->printBatch) {
            return $this->batchPrintBookings();
        }

        if (! $this->printBookingId) {
            return collect();
        }

        return Booking::where('team_id', Auth::user()?->current_team_id)
            ->where('id', $this->printBookingId)
            ->get();
    }

    /**
     * Was ein Stapeldruck umfasst. Standard: nichts – wer
     * openBatchPrintModal() anbietet, beantwortet das hier.
     *
     * Die Umsetzung muss auf das eigene Team eingegrenzt sein.
     *
     * @return \Illuminate\Support\Collection<int, Booking>
     */
    protected function batchPrintBookings(): \Illuminate\Support\Collection
    {
        return collect();
    }

    public function printBookingConfirm(): void
    {
        $service = PrintingBridge::service();

        if (! $service) {
            return;
        }

        if (! $this->selectedPrinterId && ! $this->selectedPrinterGroupId) {
            session()->flash('booking_error', 'Bitte einen Drucker oder eine Gruppe wählen.');
            return;
        }

        $bookings = $this->printBookings;

        if ($bookings->isEmpty()) {
            $this->closePrintModal();
            session()->flash('booking_error', 'Es gibt keine Buchung zum Drucken.');
            return;
        }

        // Ein Auftrag je Buchung, in der Reihenfolge der Liste. Der Drucker
        // arbeitet die Warteschlange nach Eingang ab, der Stapel liegt also
        // so da, wie er auf dem Schirm steht.
        foreach ($bookings as $booking) {
            $service->createJob(
                printable: $booking,
                data: ['requested_by' => Auth::user()?->name],
                printerId: $this->selectedPrinterId ? (int) $this->selectedPrinterId : null,
                printerGroupId: $this->selectedPrinterGroupId ? (int) $this->selectedPrinterGroupId : null,
            );
        }

        $anzahl = $bookings->count();

        $this->closePrintModal();
        session()->flash('booking_message', $anzahl === 1
            ? 'Bon-Druckauftrag wurde erstellt.'
            : $anzahl.' Bon-Druckaufträge wurden erstellt.');
    }
}
