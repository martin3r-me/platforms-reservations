<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Artikel" icon="heroicon-o-rectangle-stack" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Artikel'],
        ]">
            <div class="flex items-center gap-2">
                <div class="w-40">
                    <x-ui-input-select
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
                @if (\Illuminate\Support\Facades\Route::has('reservation.menu.import'))
                    <x-ui-button variant="secondary-outline" size="sm" :href="route('reservation.menu.import')">
                        @svg('heroicon-o-arrow-up-tray', 'w-4 h-4')
                        <span>Import</span>
                    </x-ui-button>
                @endif
                <x-ui-button variant="primary" size="sm" wire:click="openCategoryForm()">
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    <span>Kategorie</span>
                </x-ui-button>
            </div>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
    <div class="pt-4 space-y-4">

    @if (session('menu_message'))
        <div class="rounded-lg border border-[var(--ui-success)]/30 bg-[var(--ui-success-10)] p-3 text-sm text-[var(--ui-success)]">
            {{ session('menu_message') }}
        </div>
    @endif
    @if (session('menu_error'))
        <div class="rounded-lg border border-[var(--ui-danger)]/30 bg-[var(--ui-danger-10)] p-3 text-sm text-[var(--ui-danger)]">
            {{ session('menu_error') }}
        </div>
    @endif

    @if ($this->categories->isEmpty())
        <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm">
            <div class="flex flex-col items-center justify-center py-16 text-[var(--ui-muted)]">
                @svg('heroicon-o-rectangle-stack', 'w-10 h-10 mb-3 opacity-40')
                <span class="text-sm font-medium text-[var(--ui-secondary)]">Noch keine Kategorien</span>
                <span class="text-xs mt-1 opacity-70">Lege zuerst eine Kategorie an, dann Artikel.</span>
            </div>
        </section>
    @endif

    @foreach ($this->categories as $category)
        <section wire:key="cat-{{ $category->id }}" class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
                @svg('heroicon-o-tag', 'w-4 h-4 text-[var(--ui-muted)]')
                <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">{{ $category->name }}</h2>
                <span class="text-[11px] text-[var(--ui-muted)]">{{ $category->menuItems->count() }}</span>
                <div class="ml-auto flex items-center gap-1.5">
                    <x-ui-button variant="primary" size="sm" wire:click="openItemForm(null, {{ $category->id }})">
                        @svg('heroicon-o-plus', 'w-4 h-4')
                        <span>Artikel</span>
                    </x-ui-button>
                    <x-ui-button variant="secondary-outline" size="sm" :iconOnly="true" wire:click="openCategoryForm({{ $category->id }})" title="Kategorie bearbeiten">
                        @svg('heroicon-o-pencil', 'w-4 h-4')
                    </x-ui-button>
                    <div class="shrink-0">
                        <x-ui-confirm-button
                            action="deleteCategory"
                            :value="$category->id"
                            text=""
                            confirmText="Wirklich löschen?"
                            variant="danger-outline"
                            size="sm"
                            :icon="svg('heroicon-o-trash', 'w-4 h-4')->toHtml()"
                        />
                    </div>
                </div>
            </div>

            <div class="divide-y divide-[var(--ui-border)]/30">
                @foreach ($category->menuItems as $item)
                    <div wire:key="item-{{ $item->id }}" class="flex items-center px-4 py-2.5 hover:bg-[var(--ui-muted-5)] transition-colors">
                        @if ($item->image_context_file_id && $item->imageFile)
                            <img src="{{ $item->imageUrl('thumbnail_1_1') }}" alt=""
                                class="mr-3 h-12 w-12 shrink-0 rounded-lg object-cover" />
                        @else
                            <div class="mr-3 flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-[var(--ui-muted-5)] text-[var(--ui-muted)]">
                                @svg('heroicon-o-photo', 'w-5 h-5 opacity-40')
                            </div>
                        @endif
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="text-sm font-medium text-[var(--ui-secondary)]">{{ $item->name }}</span>
                                @if ($item->portion_size)
                                    <span class="text-xs text-[var(--ui-muted)]">{{ $item->portion_size }}</span>
                                @endif

                                @php
                                    [$approvalLabel, $approvalVariant] = [
                                        'draft'    => ['Entwurf', 'muted'],
                                        'review'   => ['In Prüfung', 'warning'],
                                        'approved' => ['Freigegeben', 'success'],
                                    ][$item->approval_status] ?? ['Entwurf', 'muted'];
                                @endphp
                                <x-ui-badge :variant="$approvalVariant" size="xs">{{ $approvalLabel }}</x-ui-badge>

                                @if ($item->is_vegan)
                                    <x-ui-badge variant="success" size="xs">Vegan</x-ui-badge>
                                @elseif ($item->is_vegetarian)
                                    <x-ui-badge variant="success" size="xs">Vegetarisch</x-ui-badge>
                                @endif
                                @if ($item->is_alcoholic)
                                    <x-ui-badge variant="info" size="xs">18+</x-ui-badge>
                                @endif
                                @if (!$item->available)
                                    <x-ui-badge variant="muted" size="xs">Nicht verfügbar</x-ui-badge>
                                @endif
                            </div>
                            @if ($item->description)
                                <p class="text-xs text-[var(--ui-muted)] m-0 mt-0.5">{{ $item->description }}</p>
                            @endif
                            @if ($item->allergens->isNotEmpty() || $item->additives->isNotEmpty())
                                <p class="text-[11px] text-[var(--ui-muted)] m-0 mt-0.5">
                                    {{ $item->allergens->pluck('code')->merge($item->additives->pluck('code'))->filter()->map(fn ($c) => "($c)")->implode(' ') }}
                                </p>
                            @endif
                        </div>
                        <div class="ml-4 flex shrink-0 items-center justify-end gap-1.5">
                            <span class="mr-2 whitespace-nowrap text-sm font-semibold tabular-nums text-[var(--ui-secondary)]">
                                {{ number_format($item->price, 2, ',', '.') }} €
                            </span>

                            @if ($item->approval_status === \Platform\Reservation\Models\MenuItem::APPROVAL_DRAFT)
                                <x-ui-button variant="secondary-outline" size="sm" wire:click="submitItemForReview({{ $item->id }})">Zur Prüfung</x-ui-button>
                            @elseif ($item->approval_status === \Platform\Reservation\Models\MenuItem::APPROVAL_REVIEW)
                                <x-ui-button variant="success" size="sm" wire:click="approveItem({{ $item->id }})">Freigeben</x-ui-button>
                            @elseif ($item->approval_status === \Platform\Reservation\Models\MenuItem::APPROVAL_APPROVED)
                                <div class="shrink-0">
                                    <x-ui-confirm-button
                                        action="resetItemApproval"
                                        :value="$item->id"
                                        text="Zurückziehen"
                                        confirmText="Sicher?"
                                        variant="secondary-outline"
                                        size="sm"
                                    />
                                </div>
                            @endif

                            <x-ui-button variant="secondary-outline" size="sm" :iconOnly="true" wire:click="openItemForm({{ $item->id }})" title="Bearbeiten">
                                @svg('heroicon-o-pencil', 'w-4 h-4')
                            </x-ui-button>
                            <div class="shrink-0">
                                <x-ui-confirm-button
                                    action="deleteItem"
                                    :value="$item->id"
                                    text=""
                                    confirmText="Wirklich löschen?"
                                    variant="danger-outline"
                                    size="sm"
                                    :icon="svg('heroicon-o-trash', 'w-4 h-4')->toHtml()"
                                />
                            </div>
                        </div>
                    </div>
                @endforeach

                @if ($category->menuItems->isEmpty())
                    <div class="flex flex-col items-center justify-center py-6 text-[var(--ui-muted)]">
                        @svg('heroicon-o-inbox', 'w-6 h-6 mb-1 opacity-40')
                        <span class="text-xs">{{ $approvalFilter !== '' ? 'Keine Artikel mit diesem Status.' : 'Keine Artikel vorhanden.' }}</span>
                    </div>
                @endif
            </div>
        </section>
    @endforeach

    {{-- Kategorie-Modal --}}
    <x-ui-modal size="sm" wire:model="showCategoryForm">
        <x-slot name="header">
            <div class="flex items-center gap-3">
                <div class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-[var(--ui-primary-10)] flex-shrink-0">
                    @svg('heroicon-o-tag', 'w-5 h-5 text-[var(--ui-primary)]')
                </div>
                <h3 class="text-base font-semibold text-[var(--ui-secondary)] m-0 leading-tight">
                    {{ $editingCategoryId ? 'Kategorie bearbeiten' : 'Neue Kategorie' }}
                </h3>
            </div>
        </x-slot>

        <div class="space-y-3">
            <x-ui-input-text name="categoryName" label="Name" wire:model="categoryName" required errorKey="categoryName" />
            <x-ui-input-textarea name="categoryDescription" label="Beschreibung" wire:model="categoryDescription" rows="2" />

            {{-- Kategoriebild (16:9) --}}
            <div>
                <label class="block text-[12px] font-medium text-[var(--ui-muted)] mb-1">Bild (16:9)</label>
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
            <div class="flex justify-end gap-2">
                <x-ui-button variant="secondary-outline" size="sm" wire:click="$set('showCategoryForm', false)">Abbrechen</x-ui-button>
                <x-ui-button variant="primary" size="sm" wire:click="saveCategory">Speichern</x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>

    {{-- Produkt-Modal --}}
    <x-ui-modal size="md" wire:model="showItemForm">
        <x-slot name="header">
            <div class="flex items-center gap-3">
                <div class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-[var(--ui-primary-10)] flex-shrink-0">
                    @svg('heroicon-o-cake', 'w-5 h-5 text-[var(--ui-primary)]')
                </div>
                <div class="min-w-0">
                    <h3 class="text-base font-semibold text-[var(--ui-secondary)] m-0 leading-tight">
                        {{ $editingItemId ? 'Artikel bearbeiten' : 'Neuer Artikel' }}
                    </h3>
                    <p class="text-[12px] text-[var(--ui-muted)] m-0 mt-0.5">Keine Pflichtfelder bei Allergenen/MwSt – Verantwortung beim Bearbeiter</p>
                </div>
            </div>
        </x-slot>

        <div class="space-y-4" x-data x-on:menu-item-form-reset.window="$nextTick(() => $refs.itemName?.querySelector('input')?.focus())">
            <x-ui-form-grid :cols="2" :gap="3">
                <div class="sm:col-span-2">
                    <x-ui-input-select
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
                    <x-ui-input-select
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
                        <p class="mt-1 text-[11px] text-[var(--ui-muted)]">Noch keine Stufen angelegt – unter <a href="{{ route('reservation.settings.holding-classes') }}" class="underline" wire:navigate>Einstellungen → Standzeit-Klassen</a>.</p>
                    @endif
                </div>
                <div class="sm:col-span-2" x-ref="itemName">
                    <x-ui-input-text name="itemName" label="Name" wire:model="itemName" required errorKey="itemName" />
                </div>
                <div class="sm:col-span-2">
                    <x-ui-input-textarea name="itemDescription" label="Beschreibung" wire:model="itemDescription" rows="2" />
                </div>
                <div class="sm:col-span-2">
                    <x-ui-input-text name="itemPortionSize" label="Portionsgröße" wire:model="itemPortionSize" placeholder="z.B. 0,2 l · 0,5 l · 250 g" errorKey="itemPortionSize" />
                </div>
                <x-ui-input-number name="itemPrice" label="Preis (€)" step="0.01" min="0" wire:model="itemPrice" required errorKey="itemPrice" />
                <x-ui-input-select
                    name="itemTaxRate"
                    label="MwSt. (%)"
                    :options="[
                        ['value' => '7.00', 'label' => '7 %'],
                        ['value' => '19.00', 'label' => '19 %'],
                        ['value' => '0.00', 'label' => '0 %'],
                    ]"
                    wire:model="itemTaxRate"
                />
            </x-ui-form-grid>

            {{-- Produktbild (1:1) --}}
            <div>
                <label class="block text-[12px] font-medium text-[var(--ui-muted)] mb-1">Produktbild (quadratisch)</label>
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
                    <label class="flex items-center gap-2 text-sm text-[var(--ui-secondary)] cursor-pointer">
                        <input wire:model.live="{{ $prop }}" type="checkbox" class="rounded border-[var(--ui-border)]" />
                        {{ $label }}
                    </label>
                @endforeach
            </div>

            <x-ui-form-grid :cols="2" :gap="3">
                {{-- Altersgrenze --}}
                <x-ui-input-select
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
                    <x-ui-input-number name="itemCaffeineMg" label="Koffeingehalt (mg/100 ml)" step="0.1" min="0" wire:model="itemCaffeineMg" placeholder="z. B. 32,0" errorKey="itemCaffeineMg" />
                @endif
            </x-ui-form-grid>

            {{-- Allergene --}}
            <div>
                <label class="block text-[12px] font-medium text-[var(--ui-muted)] mb-1">Allergene</label>
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
                <label class="block text-[12px] font-medium text-[var(--ui-muted)] mb-1">Zusatzstoffe</label>
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
            <div class="flex justify-end gap-2">
                <x-ui-button variant="secondary-outline" size="sm" wire:click="$set('showItemForm', false)">Abbrechen</x-ui-button>
                @unless ($editingItemId)
                    <x-ui-button variant="primary-outline" size="sm" wire:click="saveItem(true)">Speichern &amp; Neu</x-ui-button>
                @endunless
                <x-ui-button variant="primary" size="sm" wire:click="saveItem">Speichern</x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>

    </div>
    </x-ui-page-container>
</x-ui-page>
