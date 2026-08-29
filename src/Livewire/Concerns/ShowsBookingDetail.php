<?php

namespace Platform\Reservation\Livewire\Concerns;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Support\BookingItemsPresenter;

/**
 * Detail-Fenster einer Buchung: Kontext, Bestellpositionen, Summe.
 *
 * Der Einstieg ist überall derselbe – ein Klick auf die Zeile – und die
 * Antwort auf „was hat der Gast bestellt?" soll überall gleich aussehen.
 * Liegt deshalb hier statt in der einzelnen Liste: gebraucht in „Alle
 * Buchungen" und im VA-Dashboard. Die Oberfläche dazu steht einmal in
 * resources/views/partials/booking-detail-modal.blade.php.
 *
 * Setzt PrintsBookingReceipt voraus: Das Fenster bietet „Bon drucken" an,
 * weil man von hier aus am ehesten drucken will – man hat gerade
 * nachgesehen, was drin ist.
 */
trait ShowsBookingDetail
{
    public bool $showDetail = false;
    public ?int $detailBookingId = null;

    /**
     * Die geöffnete Buchung, voll geladen.
     *
     * Team-Filter, obwohl die ID aus der eigenen Liste kommt: Die ID steht in
     * einem wire:click und ist damit vom Client änderbar. Ohne den Filter
     * ließe sich jede fremde Buchung samt Name, E-Mail und Telefon öffnen.
     */
    #[Computed]
    public function detailBooking(): ?Booking
    {
        if (! $this->detailBookingId) {
            return null;
        }

        return Booking::with(['items.menuItem', 'items.bundleMenuItem', 'table.floorPlan.venue', 'event', 'slot', 'order.payment'])
            ->where('team_id', Auth::user()?->current_team_id)
            ->find($this->detailBookingId);
    }

    public function openDetail(int $bookingId): void
    {
        $this->detailBookingId = $bookingId;
        $this->showDetail = true;

        // Sonst zeigt das Fenster beim zweiten Öffnen noch die Buchung von
        // vorhin: Livewire hält berechnete Eigenschaften über die Anfrage.
        unset($this->detailBooking);
    }

    public function closeDetail(): void
    {
        $this->showDetail = false;
        $this->detailBookingId = null;

        unset($this->detailBooking);
    }

    /**
     * Positionen der geöffneten Buchung für die Anzeige.
     *
     * Über denselben Presenter wie Beleg und Gast-API: Ein Bundle ist EIN
     * Posten mit Bundle-Preis, darunter sein Inhalt. Ohne das stünden hier die
     * internen Aufteilungsbeträge – bei drei Bier etwa "2× BIER à 5,54 €" und
     * "1× BIER à 5,53 €", was aussieht, als koste dasselbe Bier verschieden viel.
     *
     * @return array<int, array<string, mixed>>
     */
    public function detailBlocks(): array
    {
        $booking = $this->detailBooking;

        return $booking ? BookingItemsPresenter::blocks($booking->items) : [];
    }
}
