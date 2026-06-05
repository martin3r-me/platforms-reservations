<?php

namespace Platform\Reservation\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\BookingItem;
use Platform\Reservation\Models\FloorPlan;
use Platform\Reservation\Models\MenuItem;
use Platform\Reservation\Models\Table;
use Illuminate\Support\Facades\Auth;

class BookingCreate extends Component
{
    public int $teamId;
    public ?int $tableId = null;

    // Schritt-Wizard: 1 = Datum/Zeit, 2 = Gastdaten, 3 = Menü, 4 = Bestätigung
    public int $step = 1;

    // Schritt 1: Datum & Zeit
    public string $date = '';
    public string $timeStart = '';
    public string $timeEnd = '';
    public int $guestCount = 2;

    // Schritt 2: Gastdaten
    public string $guestName = '';
    public string $guestEmail = '';
    public string $guestPhone = '';
    public string $notes = '';

    // Schritt 3: Menü-Vorbestellung
    public array $selectedItems = []; // [menu_item_id => quantity]

    protected function rules(): array
    {
        return match ($this->step) {
            1 => [
                'date'       => 'required|date|after_or_equal:today',
                'timeStart'  => 'required|date_format:H:i',
                'guestCount' => 'required|integer|min:1|max:20',
                'tableId'    => 'nullable|integer|exists:reservation_tables,id',
            ],
            2 => [
                'guestName'  => 'required|string|max:255',
                'guestEmail' => 'nullable|email|max:255',
                'guestPhone' => 'nullable|string|max:30',
            ],
            default => [],
        };
    }

    public function mount(?int $tableId = null): void
    {
        $this->teamId  = Auth::user()->current_team_id;
        $this->tableId = $tableId;
        $this->date    = now()->toDateString();
    }

    #[Computed]
    public function availableTables(): \Illuminate\Database\Eloquent\Collection
    {
        return Table::whereHas('floorPlan', fn ($q) => $q->whereHas('venue', fn ($q2) => $q2->where('team_id', $this->teamId)))
            ->orderBy('label')
            ->get();
    }

    #[Computed]
    public function availableMenuItems(): \Illuminate\Database\Eloquent\Collection
    {
        return MenuItem::with('category', 'allergens')
            ->forTeam($this->teamId)
            ->available()
            ->orderBy('sort_order')
            ->get();
    }

    #[Computed]
    public function selectedTable(): ?Table
    {
        return $this->tableId ? Table::find($this->tableId) : null;
    }

    #[Computed]
    public function orderTotal(): float
    {
        $total = 0;
        foreach ($this->selectedItems as $itemId => $qty) {
            $item = MenuItem::find($itemId);
            if ($item) {
                $total += (float) $item->price * (int) $qty;
            }
        }
        return $total;
    }

    public function nextStep(): void
    {
        $rules = $this->rules();
        if (!empty($rules)) {
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

    public function confirm(): void
    {
        $this->validate([
            'guestName'  => 'required|string|max:255',
            'date'       => 'required|date',
            'timeStart'  => 'required|date_format:H:i',
            'tableId'    => 'nullable|integer|exists:reservation_tables,id',
        ]);

        $booking = Booking::create([
            'team_id'     => $this->teamId,
            'table_id'    => $this->tableId ?: null,
            'guest_name'  => $this->guestName,
            'guest_email' => $this->guestEmail,
            'guest_phone' => $this->guestPhone,
            'guest_count' => $this->guestCount,
            'notes'       => $this->notes,
            'date'        => $this->date,
            'time_start'  => $this->timeStart,
            'time_end'    => $this->timeEnd ?: null,
            'status'      => Booking::STATUS_PENDING,
        ]);

        foreach ($this->selectedItems as $itemId => $qty) {
            if ($qty > 0) {
                $item = MenuItem::findOrFail($itemId);
                BookingItem::create([
                    'booking_id'   => $booking->id,
                    'menu_item_id' => $itemId,
                    'quantity'     => $qty,
                    'unit_price'   => $item->price,
                    'tax_rate'     => $item->tax_rate,
                ]);
            }
        }

        $this->step = 4;
        $this->dispatch('booking-created', bookingId: $booking->id);
    }

    public function render()
    {
        return view('reservation::livewire.booking-create')
            ->layout('platform::layouts.app');
    }
}
