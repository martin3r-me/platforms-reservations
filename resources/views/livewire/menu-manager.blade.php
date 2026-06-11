<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Menü-Verwaltung" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Menü'],
        ]">
            <div class="flex items-center gap-2">
                <select wire:model.live="approvalFilter"
                    class="rounded-md border px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    <option value="">Alle Status</option>
                    <option value="draft">Entwurf</option>
                    <option value="review">In Prüfung</option>
                    <option value="approved">Freigegeben</option>
                </select>
                @if (\Illuminate\Support\Facades\Route::has('reservation.menu.import'))
                    <x-ui-button :href="route('reservation.menu.import')" variant="secondary" size="sm">
                        @svg('heroicon-o-arrow-up-tray', 'w-4 h-4')
                        Import
                    </x-ui-button>
                @endif
                <x-ui-button wire:click="openCategoryForm()" variant="primary" size="sm">
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    Kategorie
                </x-ui-button>
            </div>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
    <div class="pt-4 space-y-6">

    @if (session('menu_message'))
        <div class="rounded-lg bg-green-100 p-3 text-sm text-green-800 dark:bg-green-900/30 dark:text-green-300">
            {{ session('menu_message') }}
        </div>
    @endif
    @if (session('menu_error'))
        <div class="rounded-lg bg-red-100 p-3 text-sm text-red-800 dark:bg-red-900/30 dark:text-red-300">
            {{ session('menu_error') }}
        </div>
    @endif

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
                    @if ($item->image_context_file_id && $item->imageFile)
                        <img src="{{ $item->imageUrl('thumbnail_1_1') }}" alt=""
                            class="mr-3 h-12 w-12 shrink-0 rounded-lg object-cover" />
                    @else
                        <div class="mr-3 flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-300 dark:bg-gray-800 dark:text-gray-600">
                            @svg('heroicon-o-photo', 'w-5 h-5')
                        </div>
                    @endif
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span class="font-medium dark:text-white">{{ $item->name }}</span>

                            {{-- Freigabe-Badge --}}
                            @php
                                $approvalBadge = [
                                    'draft'    => ['Entwurf', 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300'],
                                    'review'   => ['In Prüfung', 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300'],
                                    'approved' => ['Freigegeben', 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'],
                                ][$item->approval_status] ?? ['Entwurf', 'bg-gray-200 text-gray-700'];
                            @endphp
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $approvalBadge[1] }}">
                                {{ $approvalBadge[0] }}
                            </span>

                            {{-- Flags --}}
                            @if ($item->is_vegan)
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">Vegan</span>
                            @elseif ($item->is_vegetarian)
                                <span class="rounded-full bg-lime-100 px-2 py-0.5 text-xs text-lime-700 dark:bg-lime-900/30 dark:text-lime-300">Vegetarisch</span>
                            @endif
                            @if ($item->is_alcoholic)
                                <span class="rounded-full bg-purple-100 px-2 py-0.5 text-xs text-purple-700 dark:bg-purple-900/30 dark:text-purple-300">18+</span>
                            @endif
                            @if (!$item->available)
                                <span class="rounded-full bg-gray-200 px-2 py-0.5 text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                    Nicht verfügbar
                                </span>
                            @endif
                        </div>
                        @if ($item->description)
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $item->description }}</p>
                        @endif
                        @if ($item->allergens->isNotEmpty() || $item->additives->isNotEmpty())
                            <div class="mt-1 flex flex-wrap gap-1">
                                @foreach ($item->allergens as $allergen)
                                    <span class="rounded bg-orange-100 px-1.5 py-0.5 text-xs text-orange-700 dark:bg-orange-900/30 dark:text-orange-300">
                                        {{ $allergen->code ? "({$allergen->code})" : '' }} {{ $allergen->name }}
                                    </span>
                                @endforeach
                                @foreach ($item->additives as $additive)
                                    <span class="rounded bg-sky-100 px-1.5 py-0.5 text-xs text-sky-700 dark:bg-sky-900/30 dark:text-sky-300">
                                        {{ $additive->code ? "({$additive->code})" : '' }} {{ $additive->name }}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="ml-4 flex items-center gap-3">
                        <span class="font-semibold text-indigo-600 dark:text-indigo-400">
                            {{ number_format($item->price, 2, ',', '.') }} €
                        </span>

                        {{-- Freigabe-Aktionen --}}
                        @if ($item->approval_status === \Platform\Reservation\Models\MenuItem::APPROVAL_DRAFT)
                            <button wire:click="submitItemForReview({{ $item->id }})"
                                class="text-xs text-yellow-600 hover:underline dark:text-yellow-400">Zur Prüfung</button>
                        @elseif ($item->approval_status === \Platform\Reservation\Models\MenuItem::APPROVAL_REVIEW)
                            <button wire:click="approveItem({{ $item->id }})"
                                class="text-xs text-green-600 hover:underline dark:text-green-400">Freigeben</button>
                        @elseif ($item->approval_status === \Platform\Reservation\Models\MenuItem::APPROVAL_APPROVED)
                            <button wire:click="resetItemApproval({{ $item->id }})"
                                wire:confirm="Freigabe zurückziehen?"
                                class="text-xs text-gray-500 hover:underline dark:text-gray-400">Zurückziehen</button>
                        @endif

                        <button wire:click="openItemForm({{ $item->id }})"
                            class="text-xs text-gray-500 hover:underline dark:text-gray-400">Edit</button>
                        <button wire:click="deleteItem({{ $item->id }})"
                            wire:confirm="Gericht löschen?"
                            class="text-xs text-red-500 hover:underline">Del</button>
                    </div>
                </div>
            @endforeach

            @if ($category->menuItems->isEmpty())
                <p class="px-4 py-3 text-sm text-gray-400">
                    {{ $approvalFilter !== '' ? 'Keine Gerichte mit diesem Status.' : 'Keine Gerichte vorhanden.' }}
                </p>
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

                    {{-- Kategoriebild (16:9) --}}
                    <div>
                        <label class="text-xs text-gray-600 dark:text-gray-400">Bild (16:9)</label>
                        @php $editingCategory = $editingCategoryId ? $this->categories->firstWhere('id', $editingCategoryId) : null; @endphp
                        @if ($categoryImage)
                            <img src="{{ $categoryImage->temporaryUrl() }}" alt="" class="mt-1 aspect-video w-full rounded-lg object-cover" />
                        @elseif ($editingCategory?->image_context_file_id && $editingCategory->imageFile)
                            <div class="relative mt-1">
                                <img src="{{ $editingCategory->imageUrl('medium_16_9') }}" alt="" class="aspect-video w-full rounded-lg object-cover" />
                                <button wire:click="removeCategoryImage" type="button"
                                    class="absolute right-2 top-2 rounded bg-black/60 px-2 py-1 text-xs text-white hover:bg-black/80">Entfernen</button>
                            </div>
                        @endif
                        <input type="file" wire:model="categoryImage" accept="image/*"
                            class="mt-1 w-full text-sm text-gray-600 dark:text-gray-300" />
                        <div wire:loading wire:target="categoryImage" class="mt-1 text-xs text-gray-500">Wird hochgeladen…</div>
                        @error('categoryImage') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
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
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900 overflow-y-auto max-h-screen"
                x-data
                x-on:menu-item-form-reset.window="$nextTick(() => $refs.itemName?.focus())">
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
                        <input wire:model="itemName" type="text" x-ref="itemName" autofocus
                            class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                        @error('itemName') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
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
                            @error('itemPrice') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
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

                    {{-- Produktbild (1:1) --}}
                    <div>
                        <label class="text-xs text-gray-600 dark:text-gray-400">Produktbild (quadratisch)</label>
                        @php $editingItem = $editingItemId ? \Platform\Reservation\Models\MenuItem::with('imageFile.variants')->find($editingItemId) : null; @endphp
                        <div class="mt-1 flex items-start gap-3">
                            @if ($itemImage)
                                <img src="{{ $itemImage->temporaryUrl() }}" alt="" class="h-20 w-20 rounded-lg object-cover" />
                            @elseif ($editingItem?->image_context_file_id && $editingItem->imageFile)
                                <div class="relative">
                                    <img src="{{ $editingItem->imageUrl('thumbnail_1_1') }}" alt="" class="h-20 w-20 rounded-lg object-cover" />
                                    <button wire:click="removeItemImage" type="button"
                                        class="absolute -right-1 -top-1 rounded-full bg-black/60 px-1.5 text-xs text-white hover:bg-black/80">✕</button>
                                </div>
                            @endif
                            <div class="flex-1">
                                <input type="file" wire:model="itemImage" accept="image/*"
                                    class="w-full text-sm text-gray-600 dark:text-gray-300" />
                                <div wire:loading wire:target="itemImage" class="mt-1 text-xs text-gray-500">Wird hochgeladen…</div>
                                @error('itemImage') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Eigenschaften --}}
                    <div class="flex flex-wrap gap-x-4 gap-y-2">
                        <label class="flex items-center gap-2 text-sm dark:text-white">
                            <input wire:model="itemAvailable" type="checkbox" class="rounded border-gray-300" />
                            Verfügbar
                        </label>
                        <label class="flex items-center gap-2 text-sm dark:text-white">
                            <input wire:model="itemVegetarian" type="checkbox" class="rounded border-gray-300" />
                            Vegetarisch
                        </label>
                        <label class="flex items-center gap-2 text-sm dark:text-white">
                            <input wire:model="itemVegan" type="checkbox" class="rounded border-gray-300" />
                            Vegan
                        </label>
                        <label class="flex items-center gap-2 text-sm dark:text-white">
                            <input wire:model="itemAlcoholic" type="checkbox" class="rounded border-gray-300" />
                            Alkoholisch (18+)
                        </label>
                    </div>

                    {{-- Allergene --}}
                    <div>
                        <label class="mb-1 block text-xs text-gray-600 dark:text-gray-400">Allergene</label>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($this->allergens as $allergen)
                                <label class="flex cursor-pointer items-center gap-1 rounded-full border px-2 py-1 text-xs
                                    {{ in_array($allergen->id, $itemAllergenIds) ? 'border-orange-400 bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300' : 'border-gray-300 dark:border-gray-700 dark:text-gray-300' }}">
                                    <input type="checkbox" wire:model.live="itemAllergenIds" value="{{ $allergen->id }}" class="sr-only" />
                                    {{ $allergen->code ? "({$allergen->code})" : '' }} {{ $allergen->name }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Zusatzstoffe --}}
                    <div>
                        <label class="mb-1 block text-xs text-gray-600 dark:text-gray-400">Zusatzstoffe</label>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($this->additives as $additive)
                                <label class="flex cursor-pointer items-center gap-1 rounded-full border px-2 py-1 text-xs
                                    {{ in_array($additive->id, $itemAdditiveIds) ? 'border-sky-400 bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300' : 'border-gray-300 dark:border-gray-700 dark:text-gray-300' }}">
                                    <input type="checkbox" wire:model.live="itemAdditiveIds" value="{{ $additive->id }}" class="sr-only" />
                                    {{ $additive->code ? "({$additive->code})" : '' }} {{ $additive->name }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button wire:click="$set('showItemForm', false)"
                        class="rounded-md border px-4 py-2 text-sm dark:border-gray-700 dark:text-white">Abbrechen</button>
                    @unless ($editingItemId)
                        <button wire:click="saveItem(true)"
                            class="rounded-md border border-indigo-300 px-4 py-2 text-sm text-indigo-700 hover:bg-indigo-50 dark:border-indigo-700 dark:text-indigo-300 dark:hover:bg-indigo-900/30">
                            Speichern &amp; Neu
                        </button>
                    @endunless
                    <button wire:click="saveItem"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700">Speichern</button>
                </div>
            </div>
        </div>
    @endif

    </div>
    </x-ui-page-container>
</x-ui-page>
