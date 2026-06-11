<?php

namespace Platform\Reservation\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Reservation\Models\MenuCategory;
use Platform\Reservation\Models\MenuItem;
use Platform\Reservation\Models\Allergen;
use Platform\Reservation\Models\Additive;
use Illuminate\Support\Facades\Auth;

class MenuManager extends Component
{
    // Kategorie-Formular
    public bool $showCategoryForm = false;
    public ?int $editingCategoryId = null;
    public string $categoryName = '';
    public string $categoryDescription = '';

    // Menüpunkt-Formular
    public bool $showItemForm = false;
    public ?int $editingItemId = null;
    public ?int $itemCategoryId = null;
    public string $itemName = '';
    public string $itemDescription = '';
    public string $itemPrice = '';
    public string $itemTaxRate = '7.00';
    public bool $itemAvailable = true;
    public bool $itemVegetarian = false;
    public bool $itemVegan = false;
    public bool $itemAlcoholic = false;
    public array $itemAllergenIds = [];
    public array $itemAdditiveIds = [];

    // Filter
    public string $approvalFilter = '';

    protected function getTeamId(): ?int
    {
        $user = Auth::user();
        return $user?->current_team_id;
    }

    #[Computed]
    public function categories(): \Illuminate\Database\Eloquent\Collection
    {
        return MenuCategory::with(['menuItems' => function ($query) {
                if ($this->approvalFilter !== '') {
                    $query->where('approval_status', $this->approvalFilter);
                }
                $query->with(['allergens', 'additives'])->orderBy('sort_order');
            }])
            ->where('team_id', $this->getTeamId())
            ->orderBy('sort_order')
            ->get();
    }

    #[Computed]
    public function allergens(): \Illuminate\Database\Eloquent\Collection
    {
        return Allergen::orderBy('code')->get();
    }

    #[Computed]
    public function additives(): \Illuminate\Database\Eloquent\Collection
    {
        return Additive::orderByRaw('CAST(code AS UNSIGNED)')->get();
    }

    // Kategorie-Aktionen
    public function openCategoryForm(?int $id = null): void
    {
        $this->showCategoryForm = true;
        $this->editingCategoryId = $id;

        if ($id) {
            $cat = MenuCategory::findOrFail($id);
            $this->categoryName        = $cat->name;
            $this->categoryDescription = $cat->description ?? '';
        } else {
            $this->categoryName        = '';
            $this->categoryDescription = '';
        }
    }

    public function saveCategory(): void
    {
        $this->validate([
            'categoryName' => 'required|string|max:255',
        ]);

        $data = [
            'team_id'     => $this->getTeamId(),
            'name'        => $this->categoryName,
            'description' => $this->categoryDescription,
        ];

        if ($this->editingCategoryId) {
            MenuCategory::findOrFail($this->editingCategoryId)->update($data);
        } else {
            MenuCategory::create($data);
        }

        $this->showCategoryForm = false;
        $this->editingCategoryId = null;
        unset($this->categories);
    }

    public function deleteCategory(int $id): void
    {
        MenuCategory::findOrFail($id)->delete();
        unset($this->categories);
    }

    // Menüpunkt-Aktionen
    public function openItemForm(?int $id = null, ?int $categoryId = null): void
    {
        $this->showItemForm = true;
        $this->editingItemId = $id;
        $this->resetErrorBag();

        if ($id) {
            $item = MenuItem::with(['allergens', 'additives'])->findOrFail($id);
            $this->itemCategoryId    = $item->category_id;
            $this->itemName          = $item->name;
            $this->itemDescription   = $item->description ?? '';
            $this->itemPrice         = (string) $item->price;
            $this->itemTaxRate       = $item->tax_rate;
            $this->itemAvailable     = $item->available;
            $this->itemVegetarian    = $item->is_vegetarian;
            $this->itemVegan         = $item->is_vegan;
            $this->itemAlcoholic     = $item->is_alcoholic;
            $this->itemAllergenIds   = $item->allergens->pluck('id')->toArray();
            $this->itemAdditiveIds   = $item->additives->pluck('id')->toArray();
        } else {
            $this->resetItemForm($categoryId);
        }
    }

    protected function resetItemForm(?int $categoryId = null): void
    {
        $this->itemCategoryId  = $categoryId;
        $this->itemName        = '';
        $this->itemDescription = '';
        $this->itemPrice       = '';
        $this->itemTaxRate     = '7.00';
        $this->itemAvailable   = true;
        $this->itemVegetarian  = false;
        $this->itemVegan       = false;
        $this->itemAlcoholic   = false;
        $this->itemAllergenIds = [];
        $this->itemAdditiveIds = [];
    }

    public function saveItem(bool $createAnother = false): void
    {
        $this->validate([
            'itemCategoryId' => 'required|integer|exists:reservation_menu_categories,id',
            'itemName'       => 'required|string|max:255',
            'itemPrice'      => 'required|numeric|min:0',
        ]);

        $data = [
            'team_id'       => $this->getTeamId(),
            'category_id'   => $this->itemCategoryId,
            'name'          => $this->itemName,
            'description'   => $this->itemDescription,
            'price'         => $this->itemPrice,
            'tax_rate'      => $this->itemTaxRate,
            'available'     => $this->itemAvailable,
            'is_vegetarian' => $this->itemVegetarian,
            'is_vegan'      => $this->itemVegan,
            'is_alcoholic'  => $this->itemAlcoholic,
        ];

        if ($this->editingItemId) {
            $item = MenuItem::findOrFail($this->editingItemId);
            $item->update($data);
            $contentChanged = $item->wasChanged([
                'name', 'description', 'price', 'tax_rate',
                'is_vegetarian', 'is_vegan', 'is_alcoholic',
            ]);
        } else {
            $item = MenuItem::create($data);
            $contentChanged = false;
        }

        $allergenChanges = $item->allergens()->sync($this->itemAllergenIds);
        $additiveChanges = $item->additives()->sync($this->itemAdditiveIds);
        $pivotChanged = count($allergenChanges['attached']) || count($allergenChanges['detached'])
            || count($additiveChanges['attached']) || count($additiveChanges['detached']);

        // Inhaltliche Änderung nach Freigabe → zurück auf Entwurf (Vier-Augen)
        if (($contentChanged || $pivotChanged) && $item->approval_status !== MenuItem::APPROVAL_DRAFT) {
            $item->resetApproval();
            session()->flash('menu_message', 'Artikel geändert – Freigabestatus wurde auf „Entwurf“ zurückgesetzt.');
        }

        if ($createAnother) {
            $this->editingItemId = null;
            $this->resetItemForm($this->itemCategoryId);
            $this->dispatch('menu-item-form-reset');
        } else {
            $this->showItemForm = false;
            $this->editingItemId = null;
        }

        unset($this->categories);
    }

    public function deleteItem(int $id): void
    {
        MenuItem::findOrFail($id)->delete();
        unset($this->categories);
    }

    // Vier-Augen-Freigabe
    public function submitItemForReview(int $id): void
    {
        MenuItem::findOrFail($id)->submitForReview(Auth::user());
        unset($this->categories);
    }

    public function approveItem(int $id): void
    {
        $item = MenuItem::findOrFail($id);

        if (!$item->approve(Auth::user())) {
            session()->flash('menu_error', 'Vier-Augen-Prinzip: Die Freigabe muss durch eine andere Person erfolgen als die Einreichung.');
            return;
        }

        unset($this->categories);
    }

    public function resetItemApproval(int $id): void
    {
        MenuItem::findOrFail($id)->resetApproval();
        unset($this->categories);
    }

    public function render()
    {
        return view('reservation::livewire.menu-manager')
            ->layout('platform::layouts.app');
    }
}
