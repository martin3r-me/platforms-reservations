<?php

namespace Platform\Reservation\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Reservation\Models\MenuCategory;
use Platform\Reservation\Models\MenuItem;
use Platform\Reservation\Models\Allergen;
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
    public array $itemAllergenIds = [];

    protected function getTeamId(): ?int
    {
        $user = Auth::user();
        return $user?->current_team_id;
    }

    #[Computed]
    public function categories(): \Illuminate\Database\Eloquent\Collection
    {
        return MenuCategory::with(['menuItems.allergens'])
            ->where('team_id', $this->getTeamId())
            ->orderBy('sort_order')
            ->get();
    }

    #[Computed]
    public function allergens(): \Illuminate\Database\Eloquent\Collection
    {
        return Allergen::orderBy('name')->get();
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

        if ($id) {
            $item = MenuItem::with('allergens')->findOrFail($id);
            $this->itemCategoryId    = $item->category_id;
            $this->itemName          = $item->name;
            $this->itemDescription   = $item->description ?? '';
            $this->itemPrice         = (string) $item->price;
            $this->itemTaxRate       = $item->tax_rate;
            $this->itemAvailable     = $item->available;
            $this->itemAllergenIds   = $item->allergens->pluck('id')->toArray();
        } else {
            $this->itemCategoryId  = $categoryId;
            $this->itemName        = '';
            $this->itemDescription = '';
            $this->itemPrice       = '';
            $this->itemTaxRate     = '7.00';
            $this->itemAvailable   = true;
            $this->itemAllergenIds = [];
        }
    }

    public function saveItem(): void
    {
        $this->validate([
            'itemCategoryId' => 'required|integer|exists:reservation_menu_categories,id',
            'itemName'       => 'required|string|max:255',
            'itemPrice'      => 'required|numeric|min:0',
        ]);

        $data = [
            'team_id'     => $this->getTeamId(),
            'category_id' => $this->itemCategoryId,
            'name'        => $this->itemName,
            'description' => $this->itemDescription,
            'price'       => $this->itemPrice,
            'tax_rate'    => $this->itemTaxRate,
            'available'   => $this->itemAvailable,
        ];

        if ($this->editingItemId) {
            $item = MenuItem::findOrFail($this->editingItemId);
            $item->update($data);
        } else {
            $item = MenuItem::create($data);
        }

        $item->allergens()->sync($this->itemAllergenIds);

        $this->showItemForm = false;
        $this->editingItemId = null;
        unset($this->categories);
    }

    public function deleteItem(int $id): void
    {
        MenuItem::findOrFail($id)->delete();
        unset($this->categories);
    }

    public function render()
    {
        return view('reservation::livewire.menu-manager')
            ->layout('platform::layouts.app');
    }
}
