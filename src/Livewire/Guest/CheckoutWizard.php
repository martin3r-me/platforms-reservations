<?php

namespace Platform\Reservation\Livewire\Guest;

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Platform\Reservation\Models\Booking;
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
 * Steps lt. Kundentermin: 1 Gastdaten → 2 Produktauswahl → 3 Sitzplatz
 * (Slot/Raum/Tisch) → 4 Checkout → 5 Bestätigung.
 *
 * M1: Mock-Checkout (Zahlartauswahl ohne echte Zahlung); Mollie folgt in M2.
 * Team-Kontext kommt ausschließlich aus dem Event (kein Auth-Kontext!).
 */
class CheckoutWizard extends Component
{
    #[Locked]
    public string $uuid = '';

    public int $step = 1;

    // Step 1: Gastdaten
    public string $guestName = '';
    public string $guestEmail = '';
    public string $guestPhone = '';
    public int $guestCount = 2;
    public string $notes = '';

    // Step 2: Produktauswahl
    /** @var array<int, int> menu_item_id => Menge */
    public array $selectedItems = [];
    public bool $filterVegetarian = false;
    public bool $filterVegan = false;

    // Step 3: Sitzplatz
    public ?int $selectedSlotId = null;
    public ?int $selectedRoomId = null;   // EventRoom-ID
    public ?int $selectedTableId = null;

    // Step 4: Checkout (Zahlungsart wählt der Gast bei Mollie)
    public bool $ageConfirmed = false;
    public bool $legalAccepted = false;

    // Step 5: Bestätigung
    #[Locked]
    public ?string $orderUuid = null;

    public function mount(string $uuid): void
    {
        $this->uuid = $uuid;

        $event = $this->event;

        if ($event->slots->count() === 1) {
            $this->selectedSlotId = $event->slots->first()->id;
        }
    }

    #[Computed]
    public function event(): Event
    {
        return Event::where('uuid', $this->uuid)
            ->published()
            ->with(['venue', 'slots', 'eventRooms.floorPlan'])
            ->firstOrFail();
    }

    /** Artikel der Event-Verkaufsliste (Gast-sichtbar), optional gefiltert. */
    #[Computed]
    public function menuItems(): \Illuminate\Support\Collection
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

    /**
     * Preise (id => €) aller gast-sichtbaren Artikel der freigegebenen Liste –
     * für die sofortige Client-Anzeige (Alpine). Der verbindliche Preis wird
     * beim confirm() serverseitig autoritativ neu berechnet.
     */
    #[Computed]
    public function itemPrices(): array
    {
        $items = $this->event->resolveSalesList()?->guestVisibleItems() ?? collect();

        return $items->mapWithKeys(fn (MenuItem $item) => [$item->id => (float) $item->price])->all();
    }

    /** Autoritative Warenkorb-Kalkulation (auch von der künftigen Gast-API genutzt). */
    protected function calc(): CartCalculator
    {
        return app(CartCalculator::class);
    }

    #[Computed]
    public function cartItems(): \Illuminate\Support\Collection
    {
        return $this->calc()->lines($this->selectedItems, $this->event);
    }

    #[Computed]
    public function orderTotal(): float
    {
        return $this->calc()->total($this->cartItems);
    }

    /** Summen je MwSt-Satz für die Checkout-Zusammenfassung. */
    #[Computed]
    public function totalsByTaxRate(): \Illuminate\Support\Collection
    {
        return $this->calc()->totalsByTaxRate($this->cartItems);
    }

    #[Computed]
    public function requiresAgeCheck(): bool
    {
        return $this->calc()->containsAgeRestricted($this->cartItems);
    }

    #[Computed]
    public function selectedSlot(): ?EventSlot
    {
        return $this->selectedSlotId
            ? $this->event->slots->firstWhere('id', $this->selectedSlotId)
            : null;
    }

    /** @return \Illuminate\Support\Collection<int, EventRoom> */
    #[Computed]
    public function openRooms(): \Illuminate\Support\Collection
    {
        if (!$this->selectedSlot) {
            return collect();
        }

        return app(RoomReleaseService::class)->openRooms($this->event, $this->selectedSlot);
    }

    #[Computed]
    public function selectedRoom(): ?EventRoom
    {
        return $this->selectedRoomId
            ? $this->openRooms->firstWhere('id', $this->selectedRoomId)
            : null;
    }

    /** Tisch-Status für das Tischplan-Partial (platzgenau je Slot). */
    #[Computed]
    public function tableStates(): array
    {
        $room = $this->selectedRoom;
        $slot = $this->selectedSlot;

        if (!$room || !$slot) {
            return [];
        }

        $seats = app(SeatAvailabilityService::class);
        $bookedByTable = $seats->bookedSeatsByTable($room->floorPlan, $slot);
        $event = $this->event;

        return $room->floorPlan->tables()->where('is_active', true)->get()
            ->map(function (Table $table) use ($bookedByTable, $seats, $event) {
                $booked = $bookedByTable->get($table->id, 0);
                $remaining = max(0, $table->capacity - $booked);

                // Pro Termin gesperrte Tische sind nicht buchbar
                if ($event->isTableDisabled($table->id)) {
                    return ['table' => $table, 'state' => 'full', 'remaining' => 0];
                }

                $state = $this->selectedTableId === $table->id
                    ? 'selected'
                    : ($remaining === 0
                        ? 'full'
                        : ($remaining < $this->guestCount
                            ? 'full' // zu klein für die Gruppe → nicht wählbar
                            : $seats->tableStatus($table, $booked)));

                return ['table' => $table, 'state' => $state, 'remaining' => $remaining];
            })
            ->all();
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
        return [
            'guestName'  => 'required|string|max:255',
            'guestEmail' => 'required|email:rfc' . ($dns ? ',dns' : '') . '|max:255',
            'guestPhone' => ['nullable', 'string', 'max:30', 'regex:' . self::PHONE_REGEX],
            'guestCount' => 'required|integer|min:1|max:20',
        ];
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
            $this->validate($this->guestRules(dns: true), $this->guestMessages());
        }

        if ($this->step === 2 && empty($this->selectedItems)) {
            $this->addError('selectedItems', 'Bitte wählen Sie mindestens ein Produkt für Ihre Pause.');
            return;
        }

        if ($this->step === 3) {
            if (!$this->selectedSlotId) {
                $this->addError('selectedSlotId', 'Bitte wählen Sie eine Pause.');
                return;
            }
            if (!$this->selectedTableId) {
                $this->addError('selectedTableId', 'Bitte wählen Sie einen Tisch im Plan.');
                return;
            }
        }

        $this->resetErrorBag();
        $this->step = min(4, $this->step + 1);

        if ($this->step === 3 && $this->selectedSlotId && !$this->selectedRoomId) {
            $this->autoSelectSingleRoom();
        }
    }

    public function prevStep(): void
    {
        $this->resetErrorBag();
        $this->step = max(1, $this->step - 1);
    }

    // ── Step 2: Produkte ─────────────────────────────────────────

    public function incrementItem(int $itemId): void
    {
        $this->selectedItems[$itemId] = min(
            CartCalculator::MAX_QUANTITY_PER_ITEM,
            ($this->selectedItems[$itemId] ?? 0) + 1,
        );
    }

    public function decrementItem(int $itemId): void
    {
        if (($this->selectedItems[$itemId] ?? 0) <= 1) {
            unset($this->selectedItems[$itemId]);
        } else {
            $this->selectedItems[$itemId]--;
        }
    }

    // ── Step 3: Sitzplatz ────────────────────────────────────────

    public function selectSlot(int $slotId): void
    {
        $this->selectedSlotId = $slotId;
        $this->selectedRoomId = null;
        $this->selectedTableId = null;
        $this->autoSelectSingleRoom();
    }

    public function selectRoom(int $eventRoomId): void
    {
        $this->selectedRoomId = $eventRoomId;
        $this->selectedTableId = null;
    }

    public function selectTable(int $tableId): void
    {
        $states = collect($this->tableStates);
        $state = $states->first(fn ($s) => $s['table']->id === $tableId);

        if (!$state || $state['remaining'] < $this->guestCount) {
            $this->addError('selectedTableId', 'Dieser Tisch hat nicht genug freie Plätze für Ihre Gruppe.');
            return;
        }

        $this->resetErrorBag('selectedTableId');
        $this->selectedTableId = $tableId;

        // Computed-Cache leeren, damit der gewählte Tisch sofort hervorgehoben wird.
        unset($this->tableStates);
    }

    protected function autoSelectSingleRoom(): void
    {
        if ($this->openRooms->count() === 1) {
            $this->selectedRoomId = $this->openRooms->first()->id;
        }
    }

    // ── Step 4: Mock-Checkout ────────────────────────────────────

    public function confirm(): void
    {
        // Härtung: Gastdaten final gegenprüfen (falls Step 1 umgangen wurde).
        // DNS-Check hier aus – die Domain wurde in Step 1 bereits geprüft.
        $guest = \Illuminate\Support\Facades\Validator::make(
            [
                'guestName'  => $this->guestName,
                'guestEmail' => $this->guestEmail,
                'guestPhone' => $this->guestPhone,
                'guestCount' => $this->guestCount,
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

        // Nach der autoritativen Filterung (Verkaufsliste/Mengen) muss noch
        // mindestens eine gültige Position übrig sein.
        if ($this->cartItems->isEmpty()) {
            $this->step = 2;
            $this->addError('selectedItems', 'Bitte wählen Sie mindestens ein gültiges Produkt für Ihre Pause.');
            return;
        }

        $event = $this->event;
        $slot = $this->selectedSlot;
        $table = Table::findOrFail($this->selectedTableId);

        if (!$event->isOrderable()) {
            $this->addError('selectedItems', 'Der Bestellschluss für diesen Termin ist leider erreicht.');
            return;
        }

        // Finale Platzprüfung (M1 ohne Locking – Härtung in M2)
        $remaining = app(SeatAvailabilityService::class)->remainingSeats($table, $slot);
        if ($remaining < $this->guestCount) {
            $this->selectedTableId = null;
            $this->step = 3;
            $this->addError('selectedTableId', 'Der gewählte Tisch wurde zwischenzeitlich belegt – bitte wählen Sie einen anderen.');
            return;
        }

        // Idempotenz: eine bereits in diesem Wizard angelegte (noch offene)
        // Order wiederverwenden, statt bei erneutem Absenden/Zurück eine zweite
        // pending-Buchung anzulegen (die sonst Plätze doppelt binden würde).
        $existingOrder = $this->orderUuid
            ? Order::where('uuid', $this->orderUuid)
                ->where('team_id', $event->team_id)
                ->where('status', Order::STATUS_PENDING)
                ->first()
            : null;

        $order = DB::transaction(function () use ($event, $slot, $table, $existingOrder) {
            $order = $existingOrder ?? Order::create([
                'team_id'  => $event->team_id,
                'event_id' => $event->id,
                'status'   => Order::STATUS_PENDING,
            ]);

            $data = [
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
                // Zahlungsart kommt von Mollie per Webhook (sonst null).
                'payment_method'         => null,
                'age_check_confirmed_at' => $this->requiresAgeCheck ? now() : null,
                'legal_accepted_at'      => now(),
            ];

            // 2a: genau eine (Slot-)Buchung je Order; vorhandene pending-Buchung
            // der Order wiederverwenden. (Multi-Slot folgt in 2b.)
            $booking = $order->bookings()->where('status', Booking::STATUS_PENDING)->first();
            if ($booking) {
                $booking->update($data);
                $booking->items()->delete();
            } else {
                $booking = Booking::create($data);
            }

            foreach ($this->calc()->frozenItemAttributes($this->cartItems) as $attributes) {
                $booking->items()->create($attributes);
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
