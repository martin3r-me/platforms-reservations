<?php

namespace Platform\Reservation\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Platform\Reservation\Exceptions\GuestOrderException;
use Platform\Reservation\Models\CheckoutSetting;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Models\MenuItem;
use Platform\Reservation\Models\PickupStation;
use Platform\Reservation\Models\Table;
use Platform\Reservation\Services\CartCalculator;
use Platform\Reservation\Services\GuestOrderService;
use Platform\Reservation\Services\PickupCapacityService;
use Platform\Reservation\Services\SeatAvailabilityService;

/**
 * Buchung von Hand anlegen – telefonisch, am Schalter, nachträglich.
 *
 * Hängt an einem TERMIN und einer PAUSE, nicht an einem frei gewählten Datum.
 * Vorher fragte dieser Weg nach Datum und Uhrzeit und schrieb weder event_id
 * noch event_slot_id. Damit fiel die Buchung durch jedes Raster, das darauf
 * aufsetzt: Küche und Laufzettel gruppieren über Termin und Pause, und die
 * Platzprüfung zählt über die Pause – eine so angelegte Buchung belegte also
 * keinen Platz, und der Shop konnte denselben Tisch ein zweites Mal verkaufen.
 *
 * Geschrieben wird über GuestOrderService::placeForStaff() und damit über
 * denselben Kern wie der Gast-Checkout: Artikel gegen die Verkaufsliste des
 * Termins, Tisch muss zum Termin gehören, Plätze geprüft, Preise aus der
 * Datenbank eingefroren, Bundles in Bestandteile aufgelöst.
 */
class BookingCreate extends Component
{
    // Aus Auth im mount abgeleitet – darf clientseitig nicht überschrieben
    // werden, sonst würde eine Buchung unter fremdem Team angelegt.
    #[Locked]
    public int $teamId;

    /** Schritt-Wizard: 1 = Termin/Pause/Tisch, 2 = Gast, 3 = Artikel, 4 = fertig */
    public int $step = 1;

    // Schritt 1
    public ?int $eventId = null;
    public ?int $slotId = null;
    public ?int $tableId = null;

    /**
     * Die gewählte Abholstation – die Alternative zum Tisch, nie zusätzlich.
     *
     * Zwei Felder statt eines Paars aus Art und Id: Die Ansicht fragt beide
     * getrennt ab, und ein zusammengesetzter Wert („station-7") müsste an jeder
     * Stelle wieder auseinandergenommen werden.
     */
    public ?int $stationId = null;
    public int $guestCount = 2;

    // Schritt 2. Vor- und Nachname getrennt wie im Shop – der Auftrag hält beide
    // Felder, und Order::customerName() setzt daraus den Anzeigenamen zusammen.
    public string $guestFirstName = '';
    public string $guestLastName = '';
    public string $guestEmail = '';
    public string $guestPhone = '';
    public string $notes = '';

    // Schritt 3: [menu_item_id => Menge]
    public array $selectedItems = [];

    /** Meldung, wenn das Speichern an einer Prüfung scheitert. */
    public string $bookingError = '';

    /**
     * Regeln je Schritt. Bewusst NICHT "rules" genannt: Unter diesem Namen ist
     * es Livewires eigene Regelquelle, und dann prüft es beim Tippen mit. Geprüft
     * werden soll aber erst beim Klick auf "Weiter".
     */
    protected function regelnFuerSchritt(): array
    {
        return match ($this->step) {
            // tableId ist hier NICHT mehr Pflicht: Der Ort kann auch eine
            // Abholstation sein. Dass genau eines von beidem gesetzt sein muss,
            // prueft nextStep() gleich darunter - eine Validierungsregel kann
            // "das eine ODER das andere" nicht ausdruecken, ohne dass die
            // Meldung an einem der beiden Felder haengt und dort falsch steht.
            1 => [
                'eventId'    => 'required|integer',
                'slotId'     => 'required|integer',
                'guestCount' => 'required|integer|min:1|max:100',
            ],
            2 => [
                'guestFirstName' => 'required|string|max:255',
                'guestLastName'  => 'required|string|max:255',
                'guestEmail'     => 'nullable|email|max:255',
                'guestPhone'     => 'nullable|string|max:30',
            ],
            default => [],
        };
    }

    protected function messages(): array
    {
        return [
            'eventId.required' => 'Bitte einen Termin wählen.',
            'slotId.required'  => 'Bitte eine Pause wählen.',
            'tableId.required'        => 'Bitte einen Tisch wählen.',
            'guestFirstName.required' => 'Bitte den Vornamen angeben.',
            'guestLastName.required'  => 'Bitte den Nachnamen angeben.',
        ];
    }

    public function mount(?int $tableId = null): void
    {
        $this->teamId  = Auth::user()->current_team_id;
        $this->tableId = $tableId;

        // Nur ein Termin in Frage: dann keine Wahl vorlegen, die keine ist.
        if ($this->events->count() === 1) {
            $this->eventId = $this->events->first()->id;
            $this->slotGewaehltWennEindeutig();
        }
    }

    /**
     * Buchbare Termine: ab heute und nicht abgesagt.
     *
     * Bewusst OHNE Bestellschluss-Prüfung – intern darf nachgebucht werden,
     * wenn der Shop längst zu ist. Das ist der Zweck dieses Wegs.
     */
    #[Computed]
    public function events(): \Illuminate\Support\Collection
    {
        return Event::query()
            ->where('team_id', $this->teamId)
            ->whereIn('status', [Event::STATUS_DRAFT, Event::STATUS_ANNOUNCED, Event::STATUS_PUBLISHED, Event::STATUS_CLOSED])
            ->upcoming()
            ->with(['slots', 'eventRooms.floorPlan.tables'])
            ->orderBy('date')
            ->get();
    }

    #[Computed]
    public function event(): ?Event
    {
        return $this->eventId ? $this->events->firstWhere('id', $this->eventId) : null;
    }

    #[Computed]
    public function slots(): \Illuminate\Support\Collection
    {
        return $this->event?->slots->sortBy(fn ($s) => (string) $s->time_start)->values() ?? collect();
    }

    #[Computed]
    public function slot(): mixed
    {
        return $this->slotId ? $this->slots->firstWhere('id', $this->slotId) : null;
    }

    /**
     * Tische der Räume DIESES Termins, mit freien Plätzen für die gewählte Pause.
     *
     * Vorher standen hier alle Tische des Teams – auch solche aus Räumen, die
     * dem Termin nicht zugeordnet sind. Der Service hätte sie abgelehnt, aber
     * erst nach dem Ausfüllen.
     *
     * @return \Illuminate\Support\Collection<int, array{table: Table, free: int, bookable: bool}>
     */
    #[Computed]
    public function tables(): \Illuminate\Support\Collection
    {
        $event = $this->event;
        $slot  = $this->slot;

        if (! $event || ! $slot) {
            return collect();
        }

        $seats    = app(SeatAvailabilityService::class);
        $checkout = CheckoutSetting::forTeam($this->teamId);

        return $event->eventRooms
            ->flatMap(fn ($room) => $room->floorPlan?->tables->where('is_active', true) ?? collect())
            ->reject(fn (Table $t) => $event->isTableDisabled($t->id))
            ->sortBy('label')
            ->map(fn (Table $t) => [
                'table'    => $t,
                'free'     => $seats->remainingSeats($t, $slot),
                'bookable' => $seats->canSeat(
                    $t,
                    $slot,
                    $this->guestCount,
                    $checkout->softTableCapacity(),
                    $checkout->maxGroupEmptyTable(),
                ),
            ])
            ->values();
    }

    /**
     * Obergrenze für die Personenzahl – dieselbe Zahl, die der Shop zeigt und
     * gegen die die API prüft: die eigene Grenze des Termins, sonst die
     * Vorgabe des Teams.
     *
     * Vorher rechnete diese Stelle sich ihren eigenen Wert aus der weichen
     * Tisch-Kapazität und, falls die fehlte, aus dem größten Tisch. Das war
     * eine dritte Antwort auf eine Frage, die es nur einmal gibt.
     *
     * Verbindlich bleibt die Platzprüfung beim Speichern – die kennt auch die
     * schon belegten Plätze.
     */
    /**
     * Die Abholstationen dieses Termins, in dieser Pause.
     *
     * Zeigt auch die, die voll sind – „belegt" ist eine Information, ein
     * Verschwinden nicht. Wer nicht buchbar ist, steht mit Grund da.
     *
     * @return \Illuminate\Support\Collection<int, array{station: PickupStation, frei: ?int, buchbar: bool}>
     */
    #[Computed]
    public function stations(): \Illuminate\Support\Collection
    {
        $event = $this->event;
        $slot  = $this->slot;

        if (! $event || ! $slot) {
            return collect();
        }

        $kapazitaet = app(PickupCapacityService::class);

        return $event->eventStations()
            ->with(['station', 'slots'])
            ->get()
            ->filter(fn ($z) => $z->station?->is_active && $z->offenIn($slot->id))
            ->map(fn ($z) => [
                'station' => $z->station,
                'frei'    => $kapazitaet->frei($z, $slot),
                'buchbar' => $kapazitaet->passt($z, $slot, $this->guestCount),
            ])
            ->values();
    }

    #[Computed]
    public function maxGuests(): int
    {
        return $this->event
            ? $this->event->maxGuestCount()
            : CheckoutSetting::forTeam($this->teamId)->maxGuestCount();
    }

    /**
     * Artikel aus der Verkaufsliste DES TERMINS.
     *
     * Vorher war es das ganze Team-Menü – damit ließ sich buchen, was es an
     * dem Abend nicht gibt. Dieselbe Quelle wie im Gast-Checkout.
     */
    #[Computed]
    public function availableMenuItems(): \Illuminate\Support\Collection
    {
        $event = $this->event;

        if (! $event) {
            return collect();
        }

        // allowedItems liefert die Bestandteile schon mitgeladen; die Kategorie
        // braucht nur die Anzeige.
        return app(CartCalculator::class)->allowedItems($event)
            ->load('category')
            // Ein Bundle ohne Bestandteile zerfällt in null Positionen – die
            // Buchung hätte dann eine Zeile weniger, ohne dass es auffällt.
            ->filter(fn (MenuItem $item) => ! $item->isBundle() || $item->components->isNotEmpty())
            ->sortBy('sort_order')
            ->values();
    }

    /**
     * Die Auswahl als Warenkorb-Positionen.
     *
     * Über den CartCalculator: dort liegen Mengenbegrenzung und die Auflösung
     * von Bundles in ihre Bestandteile.
     */
    #[Computed]
    public function bookingLines(): \Illuminate\Support\Collection
    {
        return app(CartCalculator::class)->linesFrom(
            $this->selectedItems,
            $this->availableMenuItems->keyBy('id'),
        );
    }

    #[Computed]
    public function selectedTable(): ?Table
    {
        return $this->tableId
            ? $this->tables->firstWhere(fn ($z) => $z['table']->id === $this->tableId)['table'] ?? null
            : null;
    }

    /** Der gewählte Ort in Worten – für die Übersicht im letzten Schritt. */
    public function ortLabel(): ?string
    {
        if ($this->stationId) {
            return $this->stations->firstWhere(fn ($z) => $z['station']->id === $this->stationId)['station']->name ?? null;
        }

        return $this->selectedTable?->label;
    }

    /** Tisch und Station schließen einander aus – hier, nicht erst beim Speichern. */
    public function chooseTable(int $id): void
    {
        $this->tableId   = $id;
        $this->stationId = null;
    }

    public function chooseStation(int $id): void
    {
        $this->stationId = $id;
        $this->tableId   = null;
    }

    #[Computed]
    public function orderTotal(): float
    {
        return app(CartCalculator::class)->total($this->bookingLines);
    }

    /** Termin gewechselt: Pause, Tisch und Auswahl gehören zum alten. */
    public function updatedEventId(): void
    {
        $this->slotId        = null;
        $this->tableId       = null;
        $this->stationId = null;
        $this->selectedItems = [];
        $this->bookingError  = '';
        $this->resetValidation();

        unset($this->event, $this->slots, $this->slot, $this->tables, $this->availableMenuItems);

        $this->slotGewaehltWennEindeutig();
    }

    /** Pause gewechselt: Der Tisch kann in der neuen Pause voll sein. */
    public function updatedSlotId(): void
    {
        $this->tableId      = null;
        $this->bookingError = '';
        $this->resetValidation();

        unset($this->slot, $this->tables);
    }

    public function updatedGuestCount(): void
    {
        $this->resetValidation();

        unset($this->tables);
    }

    /** Eine einzige Pause ist keine Wahl. */
    protected function slotGewaehltWennEindeutig(): void
    {
        if ($this->slots->count() === 1) {
            $this->slotId = $this->slots->first()->id;
        }
    }

    public function nextStep(): void
    {
        $regeln = $this->regelnFuerSchritt();

        if (! empty($regeln)) {
            $this->validate($regeln);
        }

        if ($this->step === 1 && ! $this->tableId && ! $this->stationId) {
            $this->addError('tableId', 'Bitte einen Tisch oder eine Abholstation wählen.');

            return;
        }

        $this->step++;
    }

    public function prevStep(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    /*
     * incrementItem/decrementItem sind entfallen: Die Mengen führt der Browser
     * und schickt die ganze Auswahl gebündelt als selectedItems. Verbindlich
     * bleibt, was CartCalculator daraus macht – Artikel außerhalb der
     * Verkaufsliste und übergroße Mengen fallen dort weg, nicht hier.
     */

    /**
     * Speichern über denselben Kern wie der Gast-Checkout.
     *
     * Die Prüfungen dort sind autoritativ; was die Oberfläche vorher schon
     * einschränkt, ist nur Bequemlichkeit. Scheitert eine, steht sie als
     * Meldung im Formular statt als Ausnahme im Log.
     */
    public function confirm(): void
    {
        $this->bookingError = '';

        $this->validate([
            'eventId'        => 'required|integer',
            'slotId'         => 'required|integer',
            'guestFirstName' => 'required|string|max:255',
            'guestLastName'  => 'required|string|max:255',
            'guestCount'     => 'required|integer|min:1|max:100',
        ]);

        $event = $this->event;

        if (! $event) {
            $this->bookingError = 'Der Termin ist nicht mehr verfügbar.';

            return;
        }

        // Genau ein Ort. Die Regel gilt auch im Kern, aber dort ist sie eine
        // Ausnahme fuer Programmfehler - hier ist es eine Eingabe.
        if (! $this->tableId && ! $this->stationId) {
            $this->bookingError = 'Bitte einen Tisch oder eine Abholstation wählen.';

            return;
        }

        if ($this->selectedItems === []) {
            $this->bookingError = 'Bitte mindestens einen Artikel wählen.';

            return;
        }

        try {
            app(GuestOrderService::class)->placeForStaff(
                $event,
                [
                    'first_name' => $this->guestFirstName,
                    'last_name'  => $this->guestLastName,
                    'email'      => $this->guestEmail ?: null,
                    'phone'      => $this->guestPhone ?: null,
                    'count'      => $this->guestCount,
                    'notes'      => $this->notes ?: null,
                ],
                [array_filter([
                    'slot_id'    => $this->slotId,
                    'table_id'   => $this->tableId,
                    'station_id' => $this->stationId,
                    'items'      => $this->selectedItems,
                ], fn ($wert) => $wert !== null)],
            );
        } catch (GuestOrderException $e) {
            $this->bookingError = $e->getMessage();

            return;
        }

        $this->step = 4;
    }

    public function render()
    {
        return view('reservation::livewire.booking-create')
            ->layout('platform::layouts.app');
    }
}
