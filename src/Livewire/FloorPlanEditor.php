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
use Platform\Reservation\Support\RoomLayout;

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

    // #[Locked], weil Löschen und Duplizieren jetzt darauf arbeiten statt auf
    // einem übergebenen Parameter – gesetzt wird es ausschließlich serverseitig
    // in openTableForm().
    #[Locked]
    public ?int $editingTableId = null;
    public string $tableLabel = '';
    public int $tableCapacity = 2;
    public string $tableShape = 'square';

    // Größe = Breite in Prozent der Planbreite. Die Höhe wird daraus abgeleitet
    // (heightFor()), damit runde Tische rund und eckige quadratisch bleiben –
    // per Hand lässt sich das Verhältnis nicht mehr verstellen.
    public int $tableSizePct = 10;

    // Ausrichtung in Grad, 45°-Schritte. Dreht nur die Darstellung der Fläche –
    // x_pct/y_pct bleiben der Mittelpunkt, w_pct/h_pct die unrotierten Maße.
    public int $tableRotation = 0;

    protected $rules = [
        'floorPlanName'  => 'required|string|max:255',
        'tableLabel'     => 'required|string|max:50',
        'tableCapacity'  => 'required|integer|min:1|max:50',
        'tableShape'     => 'required|in:round,square,rectangle',
        'tableSizePct'   => 'required|integer|min:2|max:40',
        'tableRotation'  => 'required|integer|min:0|max:315',
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

    /**
     * Name automatisch speichern, wie alles andere auf der Seite auch.
     *
     * Nur wenn der Plan schon existiert: bei einem NEUEN Plan würde sonst beim
     * ersten Tippen ein Tischplan mit halbem Namen entstehen. Dafür bleibt der
     * Knopf, der dann "Tischplan anlegen" heißt.
     */
    public function updatedFloorPlanName(): void
    {
        if (! $this->floorPlanId) {
            return;
        }

        $this->validate(['floorPlanName' => 'required|string|max:255']);

        $plan = FloorPlan::findOrFail($this->floorPlanId);

        // Ohne echte Änderung nicht schreiben: Tippen und wieder rückgängig
        // machen (oder Fokuswechsel) löste sonst einen UPDATE mit identischem
        // Wert aus – gleiche Regel wie beim Ziehen in updateTablePosition().
        if ($plan->name === $this->floorPlanName) {
            return;
        }

        $plan->update(['name' => $this->floorPlanName]);

        unset($this->floorPlan);
        $this->dispatch('floor-plan-saved');
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

    /** Höhe aus Breite und Form – Regel liegt am FloorPlan (einzige Quelle). */
    protected function heightFor(float $wPct, string $shape): float
    {
        return $this->floorPlan
            ? $this->floorPlan->heightForWidth($wPct, $shape)
            : min(0.9, max(0.02, $wPct * (4 / 3)));
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
            $this->tableSizePct  = (int) round(min(40, max(2, $table->w_pct * 100)));
            $this->tableRotation = Table::normalizeRotation((int) $table->rotation);
        } else {
            $this->resetTableForm();
        }
    }

    /** Ausrichtung im Formular schrittweise drehen (Delta z.B. +45 / -45). */
    public function rotateTableBy(int $delta): void
    {
        $this->tableRotation = Table::normalizeRotation($this->tableRotation + $delta);
    }

    /**
     * Tisch direkt im Plan weiterdrehen – Griff über dem Tisch, 45° je Klick.
     *
     * Bewusst OHNE skipRender(): anders als Position und Größe steckt die
     * Drehung im gerenderten style der Flächen-Ebene, es muss also neues HTML
     * kommen. Der äußere transform (Ziehen) ist davon nicht betroffen, weil
     * während eines Klicks keine Geste läuft.
     */
    public function rotateTable(int $tableId, int $delta = 45): void
    {
        $table = Table::findOrFail($tableId);

        // Runde Tische sehen gedreht identisch aus.
        if ($table->shape === 'round') {
            return;
        }

        $table->update([
            'rotation' => Table::normalizeRotation((int) $table->rotation + $delta),
        ]);

        // Steht das Formular für genau diesen Tisch offen, Anzeige mitziehen.
        if ($this->editingTableId === $tableId) {
            $this->tableRotation = (int) $table->rotation;
        }

        unset($this->tables);
    }

    public function saveTable(): void
    {
        $this->validate([
            'tableLabel'    => 'required|string|max:50',
            'tableCapacity' => 'required|integer|min:1|max:50',
            'tableShape'    => 'required|in:round,square,rectangle',
            'tableSizePct'  => 'required|integer|min:2|max:40',
            'tableRotation' => 'required|integer|min:0|max:315',
        ]);

        $wPct = $this->tableSizePct / 100;

        $data = [
            'label'    => $this->tableLabel,
            'capacity' => $this->tableCapacity,
            'shape'    => $this->tableShape,
            'w_pct'    => $wPct,
            'h_pct'    => $this->heightFor($wPct, $this->tableShape),
            // Runde Tische sehen gedreht identisch aus – gar nicht erst speichern.
            'rotation' => $this->tableShape === 'round' ? 0 : Table::normalizeRotation($this->tableRotation),
        ];

        if ($this->editingTableId) {
            // Position bleibt unangetastet (wird per Drag gepflegt); die Größe
            // kommt jetzt aus dem Formular statt nur aus dem Resize-Griff.
            Table::findOrFail($this->editingTableId)->update($data);
        } else {
            // Neuer Tisch: mittig platziert.
            Table::create($data + [
                'floor_plan_id' => $this->floorPlanId,
                'x_pct' => 0.5,
                'y_pct' => 0.5,
            ]);
        }

        $this->closeTableForm();
    }

    /** Formular schließen und zurücksetzen; Tischliste neu laden. */
    protected function closeTableForm(): void
    {
        $this->showTableForm  = false;
        $this->editingTableId = null;
        $this->resetTableForm();
        unset($this->tables);
    }

    /**
     * Aktuelle Formulargröße auf alle Tische des Plans übertragen – erspart das
     * Aufziehen jedes einzelnen Tischs. Die Höhe wird je Tisch aus dessen
     * eigener Form gerechnet, Positionen bleiben unberührt.
     */
    public function applySizeToAll(): void
    {
        $this->validate(['tableSizePct' => 'required|integer|min:2|max:40']);

        if (! $this->floorPlanId) {
            return;
        }

        $wPct = $this->tableSizePct / 100;

        foreach (Table::where('floor_plan_id', $this->floorPlanId)->get() as $table) {
            $table->update([
                'w_pct' => $wPct,
                'h_pct' => $this->heightFor($wPct, $table->shape),
            ]);
        }

        unset($this->tables);
        $this->dispatch('floor-plan-saved');
    }

    /**
     * Tisch duplizieren: Kapazität, Form und Größe übernehmen, leicht versetzt
     * daneben legen und die Nummer im Label hochzählen. Einen Tisch einstellen
     * und dann duplizieren ist schneller als jeden neu zu konfigurieren.
     *
     * Ohne Parameter, arbeitet auf $editingTableId: x-ui-confirm-button ruft
     * sein action= als Methodennamen auf, Argumente in Klammern landen als Teil
     * des Namens beim Server ("deleteTable(9) not found"). Plattformweit sind
     * daher alle action-Methoden parameterlos (deleteXAndCloseModal-Muster).
     */
    public function duplicateTable(): void
    {
        if (! $this->editingTableId) {
            return;
        }

        $source = Table::findOrFail($this->editingTableId);

        // Versatz nach rechts unten, aber innerhalb der Fläche bleiben.
        $offset = 0.04;

        Table::create([
            'floor_plan_id' => $source->floor_plan_id,
            'label'         => $this->nextLabel($source),
            'capacity'      => $source->capacity,
            'shape'         => $source->shape,
            'rotation'      => $source->rotation,
            'w_pct'         => $source->w_pct,
            'h_pct'         => $source->h_pct,
            'x_pct'         => min(1, $source->x_pct + $offset),
            'y_pct'         => min(1, $source->y_pct + $offset),
        ]);

        // Modal zu, damit die Kopie im Plan sichtbar ist – sonst liegt sie
        // hinter dem Dialog und man sieht nicht, wo sie gelandet ist.
        $this->closeTableForm();
        $this->dispatch('floor-plan-saved');
    }

    /**
     * Nächstes freies Label: "Stehtisch 14" -> "Stehtisch 15". Ohne Zahl im
     * Label wird " 2" angehängt bzw. weitergezählt, bis nichts kollidiert.
     */
    protected function nextLabel(Table $source): string
    {
        $existing = Table::where('floor_plan_id', $source->floor_plan_id)
            ->pluck('label')
            ->all();

        if (preg_match('/^(.*?)(\d+)(\D*)$/', $source->label, $m)) {
            [$prefix, $number, $suffix] = [$m[1], (int) $m[2], $m[3]];
        } else {
            [$prefix, $number, $suffix] = [rtrim($source->label) . ' ', 1, ''];
        }

        do {
            $number++;
            $candidate = $prefix . $number . $suffix;
        } while (in_array($candidate, $existing, true));

        return mb_substr($candidate, 0, 50);
    }

    /**
     * Position (Mittelpunkt) als Anteil 0…1 speichern.
     *
     * skipRender() ist hier wesentlich: Alpine hat die neue Position bereits per
     * inline-style gesetzt. Käme frisches HTML zurück, würde der Livewire-Morph
     * das style-Attribut überschreiben – bei jeder Latenzspitze sichtbar als
     * Zurückspringen des Tischs.
     */
    public function updateTablePosition(int $tableId, float $xPct, float $yPct): void
    {
        Table::findOrFail($tableId)->update([
            'x_pct' => min(1, max(0, $xPct)),
            'y_pct' => min(1, max(0, $yPct)),
        ]);

        unset($this->tables);
        $this->skipRender();
    }

    /**
     * Größe speichern – nur die Breite kommt vom Client, die Höhe folgt aus Form
     * und Seitenverhältnis. Damit ist das Verhältnis auch per Ziehen nicht mehr
     * verzerrbar (vorher kamen w und h unabhängig aus der Maus-Diagonale).
     */
    public function updateTableSize(int $tableId, float $wPct): void
    {
        $table = Table::findOrFail($tableId);
        $w     = min(0.4, max(0.02, $wPct));

        $table->update([
            'w_pct' => $w,
            'h_pct' => $this->heightFor($w, $table->shape),
        ]);

        unset($this->tables);
        $this->skipRender(); // siehe updateTablePosition()
    }

    /**
     * Löscht den gerade bearbeiteten Tisch und schließt das Formular.
     * Parameterlos – siehe Hinweis an duplicateTable().
     */
    /* =====================================================================
     | RAUMUMRISS – VERSUCHSSTAND, rückstandsfrei entfernbar
     |
     | Zum Entfernen genügen drei Schnitte: dieser Block, der Include der
     | Ebene im Blade samt zugehörigem Alpine-Abschnitt, und die Klasse
     | Support\RoomLayout. Keine Migration, keine Änderung an den Tischen,
     | nichts davon in der Gast-API – der Shop sieht den Umriss vorerst nicht.
     ===================================================================== */

    /** Zeichenmodus: solange aktiv, ruhen die Tische. */
    public bool $roomMode = false;

    /**
     * Züge des Raumumrisses, normalisiert (0…1).
     *
     * @return array<int, array<int, array{0: float, 1: float}>>
     */
    #[Computed]
    public function roomPaths(): array
    {
        return RoomLayout::paths($this->floorPlan);
    }

    public function toggleRoomMode(): void
    {
        $this->roomMode = ! $this->roomMode;
    }

    /**
     * Umriss speichern. Der Client schickt immer den vollständigen Stand –
     * einzelne Punkte nachzuhalten wäre fehleranfälliger als das bisschen
     * Nutzlast, und die Züge sind winzig.
     *
     * @param  array<mixed>  $paths
     */
    public function saveRoomPaths(array $paths): void
    {
        $plan = $this->floorPlan;

        if (! $plan) {
            return;
        }

        RoomLayout::save($plan, $paths);

        unset($this->floorPlan, $this->roomPaths);
        $this->skipRender();
    }

    /** Alles verwerfen – layout_json sieht danach aus wie vor der Funktion. */
    public function clearRoomPaths(): void
    {
        $plan = $this->floorPlan;

        if (! $plan) {
            return;
        }

        RoomLayout::save($plan, []);

        unset($this->floorPlan, $this->roomPaths);
    }

    public function deleteTableAndCloseModal(): void
    {
        if ($this->editingTableId) {
            Table::findOrFail($this->editingTableId)->delete();
        }

        $this->closeTableForm();
        $this->dispatch('floor-plan-saved');
    }

    protected function resetTableForm(): void
    {
        $this->tableLabel    = '';
        $this->tableCapacity = 2;
        $this->tableShape    = 'square';
        $this->tableSizePct  = 10;
        $this->tableRotation = 0;
    }

    public function render()
    {
        return view('reservation::livewire.floor-plan-editor')
            ->layout('platform::layouts.app');
    }
}
