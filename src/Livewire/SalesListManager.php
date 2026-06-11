<?php

namespace Platform\Reservation\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Reservation\Models\FloorPlan;
use Platform\Reservation\Models\MenuCategory;
use Platform\Reservation\Models\SalesList;

class SalesListManager extends Component
{
    // Listen-Formular
    public bool $showListForm = false;
    public ?int $editingListId = null;
    public string $listName = '';
    public string $listDescription = '';
    public bool $listIsDefault = false;

    // Artikel-Zuordnung
    public ?int $assigningListId = null;
    public array $assignedItemIds = [];
    public string $itemSearch = '';

    protected function getTeamId(): ?int
    {
        return Auth::user()?->current_team_id;
    }

    #[Computed]
    public function salesLists(): \Illuminate\Database\Eloquent\Collection
    {
        return SalesList::forTeam($this->getTeamId())
            ->withCount('menuItems')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function categoriesWithItems(): \Illuminate\Database\Eloquent\Collection
    {
        return MenuCategory::with(['menuItems' => function ($query) {
                if ($this->itemSearch !== '') {
                    $query->where('name', 'like', '%' . $this->itemSearch . '%');
                }
                $query->orderBy('sort_order');
            }])
            ->where('team_id', $this->getTeamId())
            ->orderBy('sort_order')
            ->get()
            ->filter(fn ($category) => $category->menuItems->isNotEmpty());
    }

    #[Computed]
    public function floorPlans(): \Illuminate\Database\Eloquent\Collection
    {
        return FloorPlan::with('venue')
            ->whereHas('venue', fn ($q) => $q->where('team_id', $this->getTeamId()))
            ->orderBy('name')
            ->get();
    }

    // Listen-CRUD
    public function openListForm(?int $id = null): void
    {
        $this->showListForm = true;
        $this->editingListId = $id;
        $this->resetErrorBag();

        if ($id) {
            $list = SalesList::findOrFail($id);
            $this->listName        = $list->name;
            $this->listDescription = $list->description ?? '';
            $this->listIsDefault   = $list->is_default;
        } else {
            $this->listName        = '';
            $this->listDescription = '';
            $this->listIsDefault   = false;
        }
    }

    public function saveList(): void
    {
        $this->validate([
            'listName' => 'required|string|max:255',
        ]);

        $data = [
            'team_id'     => $this->getTeamId(),
            'name'        => $this->listName,
            'description' => $this->listDescription,
            'is_default'  => $this->listIsDefault,
        ];

        if ($this->editingListId) {
            $list = SalesList::findOrFail($this->editingListId);
            $list->update($data);
        } else {
            $list = SalesList::create($data);
        }

        // Nur eine Default-Liste pro Team
        if ($this->listIsDefault) {
            SalesList::forTeam($this->getTeamId())
                ->where('id', '!=', $list->id)
                ->update(['is_default' => false]);
        }

        $this->showListForm = false;
        $this->editingListId = null;
        unset($this->salesLists);
    }

    public function deleteList(int $id): void
    {
        SalesList::findOrFail($id)->delete();

        if ($this->assigningListId === $id) {
            $this->assigningListId = null;
        }

        unset($this->salesLists);
    }

    // Artikel-Zuordnung
    public function openAssignment(int $listId): void
    {
        $list = SalesList::with('menuItems')->findOrFail($listId);
        $this->assigningListId = $listId;
        $this->assignedItemIds = $list->menuItems->pluck('id')->map(fn ($id) => (string) $id)->toArray();
        $this->itemSearch = '';
    }

    public function saveAssignment(): void
    {
        if (!$this->assigningListId) {
            return;
        }

        $list = SalesList::findOrFail($this->assigningListId);
        $list->menuItems()->sync(array_map('intval', $this->assignedItemIds));

        $this->assigningListId = null;
        unset($this->salesLists);
        session()->flash('sales_list_message', 'Artikel-Zuordnung gespeichert.');
    }

    // Raum-Default setzen
    public function setFloorPlanDefault(int $floorPlanId, ?int $salesListId): void
    {
        FloorPlan::findOrFail($floorPlanId)->update([
            'default_sales_list_id' => $salesListId ?: null,
        ]);

        unset($this->floorPlans);
    }

    public function render()
    {
        return view('reservation::livewire.sales-list-manager')
            ->layout('platform::layouts.app');
    }
}
