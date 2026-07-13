<?php

namespace Platform\Reservation\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\WithFileUploads;
use Platform\Core\Services\ContextFileService;
use Platform\Reservation\Models\Venue;
use Platform\Reservation\Models\FloorPlan;
use Platform\Reservation\Models\Table;
use Illuminate\Support\Facades\Auth;

class FloorPlanEditor extends Component
{
    use WithFileUploads;

    public int $venueId;
    public ?int $floorPlanId = null;
    public string $floorPlanName = '';

    // Grundriss-Upload
    public $background = null;

    // Tisch-Formular
    public bool $showTableForm = false;
    public ?int $editingTableId = null;
    public string $tableLabel = '';
    public int $tableCapacity = 2;
    public string $tableShape = 'square';
    public string $tableColor = '';
    public float $tableX = 50;
    public float $tableY = 50;
    public float $tableWidth = 80;
    public float $tableHeight = 80;

    protected $rules = [
        'floorPlanName'  => 'required|string|max:255',
        'tableLabel'     => 'required|string|max:50',
        'tableCapacity'  => 'required|integer|min:1|max:50',
        'tableShape'     => 'required|in:round,square,rectangle',
        'tableX'         => 'required|numeric',
        'tableY'         => 'required|numeric',
        'tableWidth'     => 'required|numeric|min:30',
        'tableHeight'    => 'required|numeric|min:30',
    ];

    public function mount(int $venueId, ?int $floorPlanId = null): void
    {
        $this->venueId = $venueId;
        $this->floorPlanId = $floorPlanId;

        if ($floorPlanId) {
            $plan = FloorPlan::findOrFail($floorPlanId);
            $this->floorPlanName = $plan->name;
        }
    }

    #[Computed]
    public function venue(): Venue
    {
        return Venue::findOrFail($this->venueId);
    }

    #[Computed]
    public function floorPlan(): ?FloorPlan
    {
        return $this->floorPlanId ? FloorPlan::find($this->floorPlanId) : null;
    }

    #[Computed]
    public function tables(): \Illuminate\Database\Eloquent\Collection
    {
        if (!$this->floorPlanId) {
            return collect();
        }
        return Table::where('floor_plan_id', $this->floorPlanId)->active()->get();
    }

    public function saveFloorPlan(): void
    {
        $this->validate(['floorPlanName' => 'required|string|max:255']);

        if ($this->floorPlanId) {
            $plan = FloorPlan::findOrFail($this->floorPlanId);
            $plan->update(['name' => $this->floorPlanName]);
        } else {
            $plan = FloorPlan::create([
                'venue_id' => $this->venueId,
                'name'     => $this->floorPlanName,
            ]);
            $this->floorPlanId = $plan->id;
        }

        $this->dispatch('floor-plan-saved');
    }

    /** Grundriss (Hintergrundbild) hochladen/ersetzen. */
    public function updatedBackground(): void
    {
        if (!$this->floorPlanId) {
            $this->addError('background', 'Bitte zuerst den Tischplan speichern.');
            $this->background = null;
            return;
        }

        $this->validate(['background' => 'image|max:20480'], [
            'background.image' => 'Bitte ein Bild hochladen (JPG, PNG oder WebP).',
            'background.max'   => 'Das Bild ist zu groß (max. 20 MB).',
        ]);

        $plan = FloorPlan::findOrFail($this->floorPlanId);

        try {
            $service = app(ContextFileService::class);
            $uploaded = $service->uploadForContext($this->background, 'reservation.floor_plan.background', $plan->id, [
                'team_id' => Auth::user()?->current_team_id,
                'user_id' => Auth::id(),
            ]);

            if ($plan->background_context_file_id) {
                try {
                    $service->delete($plan->background_context_file_id, Auth::user()?->current_team_id);
                } catch (\Throwable $e) {
                    // altes File fehlt bereits
                }
            }

            $plan->update(['background_context_file_id' => $uploaded['id']]);
            $this->dispatch('floor-plan-saved');
        } catch (\Throwable $e) {
            report($e);
            $this->addError('background', 'Grundriss konnte nicht gespeichert werden: ' . $e->getMessage());
        } finally {
            $this->background = null;
            unset($this->floorPlan);
        }
    }

    public function removeBackground(): void
    {
        if (!$this->floorPlanId) {
            return;
        }

        $plan = FloorPlan::findOrFail($this->floorPlanId);

        if ($plan->background_context_file_id) {
            try {
                app(ContextFileService::class)->delete($plan->background_context_file_id, Auth::user()?->current_team_id);
            } catch (\Throwable $e) {
                // File bereits weg
            }
            $plan->update(['background_context_file_id' => null]);
        }

        unset($this->floorPlan);
    }

    public function openTableForm(?int $tableId = null): void
    {
        $this->showTableForm = true;
        $this->editingTableId = $tableId;

        if ($tableId) {
            $table = Table::findOrFail($tableId);
            $this->tableLabel    = $table->label;
            $this->tableCapacity = $table->capacity;
            $this->tableShape    = $table->shape;
            $this->tableColor    = $table->color ?? '';
            $this->tableX        = $table->x;
            $this->tableY        = $table->y;
            $this->tableWidth    = $table->width;
            $this->tableHeight   = $table->height;
        } else {
            $this->resetTableForm();
        }
    }

    public function saveTable(): void
    {
        $this->validate([
            'tableLabel'    => 'required|string|max:50',
            'tableCapacity' => 'required|integer|min:1|max:50',
            'tableShape'    => 'required|in:round,square,rectangle',
            'tableX'        => 'required|numeric',
            'tableY'        => 'required|numeric',
            'tableWidth'    => 'required|numeric|min:30',
            'tableHeight'   => 'required|numeric|min:30',
        ]);

        $data = [
            'floor_plan_id' => $this->floorPlanId,
            'label'         => $this->tableLabel,
            'capacity'      => $this->tableCapacity,
            'shape'         => $this->tableShape,
            'color'         => $this->tableColor ?: null,
            'x'             => $this->tableX,
            'y'             => $this->tableY,
            'width'         => $this->tableWidth,
            'height'        => $this->tableHeight,
        ];

        if ($this->editingTableId) {
            Table::findOrFail($this->editingTableId)->update($data);
        } else {
            Table::create($data);
        }

        $this->showTableForm = false;
        $this->editingTableId = null;
        $this->resetTableForm();
        unset($this->tables);
    }

    public function updateTablePosition(int $tableId, float $x, float $y): void
    {
        Table::findOrFail($tableId)->update(['x' => $x, 'y' => $y]);
        unset($this->tables);
    }

    public function deleteTable(int $tableId): void
    {
        Table::findOrFail($tableId)->delete();
        unset($this->tables);
    }

    protected function resetTableForm(): void
    {
        $this->tableLabel    = '';
        $this->tableCapacity = 2;
        $this->tableShape    = 'square';
        $this->tableColor    = '';
        $this->tableX        = 50;
        $this->tableY        = 50;
        $this->tableWidth    = 80;
        $this->tableHeight   = 80;
    }

    public function render()
    {
        return view('reservation::livewire.floor-plan-editor')
            ->layout('platform::layouts.app');
    }
}
