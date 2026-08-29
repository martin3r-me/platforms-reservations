<?php

namespace Platform\Reservation\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Platform\Reservation\Livewire\Concerns\ChangesBookingStatus;
use Platform\Reservation\Livewire\Concerns\PrintsBookingReceipt;
use Platform\Reservation\Livewire\Concerns\ShowsBookingDetail;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\BookingItem;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Models\EventSlot;
use Platform\Reservation\Services\RoomReleaseService;
use Platform\Reservation\Services\SeatAvailabilityService;

/**
 * VA-Dashboard: operativer Hub einer Veranstaltung. Bündelt Kennzahlen und
 * die Einstiege in die vollwertigen Views (Küche, Laufzettel) sowie die
 * druckbaren Ansichten.
 */
class EventDashboard extends Component
{
    use ChangesBookingStatus;
    use PrintsBookingReceipt;
    use ShowsBookingDetail;

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

        // Das Feld heisst event_slot_id. Hier stand einmal slot_id - das gibt es
        // am Model nicht, und Eloquent liefert fuer unbekannte Felder still
        // null statt eines Fehlers. Ergebnis: jede Pause leer, alles unter
        // "Ohne Pause". Faellt nur auf, wenn man die Zahlen im Gruppenkopf mit
        // den Kacheln darueber vergleicht.
        $bySlot = $bookings->groupBy('event_slot_id');

        $groups = $this->event->slots
            ->sortBy(fn ($s) => (string) $s->time_start)
            ->map(fn ($slot) => $this->slotGroup($slot->displayLabel(), $bySlot->get($slot->id, collect()), $slot->id))
            ->values();

        // Der Rest-Topf faengt alles auf, was keiner Pause DIESES Termins
        // zugeordnet ist - nicht nur die leeren. Beim Loeschen einer Pause
        // setzt der Fremdschluessel das Feld zwar auf null (nullOnDelete), es
        // bleibt also nichts verwaist zurueck; die Liste soll aber auch bei
        // krummen Daten vollstaendig bleiben. Keine Zeile darf lautlos
        // herausfallen, am Abend sitzen die Gaeste im Saal.
        //
        // contains() vergleicht bewusst locker: Ob die Spalte als Zahl oder
        // als Text aus der Datenbank kommt, haengt am Treiber.
        $slotIds = $this->event->slots->pluck('id');

        $ohnePause = $bookings->reject(fn ($b) => $slotIds->contains($b->event_slot_id));
        if ($ohnePause->isNotEmpty()) {
            $groups->push($this->slotGroup('Ohne Pause', $ohnePause));
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
    private function slotGroup(string $label, $bookings, ?int $slotId = null): array
    {
        $zaehlend = $bookings->where('status', '!=', Booking::STATUS_NO_SHOW);

        return [
            'slot_id'  => $slotId,
            'label'    => $label,
            'bookings' => $bookings->values(),
            'count'    => $zaehlend->count(),
            'guests'   => (int) $zaehlend->sum('guest_count'),
            'revenue'  => (float) $zaehlend->sum('total_amount'),
        ];
    }

    /**
     * Auslastung je Pause: Raum und Tische.
     *
     * Gerechnet wird pro Pause, nicht pro Abend. Die Plaetze zaehlen im
     * ganzen Modul je Slot - zur naechsten Pause ist der Raum wieder leer.
     * Eine terminweite Auslastungszahl gaebe es also gar nicht, sie waere
     * schlicht erfunden.
     *
     * Gezaehlt wird ueber SeatAvailabilityService, also mit derselben Regel,
     * nach der der Shop freie Plaetze anbietet (ohne Stornos, ohne No-Shows).
     * Eine zweite Rechnung an dieser Stelle liefe frueher oder spaeter
     * auseinander, und dann stuende im Backoffice etwas anderes als beim Gast.
     *
     * Nenner sind die tatsaechlich buchbaren Plaetze: aktive Tische ohne die
     * fuer diesen Termin gesperrten. capacity_override des Raums bleibt
     * bewusst aussen vor - er gehoert zur Freigabelogik, waere hier aber
     * nicht mehr die Summe dessen, was daneben als Tische steht.
     *
     * @return array<int, array<int, array<string, mixed>>>  [slot_id => Raeume]
     */
    #[Computed]
    public function auslastung(): array
    {
        $raeume = $this->event->eventRooms()->with('floorPlan.tables')->get();

        if ($raeume->isEmpty() || $this->event->slots->isEmpty()) {
            return [];
        }

        $seats  = app(SeatAvailabilityService::class);
        $freigabe = app(RoomReleaseService::class);
        $result = [];

        foreach ($this->event->slots as $slot) {
            // Auch die Freigabe haengt an der Pause: Raum 2 kann in der ersten
            // Pause laengst offen sein und in der zweiten noch zu.
            $offeneIds = $freigabe->openRooms($this->event, $slot)->pluck('id')->all();

            $zeilen = [];

            foreach ($raeume as $i => $raum) {
                $zeile = $this->raumAuslastung($raum, $slot, $seats);

                if ($zeile === null) {
                    continue;
                }

                $zeile['open']    = in_array($raum->id, $offeneIds, true);
                $zeile['hinweis'] = $this->freigabeHinweis($raum, $raeume->get($i - 1), $zeile['open']);

                $zeilen[] = $zeile;
            }

            $result[$slot->id] = $zeilen;
        }

        return $result;
    }

    /**
     * Warum ein Raum zu ist - oder warum er entgegen der Reihenfolge offen ist.
     *
     * Ohne diesen Satz stuende ein geschlossener Raum bei 0 Prozent da, und
     * niemand wuesste, ob dort nichts gebucht wurde oder ob dort gar nichts
     * gebucht werden konnte. Das sind zwei sehr verschiedene Abende.
     *
     * Gibt null zurueck, wenn es nichts zu sagen gibt - der Normalfall bei
     * parallelen Raeumen ohne Handeingriff.
     */
    private function freigabeHinweis($raum, $vorgaenger, bool $offen): ?string
    {
        if (! $offen) {
            if ($raum->is_open_override === false) {
                return 'von Hand geschlossen';
            }

            return $vorgaenger
                ? 'öffnet, sobald ' . ($vorgaenger->floorPlan?->name ?? 'der Raum davor')
                    . ' zu ' . (int) $vorgaenger->fill_threshold_percent . " % gefüllt ist"
                : 'geschlossen';
        }

        // Offen ist der Normalfall und braucht keine Erklaerung. Ausser jemand
        // hat von Hand aufgemacht - dann soll klar sein, dass hier nicht die
        // Reihenfolge greift.
        return $raum->is_open_override === true ? 'von Hand geöffnet' : null;
    }

    /** Ein Raum in einer Pause: Plaetze, Prozent und die Tische einzeln. */
    private function raumAuslastung($raum, EventSlot $slot, SeatAvailabilityService $seats): ?array
    {
        $plan = $raum->floorPlan;

        if (! $plan) {
            return null;
        }

        $tische = $plan->tables->where('is_active', true)->sortBy('label', SORT_NATURAL);

        if ($tische->isEmpty()) {
            return null;
        }

        $belegtJeTisch = $seats->bookedSeatsByTable($plan, $slot);

        $plaetze = 0;
        $belegt  = 0;
        $zeilen  = [];

        foreach ($tische as $tisch) {
            $gesperrt = $this->event->isTableDisabled($tisch->id);
            $kapa     = (int) $tisch->capacity;
            $b        = (int) $belegtJeTisch->get($tisch->id, 0);

            if (! $gesperrt) {
                $plaetze += $kapa;
                $belegt  += $b;
            }

            $zeilen[] = [
                'label'    => $tisch->label,
                'capacity' => $kapa,
                'booked'   => $b,
                // Gesperrt zuerst: Ein gesperrter Tisch ist nicht "frei", auch
                // wenn niemand daran sitzt - da soll heute Abend keiner hin.
                'status'   => $gesperrt ? 'gesperrt' : ($b <= 0 ? 'frei' : ($b >= $kapa ? 'voll' : 'teilweise')),
            ];
        }

        return [
            'name'    => $plan->name,
            'seats'   => $plaetze,
            'booked'  => $belegt,
            'percent' => $plaetze > 0 ? (int) round($belegt / $plaetze * 100) : 0,
            'free'    => max(0, $plaetze - $belegt),
            'tables'  => $zeilen,
        ];
    }

    /** Nach einem Statuswechsel: Liste, Kennzahlen und der Abschluss-Zaehler. */
    protected function afterBookingStatusChanged(): void
    {
        // Auslastung muss mit: Ein No-Show gibt den Platz wieder frei.
        unset($this->bookingsBySlot, $this->stats, $this->offeneBestaetigte, $this->auslastung);
    }

    /* --- Abend abschliessen --- */

    public bool $showCloseEventModal = false;

    /**
     * Wie viele Buchungen ein Abschluss noch betrifft.
     *
     * Nur die bestaetigten. Ausstehende bleiben liegen: Das ist bestellt und
     * nicht bezahlt, und "abgeschlossen" wuerde behaupten, der Vorgang sei
     * erledigt. No-Shows bleiben ebenfalls, wie sie sind - sie sind das
     * Ergebnis des Abends, nicht ein offener Punkt.
     */
    #[Computed]
    public function offeneBestaetigte(): int
    {
        return Booking::where('event_id', $this->eventId)
            ->where('status', Booking::STATUS_CONFIRMED)
            ->count();
    }

    /** Ausstehende des Termins - im Dialog genannt, damit klar ist, was liegen bleibt. */
    #[Computed]
    public function offeneAusstehende(): int
    {
        return Booking::where('event_id', $this->eventId)
            ->where('status', Booking::STATUS_PENDING)
            ->count();
    }

    public function openCloseEventModal(): void
    {
        $this->showCloseEventModal = true;

        unset($this->offeneBestaetigte, $this->offeneAusstehende);
    }

    public function closeCloseEventModal(): void
    {
        $this->showCloseEventModal = false;
    }

    /**
     * Alle bestaetigten Buchungen des Termins auf "abgeschlossen".
     *
     * Der Griff am Ende des Abends: Die Fehlenden sind einzeln als No-Show
     * markiert, der Rest war da. Bewusst als ein Schritt und nicht als
     * naechtlicher Automatismus - "abgeschlossen" soll heissen, dass jemand
     * den Abend durchgesehen hat, nicht bloss, dass das Datum vorbei ist.
     *
     * Team-Grenze doppelt: $this->event laeuft ueber forTeam()->findOrFail()
     * (404 bei fremdem Termin), und auf Booking liegt der globale Team-Scope
     * aus BelongsToTeam.
     *
     * Massenupdate statt Schleife: "abgeschlossen" loest keinen Bon-Druck aus
     * (Booking::booted druckt nur bei "bestaetigt"), es geht hier also kein
     * Modell-Ereignis verloren, auf das etwas hoert.
     */
    public function confirmCloseEvent(): void
    {
        $this->event; // Team-Scope pruefen, bevor irgendetwas geschrieben wird

        $anzahl = Booking::where('event_id', $this->eventId)
            ->where('status', Booking::STATUS_CONFIRMED)
            ->update(['status' => Booking::STATUS_COMPLETED]);

        $this->showCloseEventModal = false;
        $this->afterBookingStatusChanged();
        unset($this->offeneAusstehende);

        session()->flash('booking_message', $anzahl === 0
            ? 'Es war keine bestätigte Buchung mehr offen.'
            : ($anzahl === 1
                ? 'Eine Buchung wurde auf Abgeschlossen gesetzt.'
                : $anzahl . ' Buchungen wurden auf Abgeschlossen gesetzt.'));
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
