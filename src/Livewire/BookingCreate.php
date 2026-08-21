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
use Platform\Reservation\Models\Table;
use Platform\Reservation\Services\CartCalculator;
use Platform\Reservation\Services\GuestOrderService;
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
    public int $guestCount = 2;

    // Schritt 2
    public string $guestName = '';
    public string $guestEmail = '';
    public string $guestPhone = '';
    public string $notes = '';

    // Schritt 3: [menu_item_id => Menge]
    public array $selectedItems = [];

    /** Meldung, wenn das Speichern an einer Prüfung scheitert. */
    public string $bookingError = '';

    protected function rules(): array
    {
        return match ($this->step) {
            1 => [
                'eventId'    => 'required|integer',
                'slotId'     => 'required|integer',
                'tableId'    => 'required|integer',
                'guestCount' => 'required|integer|min:1|max:100',
            ],
            2 => [
                'guestName'  => 'required|string|max:255',
                'guestEmail' => 'nullable|email|max:255',
                'guestPhone' => 'nullable|string|max:30',
            ],
            default => [],
        };
    }

    protected function messages(): array
    {
        return [
            'eventId.required' => 'Bitte einen Termin wählen.',
            'slotId.required'  => 'Bitte eine Pause wählen.',
            'tableId.required' => 'Bitte einen Tisch wählen.',
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
            ->whereIn('status', [Event::STATUS_DRAFT, Event::STATUS_PUBLISHED, Event::STATUS_CLOSED])
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
     * Obergrenze für die Personenzahl – als Hinweis, nicht als Prüfung.
     *
     * Dieselbe Zahl, die der Shop zeigt: die größte Gruppe, die auf einen
     * leeren Tisch darf. Ist sie nicht gesetzt, der größte Tisch des Termins.
     *
     * Verbindlich bleibt die Platzprüfung beim Speichern – die kennt auch die
     * schon belegten Plätze.
     */
    #[Computed]
    public function maxGuests(): int
    {
        $grenze = CheckoutSetting::forTeam($this->teamId)->maxGroupEmptyTable();

        if ($grenze) {
            return (int) $grenze;
        }

        $groesster = $this->event?->eventRooms
            ->flatMap(fn ($room) => $room->floorPlan?->tables ?? collect())
            ->max('capacity');

        return (int) ($groesster ?: 20);
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
        $this->selectedItems = [];
        $this->bookingError  = '';

        unset($this->event, $this->slots, $this->slot, $this->tables, $this->availableMenuItems);

        $this->slotGewaehltWennEindeutig();
    }

    /** Pause gewechselt: Der Tisch kann in der neuen Pause voll sein. */
    public function updatedSlotId(): void
    {
        $this->tableId      = null;
        $this->bookingError = '';

        unset($this->slot, $this->tables);
    }

    public function updatedGuestCount(): void
    {
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
        $rules = $this->rules();

        if (! empty($rules)) {
            $this->validate($rules);
        }

        $this->step++;
    }

    public function prevStep(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function incrementItem(int $itemId): void
    {
        $this->selectedItems[$itemId] = ($this->selectedItems[$itemId] ?? 0) + 1;
    }

    public function decrementItem(int $itemId): void
    {
        $current = $this->selectedItems[$itemId] ?? 0;

        if ($current <= 1) {
            unset($this->selectedItems[$itemId]);
        } else {
            $this->selectedItems[$itemId] = $current - 1;
        }
    }

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
            'eventId'    => 'required|integer',
            'slotId'     => 'required|integer',
            'tableId'    => 'required|integer',
            'guestName'  => 'required|string|max:255',
            'guestCount' => 'required|integer|min:1|max:100',
        ]);

        $event = $this->event;

        if (! $event) {
            $this->bookingError = 'Der Termin ist nicht mehr verfügbar.';

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
                    'first_name' => $this->guestName,
                    'last_name'  => '',
                    'email'      => $this->guestEmail ?: null,
                    'phone'      => $this->guestPhone ?: null,
                    'count'      => $this->guestCount,
                    'notes'      => $this->notes ?: null,
                ],
                [[
                    'slot_id'  => $this->slotId,
                    'table_id' => $this->tableId,
                    'items'    => $this->selectedItems,
                ]],
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
