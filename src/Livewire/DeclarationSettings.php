<?php

namespace Platform\Reservation\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Reservation\Models\Additive;
use Platform\Reservation\Models\Allergen;
use Platform\Reservation\Support\FoodDeclarations;

/**
 * Pflege der Allergen- und Zusatzstoff-Stammlisten (team-bezogen).
 * Beim ersten Öffnen wird die Standard-Legende (LMIV) übernommen.
 */
class DeclarationSettings extends Component
{
    // Inline-Anlegen
    public string $newAllergenCode = '';
    public string $newAllergenName = '';
    public string $newAdditiveCode = '';
    public string $newAdditiveName = '';

    // Inline-Bearbeiten
    public ?int $editingAllergenId = null;
    public string $editAllergenCode = '';
    public string $editAllergenName = '';
    public ?int $editingAdditiveId = null;
    public string $editAdditiveCode = '';
    public string $editAdditiveName = '';

    protected function getTeamId(): int
    {
        return (int) (Auth::user()?->current_team_id ?? 0);
    }

    public function mount(): void
    {
        // Standard-Legende beim ersten Besuch bereitstellen.
        if (Allergen::forTeam($this->getTeamId())->doesntExist()
            && Additive::forTeam($this->getTeamId())->doesntExist()
        ) {
            FoodDeclarations::ensureForTeam($this->getTeamId());
        }
    }

    #[Computed]
    public function allergens(): \Illuminate\Database\Eloquent\Collection
    {
        return Allergen::forTeam($this->getTeamId())->orderBy('code')->get();
    }

    #[Computed]
    public function additives(): \Illuminate\Database\Eloquent\Collection
    {
        return Additive::forTeam($this->getTeamId())->orderByRaw('CAST(code AS UNSIGNED), code')->get();
    }

    public function loadStandard(): void
    {
        $r = FoodDeclarations::ensureForTeam($this->getTeamId());
        unset($this->allergens, $this->additives);
        session()->flash('decl_message', "Standard-Legende übernommen ({$r['allergens']} Allergene, {$r['additives']} Zusatzstoffe neu).");
    }

    // ── Allergene ────────────────────────────────────────────────
    public function addAllergen(): void
    {
        $this->validate([
            'newAllergenCode' => 'required|string|max:10',
            'newAllergenName' => 'required|string|max:255',
        ], [], ['newAllergenCode' => 'Code', 'newAllergenName' => 'Bezeichnung']);

        Allergen::create([
            'team_id' => $this->getTeamId(),
            'code'    => trim($this->newAllergenCode),
            'name'    => trim($this->newAllergenName),
        ]);

        $this->reset('newAllergenCode', 'newAllergenName');
        unset($this->allergens);
    }

    public function editAllergen(int $id): void
    {
        $a = Allergen::forTeam($this->getTeamId())->findOrFail($id);
        $this->editingAllergenId = $id;
        $this->editAllergenCode = $a->code ?? '';
        $this->editAllergenName = $a->name;
    }

    public function updateAllergen(): void
    {
        $this->validate([
            'editAllergenCode' => 'required|string|max:10',
            'editAllergenName' => 'required|string|max:255',
        ]);

        Allergen::forTeam($this->getTeamId())->whereKey($this->editingAllergenId)->first()?->update([
            'code' => trim($this->editAllergenCode),
            'name' => trim($this->editAllergenName),
        ]);

        $this->editingAllergenId = null;
        unset($this->allergens);
    }

    public function deleteAllergen(int $id): void
    {
        Allergen::forTeam($this->getTeamId())->whereKey($id)->delete();
        unset($this->allergens);
    }

    // ── Zusatzstoffe ─────────────────────────────────────────────
    public function addAdditive(): void
    {
        $this->validate([
            'newAdditiveCode' => 'required|string|max:10',
            'newAdditiveName' => 'required|string|max:255',
        ], [], ['newAdditiveCode' => 'Code', 'newAdditiveName' => 'Bezeichnung']);

        Additive::create([
            'team_id' => $this->getTeamId(),
            'code'    => trim($this->newAdditiveCode),
            'name'    => trim($this->newAdditiveName),
        ]);

        $this->reset('newAdditiveCode', 'newAdditiveName');
        unset($this->additives);
    }

    public function editAdditive(int $id): void
    {
        $a = Additive::forTeam($this->getTeamId())->findOrFail($id);
        $this->editingAdditiveId = $id;
        $this->editAdditiveCode = $a->code ?? '';
        $this->editAdditiveName = $a->name;
    }

    public function updateAdditive(): void
    {
        $this->validate([
            'editAdditiveCode' => 'required|string|max:10',
            'editAdditiveName' => 'required|string|max:255',
        ]);

        Additive::forTeam($this->getTeamId())->whereKey($this->editingAdditiveId)->first()?->update([
            'code' => trim($this->editAdditiveCode),
            'name' => trim($this->editAdditiveName),
        ]);

        $this->editingAdditiveId = null;
        unset($this->additives);
    }

    public function deleteAdditive(int $id): void
    {
        Additive::forTeam($this->getTeamId())->whereKey($id)->delete();
        unset($this->additives);
    }

    public function render()
    {
        return view('reservation::livewire.declaration-settings')
            ->layout('platform::layouts.app');
    }
}
