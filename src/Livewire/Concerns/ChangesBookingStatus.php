<?php

namespace Platform\Reservation\Livewire\Concerns;

use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\Order;

/**
 * Statuswechsel am Abend: erschienen, nicht erschienen, doch wieder offen.
 *
 * Liegt hier, weil zwei Listen dieselben Wechsel anbieten – „Alle Buchungen"
 * und das VA-Dashboard. Die Oberfläche dazu steht einmal in
 * resources/views/partials/booking-status-modal.blade.php.
 *
 * Was danach neu zu laden ist, weiß nur die Komponente: Die eine hält eine
 * flache Liste, die andere nach Pausen gruppierte Zahlen. Dafür
 * afterBookingStatusChanged().
 *
 * Die Team-Grenze kommt aus dem globalen Scope auf Booking (BelongsToTeam):
 * findOrFail() auf eine fremde ID endet in 404, nicht in einer fremden
 * Buchung.
 */
trait ChangesBookingStatus
{
    /** @var 'no_show'|'reopen'|'cancel'|'' */
    public string $statusAction = '';

    #[Locked]
    public ?int $statusBookingId = null;

    public bool $statusModalShow = false;

    /**
     * Gast war da, der Abend ist für diese Buchung erledigt.
     *
     * Ohne Rückfrage: „Abgeschlossen" nimmt nichts weg und lässt sich
     * jederzeit zurücknehmen. Eine Nachfrage mitten im Service wäre nur im
     * Weg.
     */
    public function markCompleted(int $bookingId): void
    {
        Booking::findOrFail($bookingId)->update(['status' => Booking::STATUS_COMPLETED]);
        $this->afterBookingStatusChanged();
    }

    /**
     * No-Show, Stornieren und Zurücknehmen laufen über eine Rückfrage.
     *
     * No-Show nimmt der Buchung ihr Gewicht in Umsatz, Küche und
     * Platzprüfung; beim Zurücknehmen hängt das Ziel an der Bestellung und
     * ist nicht selbsterklärend. Beides gehört vor den Klick, nicht danach.
     */
    public function askNoShow(int $bookingId): void
    {
        $this->oeffneRueckfrage($bookingId, 'no_show');
    }

    public function askReopen(int $bookingId): void
    {
        $this->oeffneRueckfrage($bookingId, 'reopen');
    }

    /**
     * Stornieren – der schwerste der Wechsel und der einzige ohne Rückweg
     * im Menü.
     */
    public function askCancel(int $bookingId): void
    {
        $this->oeffneRueckfrage($bookingId, 'cancel');
    }

    private function oeffneRueckfrage(int $bookingId, string $aktion): void
    {
        $this->statusBookingId = $bookingId;
        $this->statusAction    = $aktion;
        $this->statusModalShow = true;

        unset($this->statusBooking);
    }

    public function closeStatusModal(): void
    {
        $this->statusModalShow = false;
        $this->statusBookingId = null;
        $this->statusAction    = '';

        unset($this->statusBooking);
    }

    /**
     * Die Buchung, um die es in der Rückfrage geht.
     *
     * Nur bei offener Rückfrage gefüllt – sonst liefe bei jedem Render eine
     * Abfrage für einen Vorgang, den es gar nicht gibt.
     */
    #[Computed]
    public function statusBooking(): ?Booking
    {
        if (! $this->statusModalShow || ! $this->statusBookingId) {
            return null;
        }

        return Booking::with('order')->find($this->statusBookingId);
    }

    /**
     * Wohin ein Zurücknehmen führt – „bestätigt" oder „ausstehend".
     *
     * NICHT pauschal auf „bestätigt": Bei einer Buchung aus dem Shop heißt
     * bestätigt „über Mollie bezahlt" (siehe MolliePaymentService). Wer sie
     * von Hand dorthin zurückstellt, behauptet einen Zahlungseingang, den es
     * vielleicht nie gab – und die Buchung zählte wieder in Umsatz, Küche und
     * Platzprüfung.
     *
     * Die Bestellung weiß es dagegen sicher: Sie steht auf „bestätigt", wenn
     * Mollie bezahlt gemeldet hat, und ebenso beim Backoffice-Weg, wo vor Ort
     * bezahlt wird. Steht sie noch auf „ausstehend", ist die Buchung eben
     * ausstehend – das ist der Zustand, den sie ohne den Fehlgriff hätte.
     *
     * Ohne Bestellung (Altdaten aus der Zeit vor der Order-Klammer) der
     * vorsichtigere Weg: ausstehend.
     */
    public function reopenTargetStatus(?Booking $booking): string
    {
        return $booking?->order?->status === Order::STATUS_CONFIRMED
            ? Booking::STATUS_CONFIRMED
            : Booking::STATUS_PENDING;
    }

    /**
     * Die Rückfrage bestätigen.
     *
     * Parameterlos und auf einer #[Locked]-Eigenschaft arbeitend: So kann
     * zwischen Öffnen und Bestätigen keine andere Buchung untergeschoben
     * werden.
     */
    public function confirmStatusChange(): void
    {
        $booking = $this->statusBooking;
        $aktion  = $this->statusAction;

        if (! $booking) {
            $this->closeStatusModal();
            return;
        }

        if ($aktion === 'no_show') {
            $booking->update(['status' => Booking::STATUS_NO_SHOW]);
        } elseif ($aktion === 'cancel') {
            $booking->update(['status' => Booking::STATUS_CANCELLED]);
        } elseif ($aktion === 'reopen') {
            $ziel = $this->reopenTargetStatus($booking);

            // Ohne automatischen Bon-Druck: Ein Wechsel auf „bestätigt" löst
            // ihn sonst aus (siehe Booking::booted). Bei einer Korrektur ist
            // der Bon längst gedruckt; ein zweiter würde in der Küche als
            // weitere Bestellung gelesen.
            Booking::ohneAutoDruck(fn () => $booking->update(['status' => $ziel]));
        } else {
            $this->closeStatusModal();
            return;
        }

        $this->closeStatusModal();
        $this->afterBookingStatusChanged();
    }

    /** Was die Komponente nach einem Wechsel neu berechnen muss. */
    abstract protected function afterBookingStatusChanged(): void;
}
