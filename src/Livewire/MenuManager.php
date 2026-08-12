<?php

namespace Platform\Reservation\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\WithFileUploads;
use Platform\Reservation\Models\MenuCategory;
use Platform\Reservation\Models\MenuItem;
use Platform\Reservation\Models\HoldingClass;
use Platform\Reservation\Models\Allergen;
use Platform\Reservation\Models\Additive;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Platform\Reservation\Support\BundleComponents;

class MenuManager extends Component
{
    use WithFileUploads;

    // Kategorie-Formular
    public bool $showCategoryForm = false;
    public ?int $editingCategoryId = null;
    public string $categoryName = '';
    public string $categoryDescription = '';

    // Menüpunkt-Formular
    public bool $showItemForm = false;
    public ?int $editingItemId = null;
    public ?int $itemCategoryId = null;
    public ?int $itemHoldingClassId = null;
    public string $itemName = '';
    public string $itemDescription = '';
    public string $itemPortionSize = '';
    public string $itemPrice = '';
    public string $itemTaxRate = '7.00';
    public bool $itemAvailable = true;
    public bool $itemVegetarian = false;
    public bool $itemVegan = false;

    /**
     * Vegan schließt vegetarisch ein – die Liste zeigt deshalb nur EIN Badge.
     * Ohne diese Kopplung ließe sich "vegan, aber nicht vegetarisch" speichern,
     * und der Haken bei "Vegetarisch" hätte im Artikel keine sichtbare Wirkung.
     */
    public function updatedItemVegan(bool $value): void
    {
        if ($value) {
            $this->itemVegetarian = true;
        }
    }

    /** Vegetarisch abwählen hebt vegan mit auf – sonst bliebe ein Widerspruch. */
    public function updatedItemVegetarian(bool $value): void
    {
        if (! $value) {
            $this->itemVegan = false;
        }
    }
    public bool $itemAlcoholic = false;
    public ?int $itemMinAge = null;
    public bool $itemCaffeinated = false;
    public ?string $itemCaffeineMg = null;
    public array $itemAllergenIds = [];
    public array $itemAdditiveIds = [];

    // Bundle: Schalter und Bestandteile als [component_id => quantity]
    public bool $itemIsBundle = false;
    public array $itemComponents = [];

    /** Suchbegriff für die Artikelauswahl im Bundle (serverseitig gefiltert). */
    public string $componentSearch = '';

    /** Wie viele Treffer die Auswahl höchstens zeigt. */
    public const COMPONENT_LIMIT = 20;

    // Bild-Uploads (via HasContextImage-Trait → platform-core ContextFileService)
    public $itemImage = null;       // 1:1 Produktbild
    public $categoryImage = null;   // 16:9 Kategoriebild

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
        return MenuCategory::with(['imageFile.variants', 'menuItems' => function ($query) {
                if ($this->approvalFilter !== '') {
                    $query->where('approval_status', $this->approvalFilter);
                }
                $query->with(['allergens', 'additives', 'imageFile.variants'])->orderBy('sort_order');
            }])
            ->where('team_id', $this->getTeamId())
            ->orderBy('sort_order')
            ->get();
    }

    #[Computed]
    public function holdingClasses(): \Illuminate\Database\Eloquent\Collection
    {
        return HoldingClass::forTeam($this->getTeamId())->active()->get();
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

    /** Gewählte Bestandteil-IDs (Schlüssel der Mengen-Zuordnung). */
    protected function componentIds(): array
    {
        return array_values(array_map('intval', array_keys($this->itemComponents)));
    }

    /**
     * Artikel, die als Bestandteil in Frage kommen: team-eigen, kein Bundle,
     * nicht der bearbeitete Artikel selbst und noch nicht gewählt.
     *
     * Serverseitig gefiltert und auf COMPONENT_LIMIT begrenzt. Vorher wurden
     * ALLE Artikel des Teams samt Bildern geladen und ins DOM gerendert, nur
     * clientseitig versteckt – bei einem großen Sortiment sind das hunderte
     * Zeilen und Bildanfragen pro Dialog-Öffnung.
     *
     * Ein Treffer mehr als nötig wird geholt, um ohne zweite Zählabfrage zu
     * wissen, ob es weitere gibt.
     */
    #[Computed]
    public function componentCandidates(): \Illuminate\Support\Collection
    {
        $search = trim($this->componentSearch);

        return MenuItem::forTeam($this->getTeamId())
            ->where('is_bundle', false)
            ->when($this->editingItemId, fn ($q) => $q->whereKeyNot($this->editingItemId))
            ->when($this->componentIds(), fn ($q, $ids) => $q->whereNotIn('id', $ids))
            ->when($search !== '', fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->where('name', 'like', '%' . $search . '%')
                    ->orWhere('portion_size', 'like', '%' . $search . '%');
            }))
            // Bilder mitladen: die Auswahl zeigt Vorschaubilder (kein N+1).
            ->with('imageFile.variants')
            ->orderBy('name')
            ->limit(self::COMPONENT_LIMIT + 1)
            ->get();
    }

    /** Gibt es mehr Treffer, als die Auswahl zeigt? */
    #[Computed]
    public function moreComponentsAvailable(): bool
    {
        return $this->componentCandidates->count() > self::COMPONENT_LIMIT;
    }

    /** Bereits gewählte Bestandteile, in der Reihenfolge der Auswahl. */
    #[Computed]
    public function chosenComponents(): \Illuminate\Support\Collection
    {
        $ids = $this->componentIds();

        if ($ids === []) {
            return collect();
        }

        return MenuItem::forTeam($this->getTeamId())
            ->with(['allergens', 'additives'])
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (MenuItem $i) => array_search($i->id, $ids, true))
            ->values();
    }

    /**
     * Vorschau dessen, was der Gast sehen wird: Vergleichspreis, abgeleitete
     * Allergene und Altersgrenze. Der Betreiber soll die Ableitung sehen, statt
     * sie raten zu müssen.
     *
     * @return array{reference_price: float, saving: float, allergens: string, alcoholic: bool, min_age: ?int}
     */
    #[Computed]
    public function bundlePreview(): array
    {
        $components = $this->chosenComponents;

        $reference = 0.0;
        foreach ($components as $c) {
            $reference += (float) $c->price * max(1, (int) ($this->itemComponents[$c->id] ?? 1));
        }

        $price = (float) ($this->itemPrice !== '' ? $this->itemPrice : 0);

        $allergens = $components
            ->flatMap(fn (MenuItem $c) => $c->allergens)
            ->unique('id')
            ->sortBy('code')
            ->pluck('code')
            ->implode(', ');

        $ages = $components->map(fn (MenuItem $c) => $c->min_age?->value)->filter();

        return [
            'price'           => round($price, 2),
            'reference_price' => round($reference, 2),
            'saving'          => round($reference - $price, 2),
            'allergens'       => $allergens,
            'alcoholic'       => $components->contains(fn (MenuItem $c) => (bool) $c->is_alcoholic),
            'min_age'         => $ages->isEmpty() ? null : (int) $ages->max(),
        ];
    }

    public function addComponent(int $id): void
    {
        if (! isset($this->itemComponents[$id])) {
            $this->itemComponents[$id] = 1;
            // Auswahlliste neu rechnen: der Artikel fällt jetzt heraus.
            unset($this->chosenComponents, $this->bundlePreview, $this->componentCandidates, $this->moreComponentsAvailable);
        }
    }

    public function removeComponent(int $id): void
    {
        unset($this->itemComponents[$id]);
        unset($this->chosenComponents, $this->bundlePreview, $this->componentCandidates, $this->moreComponentsAvailable);
    }

    public function setComponentQuantity(int $id, int $quantity): void
    {
        if (isset($this->itemComponents[$id])) {
            $this->itemComponents[$id] = max(1, min(99, $quantity));
            unset($this->bundlePreview);
        }
    }

    public function toggleAllergen(int $id): void
    {
        $this->itemAllergenIds = in_array($id, $this->itemAllergenIds)
            ? array_values(array_diff($this->itemAllergenIds, [$id]))
            : [...$this->itemAllergenIds, $id];
    }

    public function toggleAdditive(int $id): void
    {
        $this->itemAdditiveIds = in_array($id, $this->itemAdditiveIds)
            ? array_values(array_diff($this->itemAdditiveIds, [$id]))
            : [...$this->itemAdditiveIds, $id];
    }

    /**
     * Bild speichern (via HasContextImage-Trait). Gibt true bei Erfolg zurück;
     * ein Fehler beim Bild bricht NICHT das ganze Formular ab, sondern meldet
     * die Ursache (session flash).
     */
    protected function storeImage($model, $file, string $contextType): bool
    {
        try {
            $model->setContextImage($file, $contextType, $this->getTeamId(), Auth::id());

            return true;
        } catch (\Throwable $e) {
            report($e);
            session()->flash('menu_error', 'Das Bild konnte nicht gespeichert werden: ' . $e->getMessage() . ' (Der Eintrag selbst wurde gespeichert.)');

            return false;
        }
    }

    protected function removeImage($model): void
    {
        $model->clearContextImage($this->getTeamId());
    }

    // Kategorie-Aktionen
    public function openCategoryForm(?int $id = null): void
    {
        $this->showCategoryForm = true;
        $this->editingCategoryId = $id;
        $this->categoryImage = null;

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
            'categoryName'  => 'required|string|max:255',
            'categoryImage' => 'nullable|image|max:20480',
        ]);

        $data = [
            'team_id'     => $this->getTeamId(),
            'name'        => $this->categoryName,
            'description' => $this->categoryDescription,
        ];

        if ($this->editingCategoryId) {
            $category = MenuCategory::findOrFail($this->editingCategoryId);
            $category->update($data);
        } else {
            $category = MenuCategory::create($data);
        }

        if ($this->categoryImage) {
            $this->storeImage($category, $this->categoryImage, 'reservation.menu_category.image');
            $this->categoryImage = null;
        }

        $this->showCategoryForm = false;
        $this->editingCategoryId = null;
        unset($this->categories);
    }

    public function deleteCategory(int $id): void
    {
        $category = MenuCategory::findOrFail($id);

        try {
            $category->delete();
        } catch (QueryException $e) {
            // Kategorie kaskadiert auf ihre Artikel; enthält sie bereits
            // bestellte Artikel, greift deren restrictOnDelete.
            session()->flash('menu_error', 'Die Kategorie enthält bereits bestellte Artikel und kann nicht gelöscht werden. Bitte die betroffenen Artikel zuerst deaktivieren.');

            return;
        }

        unset($this->categories);
    }

    // Menüpunkt-Aktionen
    /**
     * Eigener Einstieg für Bundles: ein Bundle ist gedanklich kein Artikel, bei
     * dem man nachträglich einen Haken setzt. Öffnet dasselbe Formular, aber
     * bereits im Bundle-Modus.
     */
    public function openBundleForm(?int $categoryId = null): void
    {
        $this->openItemForm(null, $categoryId);
        $this->itemIsBundle = true;
    }

    public function openItemForm(?int $id = null, ?int $categoryId = null): void
    {
        $this->showItemForm = true;
        $this->editingItemId = $id;
        $this->itemImage = null;
        $this->resetErrorBag();

        if ($id) {
            $item = MenuItem::with(['allergens', 'additives', 'components'])->findOrFail($id);
            $this->itemCategoryId     = $item->category_id;
            $this->itemHoldingClassId = $item->holding_class_id;
            $this->itemName          = $item->name;
            $this->itemDescription   = $item->description ?? '';
            $this->itemPortionSize   = $item->portion_size ?? '';
            $this->itemPrice         = (string) $item->price;
            $this->itemTaxRate       = $item->tax_rate;
            $this->itemAvailable     = $item->available;
            $this->itemVegetarian    = $item->is_vegetarian;
            $this->itemVegan         = $item->is_vegan;
            $this->itemAlcoholic     = $item->is_alcoholic;
            $this->itemMinAge        = $item->min_age?->value;
            $this->itemCaffeinated   = $item->is_caffeinated;
            $this->itemCaffeineMg    = $item->caffeine_mg !== null ? (string) $item->caffeine_mg : null;
            $this->itemAllergenIds   = $item->allergens->pluck('id')->toArray();
            $this->itemAdditiveIds   = $item->additives->pluck('id')->toArray();
            $this->itemIsBundle      = $item->isBundle();
            $this->itemComponents    = $item->components
                ->mapWithKeys(fn (MenuItem $c) => [$c->id => (int) ($c->pivot->quantity ?? 1)])
                ->all();
        } else {
            $this->resetItemForm($categoryId);
        }
    }

    protected function resetItemForm(?int $categoryId = null): void
    {
        $this->itemCategoryId     = $categoryId;
        $this->itemHoldingClassId = null;
        $this->itemName        = '';
        $this->itemDescription = '';
        $this->itemPortionSize = '';
        $this->itemPrice       = '';
        $this->itemTaxRate     = '7.00';
        $this->itemAvailable   = true;
        $this->itemVegetarian  = false;
        $this->itemVegan       = false;
        $this->itemAlcoholic   = false;
        $this->itemMinAge      = null;
        $this->itemCaffeinated = false;
        $this->itemCaffeineMg  = null;
        $this->itemAllergenIds = [];
        $this->itemAdditiveIds = [];
        $this->itemIsBundle    = false;
        $this->itemComponents  = [];
        $this->componentSearch = '';
    }

    public function saveItem(bool $createAnother = false): void
    {
        $this->validate([
            'itemCategoryId' => ['required', 'integer', Rule::exists('reservation_menu_categories', 'id')->where('team_id', $this->getTeamId())],
            'itemHoldingClassId' => ['nullable', 'integer', Rule::exists('reservation_holding_classes', 'id')->where('team_id', $this->getTeamId())],
            'itemName'        => 'required|string|max:255',
            'itemPortionSize' => 'nullable|string|max:50',
            'itemPrice'       => 'required|numeric|min:0',
            'itemTaxRate'     => ['required', function ($attribute, $value, $fail) {
                if (!in_array((float) $value, MenuItem::TAX_RATES, true)) {
                    $fail('Ungültiger MwSt-Satz. Erlaubt sind 7 % oder 19 %.');
                }
            }],
            'itemImage'       => 'nullable|image|max:20480',
            'itemMinAge'      => 'nullable|integer|in:16,18',
            'itemCaffeineMg'  => 'nullable|numeric|min:0|max:10000',
        ]);

        // Bundle-Prüfungen. Ein Bundle im Bundle würde die Preisverteilung in
        // eine Endlosschleife schicken; ein Selbstbezug ebenso.
        if ($this->itemIsBundle) {
            $componentIds = $this->componentIds();

            if ($componentIds === []) {
                $this->addError('itemComponents', 'Ein Bundle braucht mindestens einen Bestandteil.');

                return;
            }

            if ($this->editingItemId && in_array($this->editingItemId, $componentIds, true)) {
                $this->addError('itemComponents', 'Ein Bundle kann sich nicht selbst enthalten.');

                return;
            }

            $nested = MenuItem::whereIn('id', $componentIds)->where('is_bundle', true)->pluck('name');

            if ($nested->isNotEmpty()) {
                $this->addError('itemComponents', 'Bundles können keine Bundles enthalten: ' . $nested->implode(', '));

                return;
            }
        }

        $data = [
            'team_id'          => $this->getTeamId(),
            'category_id'      => $this->itemCategoryId,
            'holding_class_id' => $this->itemHoldingClassId ?: null,
            'name'          => $this->itemName,
            'description'   => $this->itemDescription,
            'portion_size'  => $this->itemPortionSize ?: null,
            'price'         => $this->itemPrice,
            'tax_rate'      => $this->itemTaxRate,
            'available'     => $this->itemAvailable,
            // Beim Speichern erzwingen, nicht nur beim Anklicken: Artikel aus der
            // Zeit vor der Kopplung können vegan=1 / vegetarisch=0 stehen haben,
            // und beim Öffnen des Formulars greift updatedItemVegan() nicht.
            'is_vegetarian' => $this->itemVegetarian || $this->itemVegan,
            'is_vegan'      => $this->itemVegan,
            'is_alcoholic'  => $this->itemAlcoholic,
            'min_age'       => $this->itemMinAge ?: null,
            'is_bundle'     => $this->itemIsBundle,
            'is_caffeinated' => $this->itemCaffeinated,
            'caffeine_mg'   => $this->itemCaffeinated && $this->itemCaffeineMg !== null && $this->itemCaffeineMg !== ''
                ? $this->itemCaffeineMg
                : null,
        ];

        if ($this->editingItemId) {
            $item = MenuItem::findOrFail($this->editingItemId);
            $item->update($data);
            $contentChanged = $item->wasChanged([
                'name', 'description', 'portion_size', 'price', 'tax_rate',
                'is_vegetarian', 'is_vegan', 'is_alcoholic', 'min_age', 'is_caffeinated', 'caffeine_mg',
                // Aus einem Artikel ein Bundle zu machen (oder umgekehrt) ändert
                // das Produkt grundlegend – Freigabe muss neu erteilt werden.
                'is_bundle',
            ]);
        } else {
            $item = MenuItem::create($data);
            $contentChanged = false;
        }

        if ($this->itemImage) {
            $this->storeImage($item, $this->itemImage, 'reservation.menu_item.image');
            $this->itemImage = null;
        }

        // Nur team-eigene Allergene/Zusatzstoffe zulassen (identisch zum Picker),
        // damit keine fremden IDs über einen manipulierten Request eingeschleust werden.
        $teamId = $this->getTeamId();
        $allowedAllergenIds = Allergen::forTeam($teamId)->pluck('id')->all();
        $allowedAdditiveIds = Additive::forTeam($teamId)->pluck('id')->all();

        $allergenChanges = $item->allergens()->sync(
            array_intersect(array_map('intval', $this->itemAllergenIds), $allowedAllergenIds)
        );
        $additiveChanges = $item->additives()->sync(
            array_intersect(array_map('intval', $this->itemAdditiveIds), $allowedAdditiveIds)
        );
        // Bestandteile setzen. Nur team-eigene, keine Bundles, nicht der Artikel
        // selbst – dieselben Schranken wie in der Validierung, damit ein
        // manipulierter Request nichts einschleusen kann.
        $componentChanges = ['attached' => [], 'detached' => []];

        if ($this->itemIsBundle) {
            $allowed = MenuItem::forTeam($teamId)
                ->whereIn('id', $this->componentIds())
                ->where('is_bundle', false)
                ->whereKeyNot($item->getKey())
                ->pluck('id')
                ->all();

            $sync = [];
            $sort = 0;

            foreach ($this->itemComponents as $componentId => $quantity) {
                $componentId = (int) $componentId;

                if (! in_array($componentId, $allowed, true)) {
                    continue;
                }

                $sync[$componentId] = [
                    'quantity'   => max(1, min(99, (int) $quantity)),
                    'sort_order' => $sort++,
                ];
            }

            $componentChanges = $item->components()->sync($sync);
        } elseif ($item->components()->exists()) {
            // Bundle-Schalter abgewählt: Bestandteile lösen, sonst bliebe ein
            // unsichtbarer Inhalt am Artikel hängen.
            $componentChanges = $item->components()->sync([]);
        }

        $pivotChanged = count($allergenChanges['attached']) || count($allergenChanges['detached'])
            || count($additiveChanges['attached']) || count($additiveChanges['detached'])
            || count($componentChanges['attached']) || count($componentChanges['detached'])
            || count($componentChanges['updated'] ?? []);

        // Fehlender Preisvorteil wird gemeldet, nicht verhindert – gespeichert
        // ist an dieser Stelle bereits.
        if ($this->itemIsBundle && ($notice = BundleComponents::priceNotice($item->load('components')))) {
            session()->flash('menu_warning', '„' . $item->name . '“: ' . $notice . ' Gespeichert wurde trotzdem.');
        }

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
        $item = MenuItem::findOrFail($id);

        // Bestandteil eines Bundles? Sonst meldet der Fang unten "bereits
        // bestellt" und schickt in die falsche Richtung.
        $bundles = $item->partOfBundles()->pluck('name');

        if ($bundles->isNotEmpty()) {
            session()->flash('menu_error', 'Der Artikel ist Bestandteil von: ' . $bundles->implode(', ')
                . '. Bitte zuerst dort entfernen oder das Bundle löschen.');

            return;
        }

        try {
            $item->delete();
        } catch (QueryException $e) {
            // Artikel hat Bestellhistorie (restrictOnDelete) → nicht löschbar.
            session()->flash('menu_error', 'Der Artikel wurde bereits bestellt und kann nicht gelöscht werden. Bitte stattdessen deaktivieren.');

            return;
        }

        unset($this->categories);
    }

    public function removeItemImage(): void
    {
        if ($this->editingItemId) {
            $this->removeImage(MenuItem::findOrFail($this->editingItemId));
            unset($this->categories);
        }
    }

    public function removeCategoryImage(): void
    {
        if ($this->editingCategoryId) {
            $this->removeImage(MenuCategory::findOrFail($this->editingCategoryId));
            unset($this->categories);
        }
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
