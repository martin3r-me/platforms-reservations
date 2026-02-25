<?php

namespace Platform\Reservation\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Reservation\Models\FloorPlan;
use Platform\Reservation\Models\Table;
use Platform\Reservation\Models\Booking;

class FloorPlanViewer extends Component
{
    public int $floorPlanId;
    public string $selectedDate;
    public string $selectedTimeStart = '';
    public string $selectedTimeEnd   = '';

    // Ausgewählter Tisch für die Buchung
    public ?int $selectedTableId = null;

    public function mount(int $floorPlanId): void
    {
        $this->floorPlanId   = $floorPlanId;
        $this->selectedDate  = now()->toDateString();
    }

    #[Computed]
    public function floorPlan(): FloorPlan
    {
        return FloorPlan::with('tables')->findOrFail($this->floorPlanId);
    }

    #[Computed]
    public function tableAvailability(): array
    {
        $tables = $this->floorPlan->tables()->active()->get();
        $result = [];

        foreach ($tables as $table) {
            $isAvailable = $table->isAvailableOn(
                $this->selectedDate,
                $this->selectedTimeStart ?: '00:00',
                $this->selectedTimeEnd ?: null
            );

            $result[$table->id] = [
                'table'       => $table,
                'available'   => $isAvailable,
                'color_class' => $isAvailable ? 'fill-green-400' : 'fill-red-400',
            ];
        }

        return $result;
    }

    public function selectTable(int $tableId): void
    {
        $availability = $this->tableAvailability;

        if (isset($availability[$tableId]) && $availability[$tableId]['available']) {
            $this->selectedTableId = $tableId;
            $this->dispatch('table-selected', tableId: $tableId);
        }
    }

    public function render()
    {
        return view('reservation::livewire.floor-plan-viewer');
    }
}
