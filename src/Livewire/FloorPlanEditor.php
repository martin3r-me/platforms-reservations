<?php

namespace Platform\Reservation\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\WithFileUploads;
use Platform\Core\Models\ContextFile;
use Platform\Core\Services\ContextFileService;
use Platform\Reservation\Models\Venue;
use Platform\Reservation\Models\FloorPlan;
use Platform\Reservation\Models\Table;
use Illuminate\Support\Facades\Auth;

class FloorPlanEditor extends Component
{
    use WithFileUploads;

    // Aus den Route-Parametern; dürfen clientseitig nicht manipulierbar sein,
    // sonst könnte über saveFloorPlan/saveTable unter fremder venue_id angelegt
    // werden (der globale Team-Scope greift bei create nicht).
    #[Locked]
    public int $venueId;

    #[Locked]
    public ?int $floorPlanId = null;

    public string $floorPlanName = '';

    // Grundriss-Upload
    public $background = null;

    // Atmosphäre-Bilder (Galerie, beliebig viele ContextFiles am Raum)
    public $atmosphereUploads = [];

    // Tisch-Formular
    public bool $showTableForm = false;
    public ?int $editingTableId = null;
    public string $tableLabel = '';
    public int $tableCapacity = 2;
    public string $tableShape = 'square';
    public string $tableColor = '';

    protected $rules = [
        'floorPlanName'  => 'required|string|max:255',
        'tableLabel'     => 'required|string|max:50',
        'tableCapacity'  => 'required|integer|min:1|max:50',
        'tableShape'     => 'required|in:round,square,rectangle',
    ];

    public function mount(int $venueId, ?int $floorPlanId = null): void
    {
        // Ownership-Guard: fremde Venue (anderes Team) -> 404 statt Editor.
        // Venue ist global team-gescoped, findOrFail wirft bei fremder ID.
        Venue::findOrFail($venueId);

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
        return $this->floorPlanId
            ? FloorPlan::with(['imageFile.variants', 'atmosphereFiles.variants'])->find($this->floorPlanId)
            : null;
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
            $plan->setContextImage(
                $this->background,
                'reservation.floor_plan.background',
                Auth::user()?->current_team_id,
                Auth::id(),
            );
            $this->dispatch('floor-plan-saved');
        } catch (\Throwable $e) {
            report($e);
            $this->addError('background', 'Grundriss konnte nicht gespeichert werden: ' . $e->getMessage());
        } finally {
            $this->background = null;
            unset($this->floorPlan);
        }
    }

    /** Grundriss um 90° drehen (delta = +90 im Uhrzeigersinn, -90 gegen den Uhrzeigersinn). */
    public function rotateBackground(int $delta): void
    {
        if (!$this->floorPlanId) {
            return;
        }

        $plan = FloorPlan::findOrFail($this->floorPlanId);
        $rotation = ((($plan->background_rotation ?? 0) + $delta) % 360 + 360) % 360;
        $plan->update(['background_rotation' => $rotation]);

        unset($this->floorPlan);
        $this->dispatch('floor-plan-saved');
    }

    public function removeBackground(): void
    {
        if (!$this->floorPlanId) {
            return;
        }

        FloorPlan::findOrFail($this->floorPlanId)
            ->clearContextImage(Auth::user()?->current_team_id);

        unset($this->floorPlan);
    }

    /** Atmosphäre-Bilder hochladen (mehrere ContextFiles am Raum-Kontext). */
    public function updatedAtmosphereUploads(): void
    {
        if (!$this->floorPlanId) {
            $this->addError('atmosphereUploads', 'Bitte zuerst den Tischplan speichern.');
            $this->atmosphereUploads = [];
            return;
        }

        $this->validate(['atmosphereUploads.*' => 'image|max:20480'], [
            'atmosphereUploads.*.image' => 'Bitte nur Bilder hochladen (JPG, PNG oder WebP).',
            'atmosphereUploads.*.max'   => 'Ein Bild ist zu groß (max. 20 MB).',
        ]);

        $service = app(ContextFileService::class);
        $teamId  = Auth::user()?->current_team_id;

        foreach ((array) $this->atmosphereUploads as $file) {
            try {
                $service->uploadForContext(
                    $file,
                    FloorPlan::ATMOSPHERE_CONTEXT,
                    $this->floorPlanId,
                    ['team_id' => $teamId, 'user_id' => Auth::id()],
                );
            } catch (\Throwable $e) {
                report($e);
                $this->addError('atmosphereUploads', 'Ein Bild konnte nicht gespeichert werden: ' . $e->getMessage());
            }
        }

        $this->atmosphereUploads = [];
        unset($this->floorPlan);
        $this->dispatch('floor-plan-saved');
    }

    /** Ein Atmosphäre-Bild löschen (nur wenn es zu diesem Raum gehört). */
    public function removeAtmosphereImage(int $fileId): void
    {
        if (!$this->floorPlanId) {
            return;
        }

        $owned = ContextFile::where('id', $fileId)
            ->where('context_type', FloorPlan::ATMOSPHERE_CONTEXT)
            ->where('context_id', $this->floorPlanId)
            ->exists();

        if ($owned) {
            app(ContextFileService::class)->delete($fileId, Auth::user()?->current_team_id);
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
        ]);

        $data = [
            'label'    => $this->tableLabel,
            'capacity' => $this->tableCapacity,
            'shape'    => $this->tableShape,
            'color'    => $this->tableColor ?: null,
        ];

        if ($this->editingTableId) {
            // Position/Größe bleiben unangetastet (werden per Drag/Resize gepflegt).
            Table::findOrFail($this->editingTableId)->update($data);
        } else {
            // Neuer Tisch: mittig, dezente Standardgröße. Höhe an Seitenverhältnis
            // angepasst, damit ein runder Tisch auch kreisrund erscheint.
            $aspect = $this->floorPlan?->displayAspect() ?? (4 / 3);
            $wPct = 0.10;
            $hPct = min(0.9, $wPct * $aspect);

            Table::create($data + [
                'floor_plan_id' => $this->floorPlanId,
                'x_pct' => 0.5,
                'y_pct' => 0.5,
                'w_pct' => $wPct,
                'h_pct' => $hPct,
            ]);
        }

        $this->showTableForm = false;
        $this->editingTableId = null;
        $this->resetTableForm();
        unset($this->tables);
    }

    /** Position (Mittelpunkt) als Anteil 0…1 speichern. */
    public function updateTablePosition(int $tableId, float $xPct, float $yPct): void
    {
        Table::findOrFail($tableId)->update([
            'x_pct' => min(1, max(0, $xPct)),
            'y_pct' => min(1, max(0, $yPct)),
        ]);
        unset($this->tables);
    }

    /** Größe als Anteil 0…1 speichern (Breite anteilig zur Fläche). */
    public function updateTableSize(int $tableId, float $wPct, float $hPct): void
    {
        Table::findOrFail($tableId)->update([
            'w_pct' => min(1, max(0.02, $wPct)),
            'h_pct' => min(1, max(0.02, $hPct)),
        ]);
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
    }

    public function render()
    {
        return view('reservation::livewire.floor-plan-editor')
            ->layout('platform::layouts.app');
    }
}
