<?php

namespace Platform\Reservation\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Reservation\Models\HoldingClass;

/**
 * Pflege der Standzeit-/Zeitkritikalitäts-Klassen (#523), team-bezogen.
 * Beim ersten Öffnen werden die drei Standard-Stufen angelegt. Die Reihenfolge
 * (sort_order) bestimmt später die Laufrunden-Priorität im Function Sheet.
 */
class HoldingClassSettings extends Component
{
    /** Standard-Stufen beim ersten Besuch (frei änderbar/erweiterbar). */
    public const DEFAULTS = [
        ['name' => 'Unbedenklich',      'description' => 'Zeitunkritisch, früh platzierbar (z. B. Brezel, Gummibärchen).', 'color' => '#16a34a'],
        ['name' => 'Sollte kalt sein',  'description' => 'Wird mit der Zeit wärmer (z. B. Kaltgetränk).',                    'color' => '#0ea5e9'],
        ['name' => 'Sollte heiß sein',  'description' => 'Kühlt schnell aus, verliert Qualität (z. B. Suppe, Currywurst).', 'color' => '#dc2626'],
    ];

    // Inline-Anlegen
    public string $newName = '';
    public string $newDescription = '';
    public string $newColor = '#64748b';

    // Inline-Bearbeiten
    public ?int $editingId = null;
    public string $editName = '';
    public string $editDescription = '';
    public string $editColor = '#64748b';

    protected function getTeamId(): int
    {
        return (int) (Auth::user()?->current_team_id ?? 0);
    }

    public function mount(): void
    {
        if (HoldingClass::forTeam($this->getTeamId())->doesntExist()) {
            $this->seedDefaults();
        }
    }

    protected function seedDefaults(): void
    {
        $teamId = $this->getTeamId();
        foreach (self::DEFAULTS as $i => $default) {
            HoldingClass::create([
                'team_id'     => $teamId,
                'name'        => $default['name'],
                'description' => $default['description'],
                'color'       => $default['color'],
                'sort_order'  => ($i + 1) * 10,
                'is_active'   => true,
            ]);
        }
    }

    #[Computed]
    public function classes(): \Illuminate\Database\Eloquent\Collection
    {
        return HoldingClass::forTeam($this->getTeamId())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->withCount('menuItems')
            ->get();
    }

    public function loadStandard(): void
    {
        $this->seedDefaults();
        unset($this->classes);
        session()->flash('hc_message', 'Standard-Stufen angelegt.');
    }

    public function add(): void
    {
        $this->validate([
            'newName'        => 'required|string|max:255',
            'newDescription' => 'nullable|string|max:1000',
            'newColor'       => 'nullable|string|max:7',
        ], [], ['newName' => 'Bezeichnung']);

        $nextOrder = ((int) HoldingClass::forTeam($this->getTeamId())->max('sort_order')) + 10;

        HoldingClass::create([
            'team_id'     => $this->getTeamId(),
            'name'        => trim($this->newName),
            'description' => trim($this->newDescription) ?: null,
            'color'       => trim($this->newColor) ?: null,
            'sort_order'  => $nextOrder,
            'is_active'   => true,
        ]);

        $this->reset('newName', 'newDescription');
        $this->newColor = '#64748b';
        unset($this->classes);
    }

    public function edit(int $id): void
    {
        $c = HoldingClass::forTeam($this->getTeamId())->findOrFail($id);
        $this->editingId       = $id;
        $this->editName        = $c->name;
        $this->editDescription = $c->description ?? '';
        $this->editColor       = $c->color ?: '#64748b';
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
    }

    public function update(): void
    {
        $this->validate([
            'editName'        => 'required|string|max:255',
            'editDescription' => 'nullable|string|max:1000',
            'editColor'       => 'nullable|string|max:7',
        ], [], ['editName' => 'Bezeichnung']);

        HoldingClass::forTeam($this->getTeamId())->whereKey($this->editingId)->first()?->update([
            'name'        => trim($this->editName),
            'description' => trim($this->editDescription) ?: null,
            'color'       => trim($this->editColor) ?: null,
        ]);

        $this->editingId = null;
        unset($this->classes);
    }

    public function delete(int $id): void
    {
        // Zugewiesene Artikel bleiben erhalten (FK nullOnDelete → holding_class_id = null).
        HoldingClass::forTeam($this->getTeamId())->whereKey($id)->delete();
        unset($this->classes);
    }

    public function moveUp(int $id): void
    {
        $this->swapWithNeighbour($id, 'up');
    }

    public function moveDown(int $id): void
    {
        $this->swapWithNeighbour($id, 'down');
    }

    protected function swapWithNeighbour(int $id, string $direction): void
    {
        $classes = HoldingClass::forTeam($this->getTeamId())->orderBy('sort_order')->orderBy('id')->get();
        $index   = $classes->search(fn ($c) => $c->id === $id);

        if ($index === false) {
            return;
        }

        $neighbourIndex = $direction === 'up' ? $index - 1 : $index + 1;
        if ($neighbourIndex < 0 || $neighbourIndex >= $classes->count()) {
            return;
        }

        $current   = $classes[$index];
        $neighbour = $classes[$neighbourIndex];

        // sort_order tauschen (bei Gleichstand vorher normalisieren).
        $currentOrder   = $current->sort_order;
        $neighbourOrder = $neighbour->sort_order;
        if ($currentOrder === $neighbourOrder) {
            $neighbourOrder = $direction === 'up' ? $currentOrder - 1 : $currentOrder + 1;
        }

        $current->update(['sort_order' => $neighbourOrder]);
        $neighbour->update(['sort_order' => $currentOrder]);

        unset($this->classes);
    }

    public function render()
    {
        return view('reservation::livewire.holding-class-settings')
            ->layout('platform::layouts.app');
    }
}
