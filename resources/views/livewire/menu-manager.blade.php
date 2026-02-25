<div class="p-4 space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold dark:text-white">Menü-Verwaltung</h1>
        <button wire:click="openCategoryForm()"
            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            + Kategorie
        </button>
    </div>

    @foreach ($this->categories as $category)
        <div class="rounded-xl border dark:border-gray-700">
            <div class="flex items-center justify-between border-b px-4 py-3 dark:border-gray-700">
                <h2 class="font-semibold dark:text-white">{{ $category->name }}</h2>
                <div class="flex gap-2">
                    <button wire:click="openItemForm(null, {{ $category->id }})"
                        class="text-xs text-indigo-600 hover:underline dark:text-indigo-400">+ Gericht</button>
                    <button wire:click="openCategoryForm({{ $category->id }})"
                        class="text-xs text-gray-500 hover:underline dark:text-gray-400">Bearbeiten</button>
                    <button wire:click="deleteCategory({{ $category->id }})"
                        wire:confirm="Kategorie und alle Gerichte löschen?"
                        class="text-xs text-red-500 hover:underline">Löschen</button>
                </div>
            </div>

            @foreach ($category->menuItems as $item)
                <div wire:key="item-{{ $item->id }}" class="flex items-center justify-between px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span class="font-medium dark:text-white">{{ $item->name }}</span>
                            @if (!$item->available)
                                <span class="rounded-full bg-gray-200 px-2 py-0.5 text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                    Nicht verfügbar
                                </span>
                            @endif
                        </div>
                        @if ($item->description)
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $item->description }}</p>
                        @endif
                        @if ($item->allergens->isNotEmpty())
                            <div class="mt-1 flex flex-wrap gap-1">
                                @foreach ($item->allergens as $allergen)
                                    <span class="rounded bg-orange-100 px-1.5 py-0.5 text-xs text-orange-700 dark:bg-orange-900/30 dark:text-orange-300">
                                        {{ $allergen->code ? "({$allergen->code})" : '' }} {{ $allergen->name }}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="ml-4 flex items-center gap-3">
                        <span class="font-semibold text-indigo-600 dark:text-indigo-400">
                            {{ number_format($item->price, 2, ',', '.') }} €
                        </span>
                        <button wire:click="openItemForm({{ $item->id }})"
                            class="text-xs text-gray-500 hover:underline dark:text-gray-400">Edit</button>
                        <button wire:click="deleteItem({{ $item->id }})"
                            wire:confirm="Gericht löschen?"
                            class="text-xs text-red-500 hover:underline">Del</button>
                    </div>
                </div>
            @endforeach

            @if ($category->menuItems->isEmpty())
                <p class="px-4 py-3 text-sm text-gray-400">Keine Gerichte vorhanden.</p>
            @endif
        </div>
    @endforeach

    {{-- Kategorie-Modal --}}
    @if ($showCategoryForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900">
                <h3 class="mb-4 text-lg font-semibold dark:text-white">
                    {{ $editingCategoryId ? 'Kategorie bearbeiten' : 'Neue Kategorie' }}
                </h3>
                <div class="space-y-3">
                    <input wire:model="categoryName" type="text" placeholder="Name"
                        class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                    <textarea wire:model="categoryDescription" rows="2" placeholder="Beschreibung (optional)"
                        class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"></textarea>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button wire:click="$set('showCategoryForm', false)"
                        class="rounded-md border px-4 py-2 text-sm dark:border-gray-700 dark:text-white">Abbrechen</button>
                    <button wire:click="saveCategory"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700">Speichern</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Menüpunkt-Modal --}}
    @if ($showItemForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900 overflow-y-auto max-h-screen">
                <h3 class="mb-4 text-lg font-semibold dark:text-white">
                    {{ $editingItemId ? 'Gericht bearbeiten' : 'Neues Gericht' }}
                </h3>
                <div class="space-y-3">
                    <div>
                        <label class="text-xs text-gray-600 dark:text-gray-400">Kategorie</label>
                        <select wire:model="itemCategoryId"
                            class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                            @foreach ($this->categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-600 dark:text-gray-400">Name *</label>
                        <input wire:model="itemName" type="text"
                            class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                    </div>
                    <div>
                        <label class="text-xs text-gray-600 dark:text-gray-400">Beschreibung</label>
                        <textarea wire:model="itemDescription" rows="2"
                            class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs text-gray-600 dark:text-gray-400">Preis (€) *</label>
                            <input wire:model="itemPrice" type="number" step="0.01" min="0"
                                class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                        </div>
                        <div>
                            <label class="text-xs text-gray-600 dark:text-gray-400">MwSt. (%)</label>
                            <select wire:model="itemTaxRate"
                                class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                <option value="7.00">7 %</option>
                                <option value="19.00">19 %</option>
                                <option value="0.00">0 %</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <input wire:model="itemAvailable" type="checkbox" id="itemAvailable"
                            class="rounded border-gray-300" />
                        <label for="itemAvailable" class="text-sm dark:text-white">Verfügbar</label>
                    </div>

                    {{-- Allergene --}}
                    <div>
                        <label class="mb-1 block text-xs text-gray-600 dark:text-gray-400">Allergene</label>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($this->allergens as $allergen)
                                <label class="flex cursor-pointer items-center gap-1 rounded-full border px-2 py-1 text-xs
                                    {{ in_array($allergen->id, $itemAllergenIds) ? 'border-orange-400 bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300' : 'border-gray-300 dark:border-gray-700 dark:text-gray-300' }}">
                                    <input type="checkbox" wire:model="itemAllergenIds" value="{{ $allergen->id }}" class="sr-only" />
                                    {{ $allergen->code ? "({$allergen->code})" : '' }} {{ $allergen->name }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button wire:click="$set('showItemForm', false)"
                        class="rounded-md border px-4 py-2 text-sm dark:border-gray-700 dark:text-white">Abbrechen</button>
                    <button wire:click="saveItem"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700">Speichern</button>
                </div>
            </div>
        </div>
    @endif
</div>
