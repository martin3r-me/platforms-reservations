<?php

namespace Platform\Reservation\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use Platform\Reservation\Models\Booking;
use Illuminate\Support\Facades\Auth;
use Platform\Reservation\Livewire\Concerns\PrintsBookingReceipt;
use Platform\Reservation\Support\BookingItemsPresenter;

class BookingList extends Component
{
    use PrintsBookingReceipt;
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

        return Booking::with(['items.menuItem', 'items.bundleMenuItem', 'table.floorPlan.venue', 'event', 'slot', 'order.payment'])
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

    /**
     * Gast ist nicht erschienen.
     *
     * Wirkt weiter als nur auf das Abzeichen in der Liste: No-Shows sind aus
     * Umsatz, Küchenmengen und Sitzplatz-Verfügbarkeit ausgenommen. Ein
     * Fehlgriff verschiebt also Zahlen, deshalb der Rückweg über
     * reopenBooking().
     */
    public function markNoShow(int $bookingId): void
    {
        Booking::findOrFail($bookingId)->update(['status' => Booking::STATUS_NO_SHOW]);
        unset($this->bookings);
    }

    /** Gast war da, der Abend ist für diese Buchung erledigt. */
    public function markCompleted(int $bookingId): void
    {
        Booking::findOrFail($bookingId)->update(['status' => Booking::STATUS_COMPLETED]);
        unset($this->bookings);
    }

    /**
     * Zurück auf "bestätigt" - der Rückweg aus No-Show und Abgeschlossen.
     *
     * Ohne ihn wäre beides eine Einbahnstraße: Die Oberfläche kennt sonst
     * keine Stelle, an der sich ein Status wieder ändern lässt, und ein
     * Fehlgriff im Menü bliebe für immer stehen.
     *
     * Ohne automatischen Bon-Druck, denn genau den löst ein Wechsel auf
     * "bestätigt" sonst aus (siehe Booking::booted). Bei einer Korrektur ist
     * der Bon längst gedruckt; ein zweiter würde in der Küche als weitere
     * Bestellung gelesen.
     */
    public function reopenBooking(int $bookingId): void
    {
        Booking::ohneAutoDruck(function () use ($bookingId) {
            Booking::findOrFail($bookingId)->update(['status' => Booking::STATUS_CONFIRMED]);
        });

        unset($this->bookings);
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

    public function render()
    {
        return view('reservation::livewire.booking-list')
            ->layout('platform::layouts.app');
    }
}
