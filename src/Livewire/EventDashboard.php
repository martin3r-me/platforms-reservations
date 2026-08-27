<?php

namespace Platform\Reservation\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Platform\Reservation\Livewire\Concerns\ChangesBookingStatus;
use Platform\Reservation\Livewire\Concerns\PrintsBookingReceipt;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\BookingItem;
use Platform\Reservation\Models\Event;

/**
 * VA-Dashboard: operativer Hub einer Veranstaltung. Bündelt Kennzahlen und
 * die Einstiege in die vollwertigen Views (Küche, Laufzettel) sowie die
 * druckbaren Ansichten.
 */
class EventDashboard extends Component
{
    use ChangesBookingStatus;
    use PrintsBookingReceipt;

    #[Locked]
    public int $eventId;

    /** Seed für die generative Ambient-Komposition – pro Seitenaufruf neu, stabil über Re-Renders. */
    #[Locked]
    public int $artSeed = 0;

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
        $this->artSeed = random_int(1, 999_999);
        $this->event; // Team-Scope prüfen (404 bei fremdem Team)
    }

    #[Computed]
    public function event(): Event
    {
        return Event::forTeam(Auth::user()?->current_team_id ?? 0)
            ->with(['slots', 'venue', 'imageFile.variants'])
            ->findOrFail($this->eventId);
    }

    /** Operative Kennzahlen (aktive Buchungen, ohne storniert/No-Show). */
    #[Computed]
    public function stats(): array
    {
        $base = Booking::where('event_id', $this->eventId)
            ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW]);

        $revenue = (float) BookingItem::query()
            ->join('reservation_bookings as b', 'b.id', '=', 'reservation_booking_items.booking_id')
            ->where('b.event_id', $this->eventId)
            ->whereNotIn('b.status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW])
            ->selectRaw('COALESCE(SUM(reservation_booking_items.unit_price * reservation_booking_items.quantity), 0) as s')
            ->value('s');

        return [
            'bookings' => (clone $base)->count(),
            'guests'   => (int) (clone $base)->sum('guest_count'),
            'revenue'  => $revenue,
            'pauses'   => $this->event->slots->count(),
        ];
    }

    /**
     * Aktive Buchungen des Termins, gruppiert nach Pause (Slot). Eine VA kann
     * mehrere Pausen haben; alle Slots erscheinen (auch leere), am Ende ggf.
     * eine „Ohne Pause"-Gruppe.
     *
     * @return \Illuminate\Support\Collection<int, array{label: string, bookings: \Illuminate\Support\Collection, count: int, guests: int, revenue: float}>
     */
    #[Computed]
    public function bookingsBySlot(): \Illuminate\Support\Collection
    {
        // No-Shows bleiben in der Liste, anders als in den Zahlen.
        //
        // Am Abend ist "die beiden kamen nicht" eine Information, kein Grund
        // zum Verschwinden. Vor allem aber: Wer hier von Hand auf No-Show
        // stellt, muss den Fehlgriff auch hier zurücknehmen koennen - eine
        // Zeile, die sich beim Klick in Luft aufloest, laesst genau das nicht
        // zu. Storniert bleibt draussen, das ist der Zustand vor der VA.
        $bookings = Booking::where('event_id', $this->eventId)
            ->where('status', '!=', Booking::STATUS_CANCELLED)
            ->with(['table', 'order'])
            ->withCount('items')
            ->orderBy('guest_name')
            ->get();

        $bySlot = $bookings->groupBy('slot_id');

        $groups = $this->event->slots
            ->sortBy(fn ($s) => (string) $s->time_start)
            ->map(fn ($slot) => $this->slotGroup($slot->displayLabel(), $bySlot->get($slot->id, collect())))
            ->values();

        $noSlot = $bookings->filter(fn ($b) => $b->slot_id === null);
        if ($noSlot->isNotEmpty()) {
            $groups->push($this->slotGroup('Ohne Pause', $noSlot));
        }

        return $groups;
    }

    /**
     * Eine Pausen-Gruppe: alle Zeilen, aber nur die zaehlenden im Kopf.
     *
     * Die Zahlen rechnen ohne No-Shows - wie die Kennzahlen oben, wie Kueche,
     * Laufzettel und Platzpruefung. Sonst stuenden im Kopf der Gruppe andere
     * Gaestezahlen als in der Kachel darueber.
     *
     * @param  \Illuminate\Support\Collection  $bookings
     */
    private function slotGroup(string $label, $bookings): array
    {
        $zaehlend = $bookings->where('status', '!=', Booking::STATUS_NO_SHOW);

        return [
            'label'    => $label,
            'bookings' => $bookings->values(),
            'count'    => $zaehlend->count(),
            'guests'   => (int) $zaehlend->sum('guest_count'),
            'revenue'  => (float) $zaehlend->sum('total_amount'),
        ];
    }

    /** Nach einem Statuswechsel: Liste und Kennzahlen neu rechnen. */
    protected function afterBookingStatusChanged(): void
    {
        unset($this->bookingsBySlot, $this->stats);
    }

    /**
     * Stapeldruck: alle aktiven Buchungen des Termins, je eine als eigener Bon.
     *
     * Reihenfolge wie in der Tabelle – nach Pause, darin nach Gastname. Der
     * Drucker arbeitet die Warteschlange nach Eingang ab, der Papierstapel
     * liegt also so da, wie die Liste auf dem Schirm steht.
     *
     * Team-Grenze: bookingsBySlot() hängt an $this->event, und das ist über
     * forTeam()->findOrFail() aufgelöst – ein fremder Termin endet in 404,
     * bevor hier irgendetwas gedruckt wird.
     *
     * @return \Illuminate\Support\Collection<int, Booking>
     */
    protected function batchPrintBookings(): \Illuminate\Support\Collection
    {
        // Ohne No-Shows: Die Liste zeigt sie, die Vorlage des Sammel-Bons
        // (Event::bonBookings) laesst sie aus. Stuenden sie hier drin, nennte
        // der Dialog eine Bon-Zahl, die der Drucker nicht liefert.
        return $this->bookingsBySlot
            ->flatMap(fn (array $group) => $group['bookings'])
            ->where('status', '!=', Booking::STATUS_NO_SHOW)
            ->values();
    }

    /**
     * Der Stapel geht als EIN Auftrag raus, nicht als einer je Buchung.
     *
     * Die Vorlage rendert alle Bons des Termins nacheinander, getrennt durch
     * Schnittbefehle – jede Buchung bekommt also weiterhin ihren eigenen
     * Beleg, nur eben aus einem Auftrag.
     *
     * Team-Grenze: $this->event ist über forTeam()->findOrFail() aufgelöst,
     * ein fremder Termin endet in 404, bevor etwas gedruckt wird.
     */
    protected function batchPrintable(): ?array
    {
        return [
            'printable' => $this->event,
            'template'  => 'reservation-event-bons',
        ];
    }

    /* --- Freigabe-Link für Veranstaltungsleiter (Küche + Laufzettel) --- */

    public bool $showShareModal = false;

    /** Nur direkt nach dem Erzeugen gefüllt – die PIN ist danach nicht mehr lesbar. */
    public ?string $freshPin = null;

    public function openShareModal(): void
    {
        $this->showShareModal = true;
        $this->freshPin       = null;
    }

    public function closeShareModal(): void
    {
        $this->showShareModal = false;
        // PIN nicht im Komponentenzustand liegen lassen.
        $this->freshPin = null;
    }

    /**
     * Link und PIN neu erzeugen. Ein vorhandener Link wird dadurch ungültig –
     * das ist zugleich der Weg, einen weitergegebenen Link zurückzuziehen.
     */
    public function issueShareLink(): void
    {
        $this->freshPin = $this->event->issueShareAccess();
        unset($this->event);
    }

    public function revokeShareLink(): void
    {
        $this->event->revokeShareAccess();
        $this->freshPin = null;
        unset($this->event);

        session()->flash('event_message', 'Der Freigabe-Link wurde zurückgezogen und ist sofort ungültig.');
    }

    /** Letzte Zugriffe auf den Link – vor allem gehäufte Fehlversuche. */
    #[Computed]
    public function shareAccesses(): \Illuminate\Support\Collection
    {
        return $this->event->shareAccesses()->limit(10)->get();
    }

    public function render()
    {
        return view('reservation::livewire.event-dashboard')
            ->layout('platform::layouts.app');
    }
}
