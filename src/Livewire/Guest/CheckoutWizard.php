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
use Platform\Reservation\Models\Table;
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

    // Step 4: Mock-Checkout
    public string $paymentMethod = '';
    public bool $ageConfirmed = false;
    public bool $legalAccepted = false;

    // Step 5: Bestätigung
    public ?string $bookingUuid = null;

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

    #[Computed]
    public function cartItems(): \Illuminate\Support\Collection
    {
        if (empty($this->selectedItems)) {
            return collect();
        }

        return MenuItem::with('category')
            ->whereIn('id', array_keys($this->selectedItems))
            ->get()
            ->map(fn (MenuItem $item) => [
                'item'     => $item,
                'quantity' => $this->selectedItems[$item->id],
                'total'    => $item->price * $this->selectedItems[$item->id],
            ]);
    }

    #[Computed]
    public function orderTotal(): float
    {
        return (float) $this->cartItems->sum('total');
    }

    /** Summen je MwSt-Satz für die Checkout-Zusammenfassung. */
    #[Computed]
    public function totalsByTaxRate(): \Illuminate\Support\Collection
    {
        return $this->cartItems
            ->groupBy(fn ($line) => $line['item']->tax_rate)
            ->map(fn ($lines) => $lines->sum('total'))
            ->sortKeysDesc();
    }

    #[Computed]
    public function requiresAgeCheck(): bool
    {
        return $this->cartItems->contains(fn ($line) => $line['item']->is_alcoholic);
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

    // ── Navigation ───────────────────────────────────────────────

    public function nextStep(): void
    {
        if ($this->step === 1) {
            $this->validate([
                'guestName'  => 'required|string|max:255',
                'guestEmail' => 'nullable|email|max:255',
                'guestPhone' => 'nullable|string|max:30',
                'guestCount' => 'required|integer|min:1|max:20',
            ], [
                'guestName.required' => 'Bitte geben Sie Ihren Namen an.',
            ]);
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
        $this->selectedItems[$itemId] = ($this->selectedItems[$itemId] ?? 0) + 1;
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
        $this->validate([
            'paymentMethod' => 'required|in:card,paypal,applepay',
            'legalAccepted' => 'accepted',
        ], [
            'paymentMethod.required' => 'Bitte wählen Sie eine Zahlungsart.',
            'legalAccepted.accepted' => 'Bitte bestätigen Sie die Hinweise.',
        ]);

        if ($this->requiresAgeCheck && !$this->ageConfirmed) {
            $this->addError('ageConfirmed', 'Ihre Bestellung enthält alkoholische Getränke – bitte bestätigen Sie, dass Sie mindestens 18 Jahre alt sind.');
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

        $booking = DB::transaction(function () use ($event, $slot, $table) {
            $booking = Booking::create([
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
                'payment_method'         => $this->paymentMethod,
                'age_check_confirmed_at' => $this->requiresAgeCheck ? now() : null,
                'legal_accepted_at'      => now(),
            ]);

            foreach ($this->cartItems as $line) {
                $booking->items()->create([
                    'menu_item_id' => $line['item']->id,
                    'quantity'     => $line['quantity'],
                    'unit_price'   => $line['item']->price,   // Preis einfrieren
                    'tax_rate'     => $line['item']->tax_rate, // Steuersatz einfrieren
                ]);
            }

            return $booking;
        });

        $this->bookingUuid = $booking->uuid;
        $this->step = 5;
    }

    public function render()
    {
        return view('reservation::livewire.guest.checkout-wizard')
            ->layout('platform::layouts.guest');
    }
}
