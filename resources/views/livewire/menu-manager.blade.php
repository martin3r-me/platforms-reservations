<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Artikel" icon="heroicon-o-rectangle-stack" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Artikel'],
        ]">
            {{-- Inline-Control (Filter) links neben der Navigation --}}
            <x-slot name="left">
                <div class="w-40">
                    <x-nx-input-select
                        name="approvalFilter"
                        size="sm"
                        :options="[
                            ['value' => 'draft', 'label' => 'Entwurf'],
                            ['value' => 'review', 'label' => 'In Prüfung'],
                            ['value' => 'approved', 'label' => 'Freigegeben'],
                        ]"
                        :nullable="true"
                        nullLabel="Alle Status"
                        wire:model.live="approvalFilter"
                    />
                </div>
            </x-slot>

            {{-- Seiten-Aktionen rechts gebündelt in EINEM Dropdown --}}
            <x-nx-dropdown label="Aktionen">
                @if (\Illuminate\Support\Facades\Route::has('reservation.menu.import'))
                    <x-nx-dropdown-item :href="route('reservation.menu.import')">
                        @svg('heroicon-o-arrow-up-tray', 'w-4 h-4')
                        <span>Import</span>
                    </x-nx-dropdown-item>
                @endif
                <x-nx-dropdown-item wire:click="openCategoryForm()">
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    <span>Kategorie</span>
                </x-nx-dropdown-item>
            </x-nx-dropdown>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
    <div class="space-y-5">

    @if (session('menu_message'))
        <x-nx-callout variant="success">{{ session('menu_message') }}</x-nx-callout>
    @endif
    @if (session('menu_error'))
        <x-nx-callout variant="danger">{{ session('menu_error') }}</x-nx-callout>
    @endif

    @if ($this->categories->isEmpty())
        <x-nx-card>
            <x-nx-empty icon="heroicon-o-rectangle-stack">
                <span class="text-sm font-medium text-[color:var(--nx-text)]">Noch keine Kategorien</span>
                <span class="mt-1 block">Lege zuerst eine Kategorie an, dann Artikel.</span>
            </x-nx-empty>
        </x-nx-card>
    @endif

    @foreach ($this->categories as $category)
        <x-nx-card flush wire:key="cat-{{ $category->id }}">
            <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
                @svg('heroicon-o-tag', 'w-4 h-4 text-[color:var(--nx-muted)]')
                <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">{{ $category->name }}</h2>
                <span class="text-xs tabular-nums text-[color:var(--nx-faint)]">{{ $category->menuItems->count() }}</span>
                <div class="ml-auto flex items-center gap-1">
                    <x-nx-button variant="primary" wire:click="openItemForm(null, {{ $category->id }})">
                        @svg('heroicon-o-plus', 'w-4 h-4')
                        <span>Artikel</span>
                    </x-nx-button>
                    <x-nx-button icon variant="ghost" wire:click="openCategoryForm({{ $category->id }})" title="Kategorie bearbeiten">
                        @svg('heroicon-o-pencil', 'w-4 h-4')
                    </x-nx-button>
                    <button type="button" wire:click="deleteCategory({{ $category->id }})" wire:confirm="Kategorie wirklich löschen?" title="Löschen"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-[6px] text-[color:var(--nx-danger)] transition-colors hover:bg-[rgba(224,49,49,.08)]">
                        @svg('heroicon-o-trash', 'w-4 h-4')
                    </button>
                </div>
            </div>

            <div>
                @foreach ($category->menuItems as $item)
                    <div wire:key="item-{{ $item->id }}" class="group flex items-center border-b border-[color:var(--nx-line)] px-4 py-2.5 transition-colors last:border-0 hover:bg-[color:var(--nx-hover)]">
                        @if ($item->image_context_file_id && $item->imageFile)
                            <img src="{{ $item->imageUrl('thumbnail_1_1') }}" alt=""
                                class="mr-3 h-12 w-12 shrink-0 rounded-[8px] object-cover" />
                        @else
                            <div class="mr-3 flex h-12 w-12 shrink-0 items-center justify-center rounded-[8px] bg-[color:var(--nx-bg)] text-[color:var(--nx-faint)]">
                                @svg('heroicon-o-photo', 'w-5 h-5 opacity-40')
                            </div>
                        @endif
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="text-sm font-medium text-[color:var(--nx-text)]">{{ $item->name }}</span>
                                @if ($item->portion_size)
                                    <span class="text-xs text-[color:var(--nx-muted)]">{{ $item->portion_size }}</span>
                                @endif

                                @php
                                    [$approvalLabel, $approvalVariant] = [
                                        'draft'    => ['Entwurf', 'neutral'],
                                        'review'   => ['In Prüfung', 'warning'],
                                        'approved' => ['Freigegeben', 'success'],
                                    ][$item->approval_status] ?? ['Entwurf', 'neutral'];
                                @endphp
                                <x-nx-badge :variant="$approvalVariant">{{ $approvalLabel }}</x-nx-badge>

                                @if ($item->is_vegan)
                                    <x-nx-badge variant="success">Vegan</x-nx-badge>
                                @elseif ($item->is_vegetarian)
                                    <x-nx-badge variant="success">Vegetarisch</x-nx-badge>
                                @endif
                                @if ($item->is_alcoholic)
                                    <x-nx-badge variant="info">18+</x-nx-badge>
                                @endif
                                @if (!$item->available)
                                    <x-nx-badge>Nicht verfügbar</x-nx-badge>
                                @endif
                            </div>
                            @if ($item->description)
                                <p class="m-0 mt-0.5 text-xs text-[color:var(--nx-muted)]">{{ $item->description }}</p>
                            @endif
                            @if ($item->allergens->isNotEmpty() || $item->additives->isNotEmpty())
                                <p class="m-0 mt-0.5 text-[11px] text-[color:var(--nx-faint)]">
                                    {{ $item->allergens->pluck('code')->merge($item->additives->pluck('code'))->filter()->map(fn ($c) => "($c)")->implode(' ') }}
                                </p>
                            @endif
                        </div>
                        <div class="ml-4 flex shrink-0 items-center justify-end gap-1">
                            <span class="mr-2 whitespace-nowrap text-sm font-semibold tabular-nums text-[color:var(--nx-text)]">
                                {{ number_format($item->price, 2, ',', '.') }} €
                            </span>

                            {{-- Freigabe-Workflow bleibt sichtbar (Kernaktion) --}}
                            @if ($item->approval_status === \Platform\Reservation\Models\MenuItem::APPROVAL_DRAFT)
                                <x-nx-button wire:click="submitItemForReview({{ $item->id }})">Zur Prüfung</x-nx-button>
                            @elseif ($item->approval_status === \Platform\Reservation\Models\MenuItem::APPROVAL_REVIEW)
                                <x-nx-button variant="primary" wire:click="approveItem({{ $item->id }})">Freigeben</x-nx-button>
                            @elseif ($item->approval_status === \Platform\Reservation\Models\MenuItem::APPROVAL_APPROVED)
                                <x-nx-button wire:click="resetItemApproval({{ $item->id }})" wire:confirm="Freigabe zurückziehen?">Zurückziehen</x-nx-button>
                            @endif

                            {{-- Sekundär: erscheint beim Hover --}}
                            <div class="flex items-center gap-0.5 opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100">
                                <x-nx-button icon variant="ghost" wire:click="openItemForm({{ $item->id }})" title="Bearbeiten">
                                    @svg('heroicon-o-pencil', 'w-4 h-4')
                                </x-nx-button>
                                <button type="button" wire:click="deleteItem({{ $item->id }})" wire:confirm="Artikel wirklich löschen?" title="Löschen"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-[6px] text-[color:var(--nx-danger)] transition-colors hover:bg-[rgba(224,49,49,.08)]">
                                    @svg('heroicon-o-trash', 'w-4 h-4')
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach

                @if ($category->menuItems->isEmpty())
                    <x-nx-empty icon="heroicon-o-inbox">{{ $approvalFilter !== '' ? 'Keine Artikel mit diesem Status.' : 'Keine Artikel vorhanden.' }}</x-nx-empty>
                @endif
            </div>
        </x-nx-card>
    @endforeach

    {{-- Kategorie-Modal --}}
    <x-nx-modal size="sm" wire:model="showCategoryForm">
        <x-slot name="header">
            <h3 class="m-0 text-base font-semibold leading-tight text-[color:var(--nx-text)]">
                {{ $editingCategoryId ? 'Kategorie bearbeiten' : 'Neue Kategorie' }}
            </h3>
        </x-slot>

        <div class="space-y-3">
            <x-nx-input-text name="categoryName" label="Name" wire:model="categoryName" required errorKey="categoryName" />
            <x-nx-input-textarea name="categoryDescription" label="Beschreibung" wire:model="categoryDescription" rows="2" />

            {{-- Kategoriebild (16:9) --}}
            <div>
                <label class="mb-1 block text-xs font-medium text-[color:var(--nx-text)]">Bild (16:9)</label>
                @php $editingCategory = $editingCategoryId ? $this->categories->firstWhere('id', $editingCategoryId) : null; @endphp
                @if ($categoryImage)
                    <img src="{{ $categoryImage->temporaryUrl() }}" alt="" class="mb-2 aspect-video w-full rounded-lg object-cover" />
                @elseif ($editingCategory?->image_context_file_id && $editingCategory->imageFile)
                    <div class="relative mb-2">
                        <img src="{{ $editingCategory->imageUrl('medium_16_9') }}" alt="" class="aspect-video w-full rounded-lg object-cover" />
                        <button wire:click="removeCategoryImage" type="button"
                            class="absolute right-2 top-2 rounded bg-black/60 px-2 py-1 text-xs text-white hover:bg-black/80">Entfernen</button>
                    </div>
                @endif
                @include('reservation::partials.image-upload', [
                    'model' => 'categoryImage',
                    'hint'  => '16:9 empfohlen · JPG, PNG oder WebP · max. 20 MB.',
                ])
            </div>
        </div>

        <x-slot name="footer">
            <x-nx-button wire:click="$set('showCategoryForm', false)">Abbrechen</x-nx-button>
            <x-nx-button variant="primary" wire:click="saveCategory">Speichern</x-nx-button>
        </x-slot>
    </x-nx-modal>

    {{-- Produkt-Modal --}}
    <x-nx-modal size="md" wire:model="showItemForm">
        <x-slot name="header">
            <h3 class="m-0 text-base font-semibold leading-tight text-[color:var(--nx-text)]">
                {{ $editingItemId ? 'Artikel bearbeiten' : 'Neuer Artikel' }}
            </h3>
            <p class="m-0 mt-1 text-xs text-[color:var(--nx-muted)]">Keine Pflichtfelder bei Allergenen/MwSt – Verantwortung beim Bearbeiter</p>
        </x-slot>

        <div class="space-y-4" x-data x-on:menu-item-form-reset.window="$nextTick(() => $refs.itemName?.querySelector('input')?.focus())">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-nx-input-select
                        name="itemCategoryId"
                        label="Kategorie"
                        :options="$this->categories"
                        optionValue="id"
                        optionLabel="name"
                        wire:model="itemCategoryId"
                        errorKey="itemCategoryId"
                    />
                </div>
                <div class="sm:col-span-2">
                    <x-nx-input-select
                        name="itemHoldingClassId"
                        label="Standzeit / Zeitkritikalität"
                        :options="$this->holdingClasses"
                        optionValue="id"
                        optionLabel="name"
                        :nullable="true"
                        nullLabel="— keine —"
                        wire:model="itemHoldingClassId"
                        errorKey="itemHoldingClassId"
                    />
                    @if ($this->holdingClasses->isEmpty())
                        <p class="mt-1 text-[11px] text-[color:var(--nx-muted)]">Noch keine Stufen angelegt – unter <a href="{{ route('reservation.settings.holding-classes') }}" class="underline" wire:navigate>Einstellungen → Standzeit-Klassen</a>.</p>
                    @endif
                </div>
                <div class="sm:col-span-2" x-ref="itemName">
                    <x-nx-input-text name="itemName" label="Name" wire:model="itemName" required errorKey="itemName" />
                </div>
                <div class="sm:col-span-2">
                    <x-nx-input-textarea name="itemDescription" label="Beschreibung" wire:model="itemDescription" rows="2" />
                </div>
                <div class="sm:col-span-2">
                    <x-nx-input-text name="itemPortionSize" label="Portionsgröße" wire:model="itemPortionSize" placeholder="z.B. 0,2 l · 0,5 l · 250 g" errorKey="itemPortionSize" />
                </div>
                <x-nx-input-number name="itemPrice" label="Preis (€)" step="0.01" min="0" wire:model="itemPrice" required errorKey="itemPrice" />
                <x-nx-input-select
                    name="itemTaxRate"
                    label="MwSt. (%)"
                    :options="[
                        ['value' => '7.00', 'label' => '7 %'],
                        ['value' => '19.00', 'label' => '19 %'],
                        ['value' => '0.00', 'label' => '0 %'],
                    ]"
                    wire:model="itemTaxRate"
                />
            </div>

            {{-- Produktbild (1:1) --}}
            <div>
                <label class="mb-1 block text-xs font-medium text-[color:var(--nx-text)]">Produktbild (quadratisch)</label>
                @php $editingItem = $editingItemId ? \Platform\Reservation\Models\MenuItem::with('imageFile.variants')->find($editingItemId) : null; @endphp
                <div class="flex items-start gap-3">
                    @if ($itemImage)
                        <img src="{{ $itemImage->temporaryUrl() }}" alt="" class="h-20 w-20 shrink-0 rounded-lg object-cover" />
                    @elseif ($editingItem?->image_context_file_id && $editingItem->imageFile)
                        <div class="relative shrink-0">
                            <img src="{{ $editingItem->imageUrl('thumbnail_1_1') }}" alt="" class="h-20 w-20 rounded-lg object-cover" />
                            <button wire:click="removeItemImage" type="button"
                                class="absolute -right-1 -top-1 rounded-full bg-black/60 px-1.5 text-xs text-white hover:bg-black/80">✕</button>
                        </div>
                    @endif
                    <div class="flex-1">
                        @include('reservation::partials.image-upload', [
                            'model' => 'itemImage',
                            'hint'  => 'JPG, PNG oder WebP · max. 20 MB (keine HEIC-Fotos vom iPhone).',
                        ])
                    </div>
                </div>
            </div>

            {{-- Eigenschaften --}}
            <div class="flex flex-wrap gap-x-5 gap-y-2">
                @foreach ([
                    'itemAvailable'  => 'Verfügbar',
                    'itemVegetarian' => 'Vegetarisch',
                    'itemVegan'      => 'Vegan',
                    'itemAlcoholic'  => 'Alkoholisch',
                    'itemCaffeinated' => 'Koffeinhaltig',
                ] as $prop => $label)
                    <x-nx-input-checkbox wire:model.live="{{ $prop }}" :label="$label" wire:key="prop-{{ $prop }}" />
                @endforeach
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                {{-- Altersgrenze --}}
                <x-nx-input-select
                    name="itemMinAge"
                    label="Altersgrenze (Jugendschutz)"
                    :options="[
                        ['value' => '', 'label' => 'Keine'],
                        ['value' => '16', 'label' => '16+ (Bier, Wein, Sekt)'],
                        ['value' => '18', 'label' => '18+ (Spirituosen)'],
                    ]"
                    wire:model="itemMinAge"
                    errorKey="itemMinAge"
                />

                {{-- Koffeingehalt (nur wenn koffeinhaltig) --}}
                @if ($itemCaffeinated)
                    <x-nx-input-number name="itemCaffeineMg" label="Koffeingehalt (mg/100 ml)" step="0.1" min="0" wire:model="itemCaffeineMg" placeholder="z. B. 32,0" errorKey="itemCaffeineMg" />
                @endif
            </div>

            {{-- Allergene --}}
            <div>
                <label class="mb-1 block text-xs font-medium text-[color:var(--nx-text)]">Allergene</label>
                @include('reservation::partials.tag-select', [
                    'options'     => $this->allergens,
                    'selected'    => $itemAllergenIds,
                    'toggle'      => 'toggleAllergen',
                    'accent'      => 'warning',
                    'placeholder' => 'Allergene auswählen…',
                    'key'         => 'allergens',
                ])
            </div>

            {{-- Zusatzstoffe --}}
            <div>
                <label class="mb-1 block text-xs font-medium text-[color:var(--nx-text)]">Zusatzstoffe</label>
                @include('reservation::partials.tag-select', [
                    'options'     => $this->additives,
                    'selected'    => $itemAdditiveIds,
                    'toggle'      => 'toggleAdditive',
                    'accent'      => 'info',
                    'placeholder' => 'Zusatzstoffe auswählen…',
                    'key'         => 'additives',
                ])
            </div>
        </div>

        <x-slot name="footer">
            <x-nx-button wire:click="$set('showItemForm', false)">Abbrechen</x-nx-button>
            @unless ($editingItemId)
                <x-nx-button wire:click="saveItem(true)">Speichern &amp; Neu</x-nx-button>
            @endunless
            <x-nx-button variant="primary" wire:click="saveItem">Speichern</x-nx-button>
        </x-slot>
    </x-nx-modal>

    </div>
    </x-ui-page-container>
</x-ui-page>
