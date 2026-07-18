<?php

namespace Platform\Reservation\Livewire\Guest;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\CheckoutSetting;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Models\EventRoom;
use Platform\Reservation\Models\EventSlot;
use Platform\Reservation\Models\MenuItem;
use Platform\Reservation\Models\Order;
use Platform\Reservation\Models\Table;
use Platform\Reservation\Services\CartCalculator;
use Platform\Reservation\Services\MolliePaymentService;
use Platform\Reservation\Services\RoomReleaseService;
use Platform\Reservation\Services\SeatAvailabilityService;

/**
 * Öffentlicher Buchungs-Wizard für einen Termin (ohne Auth).
 *
 * Multi-Slot: der Gast bestellt je Pause eigene Produkte und wählt je Pause
 * Raum/Tisch; alles läuft in EINER Order mit einer Zahlung zusammen. Eine Pause
 * ohne Produkte wird nicht gebucht.
 *
 * Steps: 1 Gastdaten → 2 Produkte je Pause → 3 Sitzplatz je Pause → 4 Checkout
 * → 5 Bestätigung. Team-Kontext kommt ausschließlich aus dem Event.
 *
 * In-App-Klickdummy (auch für Tests gegen den Mollie-Test-Key); die
 * produktive Gast-UX zieht später ins eigene Frontend über die Gast-API.
 */
class CheckoutWizard extends Component
{
    #[Locked]
    public string $uuid = '';

    public int $step = 1;

    // Step 1: Gastdaten (gelten für die ganze Bestellung)
    public string $guestName = '';
    public string $guestEmail = '';
    public string $guestPhone = '';
    public int $guestCount = 2;
    public string $notes = '';

    // Step 2: Produkte je Pause – slotId => (menu_item_id => Menge)
    /** @var array<int, array<int, int>> */
    public array $carts = [];
    public bool $filterVegetarian = false;
    public bool $filterVegan = false;

    // Welche Pause wird gerade bearbeitet (Step 2/3)?
    public ?int $currentSlotId = null;

    // Step 3: Sitzplatz je Pause
    /** @var array<int, int> slotId => EventRoom-ID */
    public array $slotRooms = [];
    /** @var array<int, int> slotId => Table-ID */
    public array $slotTables = [];

    // Step 4: Checkout (Zahlungsart wählt der Gast bei Mollie)
    public bool $ageConfirmed = false;
    public bool $legalAccepted = false;

    // Step 5: Bestätigung
    #[Locked]
    public ?string $orderUuid = null;

    public function mount(string $uuid): void
    {
        $this->uuid = $uuid;
        $this->currentSlotId = $this->event->slots->first()?->id;
    }

    #[Computed]
    public function event(): Event
    {
        return Event::where('uuid', $this->uuid)
            ->published()
            ->with(['venue', 'slots', 'eventRooms.floorPlan'])
            ->firstOrFail();
    }

    /** Team-Checkout-Einstellungen (u. a. Anmeldefeld-Modi, #520/#521). */
    #[Computed]
    public function checkoutSettings(): CheckoutSetting
    {
        return CheckoutSetting::forTeam((int) $this->event->team_id);
    }

    /** Artikel der Event-Verkaufsliste (Gast-sichtbar), optional gefiltert. */
    #[Computed]
    public function menuItems(): Collection
    {
        $salesList = $this->event->resolveSalesList();

        if (!$salesList) {
            return collect();
        }

        return $salesList->guestVisibleItems()
            ->when($this->filterVegan, fn ($items) => $items->where('is_vegan', true))
            ->when($this->filterVegetarian && !$this->filterVegan, fn ($items) => $items->where('is_vegetarian', true))
            ->groupBy(fn (MenuItem $item) => $item->category?->name ?? 'Sonstiges');
    }

    /** Legende: nur Codes, die in der Liste tatsächlich vorkommen. */
    #[Computed]
    public function legend(): array
    {
        $items = $this->event->resolveSalesList()?->guestVisibleItems() ?? collect();

        return [
            'allergens' => $items->flatMap->allergens->unique('id')->sortBy('code')->values(),
            'additives' => $items->flatMap->additives->unique('id')->sortBy(fn ($a) => (int) $a->code)->values(),
        ];
    }

    /** Autoritative Warenkorb-Kalkulation (auch von der künftigen Gast-API genutzt). */
    protected function calc(): CartCalculator
    {
        return app(CartCalculator::class);
    }

    /**
     * Gültige Positionen JE Pause (nur Slots mit Produkten), keyed by slotId.
     * @return Collection<int, Collection<int, array{item: MenuItem, quantity: int, total: float}>>
     */
    #[Computed]
    public function slotCarts(): Collection
    {
        return collect($this->event->slots)
            ->mapWithKeys(fn (EventSlot $slot) => [
                $slot->id => $this->calc()->lines($this->carts[$slot->id] ?? [], $this->event),
            ])
            ->filter(fn (Collection $lines) => $lines->isNotEmpty());
    }

    /** Positionen der aktuell bearbeiteten Pause (für die Produkt-Ansicht). */
    #[Computed]
    public function cartItems(): Collection
    {
        return $this->calc()->lines($this->carts[$this->currentSlotId] ?? [], $this->event);
    }

    /** Alle Positionen aller bestellten Pausen zusammengeführt. */
    protected function allLines(): Collection
    {
        return $this->slotCarts->flatten(1);
    }

    #[Computed]
    public function orderTotal(): float
    {
        return $this->calc()->total($this->allLines());
    }

    /** Summen je MwSt-Satz über ALLE Pausen (gemischte MwSt). */
    #[Computed]
    public function totalsByTaxRate(): Collection
    {
        return $this->calc()->totalsByTaxRate($this->allLines());
    }

    #[Computed]
    public function requiresAgeCheck(): bool
    {
        return $this->calc()->containsAgeRestricted($this->allLines());
    }

    #[Computed]
    public function currentSlot(): ?EventSlot
    {
        return $this->currentSlotId
            ? $this->event->slots->firstWhere('id', $this->currentSlotId)
            : null;
    }

    /** @return Collection<int, EventRoom> */
    #[Computed]
    public function openRooms(): Collection
    {
        if (!$this->currentSlot) {
            return collect();
        }

        return app(RoomReleaseService::class)->openRooms($this->event, $this->currentSlot);
    }

    #[Computed]
    public function selectedRoom(): ?EventRoom
    {
        $roomId = $this->slotRooms[$this->currentSlotId] ?? null;

        return $roomId ? $this->openRooms->firstWhere('id', $roomId) : null;
    }

    /** Tisch-Status für das Tischplan-Partial (platzgenau je Pause). */
    #[Computed]
    public function tableStates(): array
    {
        $room = $this->selectedRoom;
        $slot = $this->currentSlot;

        if (!$room || !$slot) {
            return [];
        }

        $seats           = app(SeatAvailabilityService::class);
        $bookedByTable   = $seats->bookedSeatsByTable($room->floorPlan, $slot);
        $event           = $this->event;
        $selectedTableId = $this->slotTables[$this->currentSlotId] ?? null;
        $soft            = $this->checkoutSettings->softTableCapacity();

        return $room->floorPlan->tables()->where('is_active', true)->get()
            ->map(function (Table $table) use ($bookedByTable, $seats, $event, $selectedTableId, $soft) {
                $booked    = $bookedByTable->get($table->id, 0);
                $remaining = max(0, $table->capacity - $booked);

                // Pro Termin gesperrte Tische sind nicht buchbar
                if ($event->isTableDisabled($table->id)) {
                    return ['table' => $table, 'state' => 'full', 'remaining' => 0, 'bookable' => false];
                }

                // Gruppe passt in freie Plätze – oder (weiche Kapazität) leerer Tisch für Großgruppe.
                $fits = $this->guestCount <= $remaining || ($soft && $booked === 0);

                $state = $selectedTableId === $table->id
                    ? 'selected'
                    : (! $fits
                        ? 'full' // nicht wählbar für diese Gruppe
                        : $seats->tableStatus($table, $booked));

                return ['table' => $table, 'state' => $state, 'remaining' => $remaining, 'bookable' => $fits];
            })
            ->all();
    }

    /** Gewählte Tische je bestellter Pause (für die Zusammenfassung), keyed by Table-ID. */
    #[Computed]
    public function chosenTables(): Collection
    {
        $ids = collect($this->slotTables)->filter()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Table::whereIn('id', $ids)->get()->keyBy('id');
    }

    /** Konfigurierbare Checkout-Texte des Teams (mit Defaults). */
    #[Computed]
    public function checkoutTexts(): \Platform\Reservation\Models\CheckoutSetting
    {
        return \Platform\Reservation\Models\CheckoutSetting::forTeam($this->event->team_id);
    }

    /**
     * Läuft die Zahlung über Mollie (Hosted Checkout)? Dann wählt der Gast
     * die Zahlungsart auf der Mollie-Seite – nicht bei uns.
     */
    #[Computed]
    public function payViaMollie(): bool
    {
        return $this->orderTotal > 0
            && app(MolliePaymentService::class)->isEnabledForTeam($this->event->team_id);
    }

    // Telefon optional, aber Formatprüfung: Ziffern, +, /, -, Klammern, Punkt, Leerzeichen.
    private const PHONE_REGEX = '/^\+?[0-9 .\/()\-]{6,30}$/';

    /**
     * Validierungsregeln für die Gastdaten.
     *
     * @param bool $dns  E-Mail zusätzlich per DNS prüfen (Domain existiert /
     *                   nimmt Mail an). In Step 1 an, im finalen confirm()-Guard
     *                   aus, um keinen zweiten (langsamen) DNS-Lookup zu machen.
     */
    protected function guestRules(bool $dns = true): array
    {
        // #520/#521: E-Mail/Telefon/Notiz sind per Team-Setting Pflicht/optional/aus.
        $settings = $this->checkoutSettings;

        return [
            'guestName'  => ['required', 'string', 'max:255'],
            'guestEmail' => $settings->guestFieldRule('email', ['email:rfc' . ($dns ? ',dns' : ''), 'max:255']),
            'guestPhone' => $settings->guestFieldRule('phone', ['string', 'max:30', 'regex:' . self::PHONE_REGEX]),
            'guestCount' => ['required', 'integer', 'min:1', 'max:20'],
            'notes'      => $settings->guestFieldRule('notes', ['string', 'max:2000']),
        ];
    }

    /** Ausgeblendete Felder auf Leerwert setzen, damit kein Streuwert einfließt. */
    protected function normalizeHiddenGuestFields(): void
    {
        $settings = $this->checkoutSettings;

        if ($settings->fieldIsHidden('email')) {
            $this->guestEmail = '';
        }
        if ($settings->fieldIsHidden('phone')) {
            $this->guestPhone = '';
        }
        if ($settings->fieldIsHidden('notes')) {
            $this->notes = '';
        }
    }

    protected function guestMessages(): array
    {
        return [
            'guestName.required'  => 'Bitte geben Sie Ihren Namen an.',
            'guestEmail.required' => 'Bitte geben Sie Ihre E-Mail-Adresse an.',
            'guestEmail.email'    => 'Bitte geben Sie eine gültige, existierende E-Mail-Adresse an.',
            'guestPhone.regex'    => 'Bitte geben Sie eine gültige Telefonnummer an (Ziffern, +, /, -, Klammern).',
            'guestCount.required' => 'Bitte geben Sie die Personenzahl an.',
        ];
    }

    // ── Navigation ───────────────────────────────────────────────

    public function nextStep(): void
    {
        if ($this->step === 1) {
            $this->normalizeHiddenGuestFields();
            $this->validate($this->guestRules(dns: true), $this->guestMessages());
        }

        if ($this->step === 2 && $this->slotCarts->isEmpty()) {
            $this->addError('carts', 'Bitte wählen Sie für mindestens eine Pause ein Produkt.');
            return;
        }

        if ($this->step === 3) {
            // Jede bestellte Pause braucht einen Tisch.
            foreach ($this->slotCarts->keys() as $slotId) {
                if (empty($this->slotTables[$slotId])) {
                    $this->currentSlotId = $slotId;
                    unset($this->openRooms, $this->selectedRoom, $this->tableStates);
                    $this->addError('slotTables', 'Bitte wählen Sie für jede Pause einen Tisch.');
                    return;
                }
            }
        }

        $this->resetErrorBag();
        $this->step = min(4, $this->step + 1);

        if ($this->step === 3) {
            // Auf die erste bestellte Pause springen und Raum ggf. automatisch wählen.
            $orderedIds = $this->slotCarts->keys()->all();
            if ($orderedIds && !in_array($this->currentSlotId, $orderedIds, true)) {
                $this->currentSlotId = $orderedIds[0];
                unset($this->openRooms, $this->selectedRoom, $this->tableStates);
            }
            $this->autoSelectSingleRoom();
        }
    }

    public function prevStep(): void
    {
        $this->resetErrorBag();
        $this->step = max(1, $this->step - 1);
    }

    // ── Step 2/3: Pause wechseln ─────────────────────────────────

    public function selectSlot(int $slotId): void
    {
        $this->currentSlotId = $slotId;
        // Computeds für die neue Pause neu berechnen.
        unset($this->cartItems, $this->currentSlot, $this->openRooms, $this->selectedRoom, $this->tableStates);
        $this->autoSelectSingleRoom();
    }

    // ── Step 2: Produkte (server-seitig je aktueller Pause) ───────

    public function incrementItem(int $itemId): void
    {
        if (!$this->currentSlotId) {
            return;
        }

        $current = $this->carts[$this->currentSlotId][$itemId] ?? 0;
        $this->carts[$this->currentSlotId][$itemId] = min(
            CartCalculator::MAX_QUANTITY_PER_ITEM,
            $current + 1,
        );
        unset($this->cartItems, $this->slotCarts);
    }

    public function decrementItem(int $itemId): void
    {
        if (!$this->currentSlotId) {
            return;
        }

        $current = $this->carts[$this->currentSlotId][$itemId] ?? 0;
        if ($current <= 1) {
            unset($this->carts[$this->currentSlotId][$itemId]);
        } else {
            $this->carts[$this->currentSlotId][$itemId] = $current - 1;
        }
        unset($this->cartItems, $this->slotCarts);
    }

    // ── Step 3: Sitzplatz (je aktueller Pause) ───────────────────

    public function selectRoom(int $eventRoomId): void
    {
        if (!$this->currentSlotId) {
            return;
        }

        $this->slotRooms[$this->currentSlotId] = $eventRoomId;
        unset($this->slotTables[$this->currentSlotId]); // Tisch bei Raumwechsel zurücksetzen
        unset($this->selectedRoom, $this->tableStates);
    }

    public function selectTable(int $tableId): void
    {
        $state = collect($this->tableStates)->first(fn ($s) => $s['table']->id === $tableId);

        if (!$state || empty($state['bookable'])) {
            $this->addError('slotTables', 'Dieser Tisch hat nicht genug freie Plätze für Ihre Gruppe.');
            return;
        }

        $this->resetErrorBag('slotTables');
        $this->slotTables[$this->currentSlotId] = $tableId;
        unset($this->tableStates);
    }

    protected function autoSelectSingleRoom(): void
    {
        if (!$this->currentSlotId) {
            return;
        }

        if (empty($this->slotRooms[$this->currentSlotId]) && $this->openRooms->count() === 1) {
            $this->slotRooms[$this->currentSlotId] = $this->openRooms->first()->id;
            unset($this->selectedRoom, $this->tableStates);
        }
    }

    // ── Step 4: Checkout ─────────────────────────────────────────

    public function confirm(): void
    {
        // Härtung: Gastdaten final gegenprüfen (falls Step 1 umgangen wurde).
        $this->normalizeHiddenGuestFields();
        $guest = \Illuminate\Support\Facades\Validator::make(
            [
                'guestName'  => $this->guestName,
                'guestEmail' => $this->guestEmail,
                'guestPhone' => $this->guestPhone,
                'guestCount' => $this->guestCount,
                'notes'      => $this->notes,
            ],
            $this->guestRules(dns: false),
            $this->guestMessages(),
        );

        if ($guest->fails()) {
            $this->step = 1;
            foreach ($guest->errors()->messages() as $field => $msgs) {
                foreach ($msgs as $msg) {
                    $this->addError($field, $msg);
                }
            }
            return;
        }

        // Die Zahlungsart wählt der Gast auf der Mollie-Bezahlseite.
        $this->validate(
            ['legalAccepted' => 'accepted'],
            ['legalAccepted.accepted' => 'Bitte bestätigen Sie die Hinweise.'],
        );

        if ($this->requiresAgeCheck && !$this->ageConfirmed) {
            $this->addError('ageConfirmed', 'Ihre Bestellung enthält alkoholische Getränke – bitte bestätigen Sie, dass Sie mindestens 18 Jahre alt sind.');
            return;
        }

        $event      = $this->event;
        $slotCarts  = $this->slotCarts; // slotId => lines (nur Pausen mit Produkten)

        if ($slotCarts->isEmpty()) {
            $this->step = 2;
            $this->addError('carts', 'Bitte wählen Sie für mindestens eine Pause ein gültiges Produkt.');
            return;
        }

        if (!$event->isOrderable()) {
            $this->addError('carts', 'Der Bestellschluss für diesen Termin ist leider erreicht.');
            return;
        }

        // Je Pause: Tisch gesetzt und genügend Restplätze (M1 ohne Locking).
        $seats = app(SeatAvailabilityService::class);
        $soft  = $this->checkoutSettings->softTableCapacity();
        foreach ($slotCarts->keys() as $slotId) {
            $tableId = $this->slotTables[$slotId] ?? null;
            $slot    = $event->slots->firstWhere('id', $slotId);

            if (!$tableId || !$slot) {
                $this->currentSlotId = $slotId;
                $this->step = 3;
                $this->addError('slotTables', 'Bitte wählen Sie für jede Pause einen Tisch.');
                return;
            }

            $table = Table::find($tableId);
            if (!$table || ! $seats->canSeat($table, $slot, $this->guestCount, $soft)) {
                unset($this->slotTables[$slotId]);
                $this->currentSlotId = $slotId;
                $this->step = 3;
                $this->addError('slotTables', 'Ein gewählter Tisch wurde zwischenzeitlich belegt – bitte wählen Sie einen anderen.');
                return;
            }
        }

        // Idempotenz: eine bereits angelegte (noch offene) Order wiederverwenden.
        $existingOrder = $this->orderUuid
            ? Order::where('uuid', $this->orderUuid)
                ->where('team_id', $event->team_id)
                ->where('status', Order::STATUS_PENDING)
                ->first()
            : null;

        $order = DB::transaction(function () use ($event, $slotCarts, $existingOrder) {
            $order = $existingOrder ?? Order::create([
                'team_id'  => $event->team_id,
                'event_id' => $event->id,
                'status'   => Order::STATUS_PENDING,
            ]);

            // Bei erneutem Absenden: bestehende Buchungen der Order neu aufbauen.
            foreach ($order->bookings as $old) {
                $old->delete(); // booking_items kaskadieren über booking_id
            }

            foreach ($slotCarts as $slotId => $lines) {
                $slot  = $event->slots->firstWhere('id', $slotId);
                $table = Table::find($this->slotTables[$slotId]);

                $booking = Booking::create([
                    'order_id'               => $order->id,
                    'team_id'                => $event->team_id,
                    'event_id'               => $event->id,
                    'event_slot_id'          => $slot->id,
                    'table_id'               => $table->id,
                    'guest_name'             => $this->guestName,
                    'guest_email'            => $this->guestEmail ?: null,
                    'guest_phone'            => $this->guestPhone ?: null,
                    'guest_count'            => $this->guestCount,
                    'notes'                  => $this->notes ?: null,
                    'date'                   => $event->date->toDateString(),
                    'time_start'             => $slot->time_start,
                    'time_end'               => $slot->time_end,
                    'status'                 => Booking::STATUS_PENDING,
                    'payment_method'         => null,
                    'age_check_confirmed_at' => $this->requiresAgeCheck ? now() : null,
                    'legal_accepted_at'      => now(),
                ]);

                foreach ($this->calc()->frozenItemAttributes($lines) as $attributes) {
                    $booking->items()->create($attributes);
                }
            }

            return $order;
        });

        $this->orderUuid = $order->uuid;
        $order->load('bookings.items'); // frisch für die Betragsberechnung

        // Echte Zahlung, wenn für das Team Mollie hinterlegt ist UND ein
        // Betrag > 0 anfällt – sonst Mock-Bestätigung (Klick-Dummy/0 €).
        $payments = app(MolliePaymentService::class);

        if ($order->total_amount > 0 && $payments->isEnabledForTeam($event->team_id)) {
            try {
                $checkoutUrl = $payments->createForOrder($order);
                $this->redirect($checkoutUrl, navigate: false);

                return;
            } catch (\Throwable $e) {
                report($e);
                $this->addError('payment', 'Die Zahlung konnte nicht gestartet werden. Bitte versuchen Sie es erneut.');

                return;
            }
        }

        $this->step = 5;
    }

    public function render()
    {
        return view('reservation::livewire.guest.checkout-wizard')
            ->layout('platform::layouts.guest');
    }
}
