<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Verkaufslisten" icon="heroicon-o-queue-list" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Verkaufslisten'],
        ]">
            <x-nx-button variant="primary" wire:click="openListForm()">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Verkaufsliste</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
    <div class="space-y-5">

    @if (session('sales_list_message'))
        <x-nx-callout variant="success">{{ session('sales_list_message') }}</x-nx-callout>
    @endif

    @if ($this->salesLists->isEmpty())
        <x-nx-card>
            <x-nx-empty icon="heroicon-o-queue-list">
                <span class="text-sm font-medium text-[color:var(--nx-text)]">Noch keine Verkaufsliste</span>
                <span class="mt-1 block">Verkaufslisten sind segmentierte Sortimente (z.&nbsp;B. Konzert, Kantine), die Terminen zugewiesen werden.</span>
                <x-slot name="action">
                    <x-nx-button variant="primary" wire:click="openListForm()">
                        @svg('heroicon-o-plus', 'w-4 h-4')
                        <span>Verkaufsliste erstellen</span>
                    </x-nx-button>
                </x-slot>
            </x-nx-empty>
        </x-nx-card>
    @else
        <x-nx-card flush>
            <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
                @svg('heroicon-o-queue-list', 'w-4 h-4 text-[color:var(--nx-muted)]')
                <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Listen</h2>
                <span class="ml-auto text-xs tabular-nums text-[color:var(--nx-faint)]">{{ $this->salesLists->count() }}</span>
            </div>
            <div>
                @foreach ($this->salesLists as $list)
                    <div wire:key="list-{{ $list->id }}" class="group flex items-center justify-between gap-3 border-b border-[color:var(--nx-line)] px-4 py-2.5 transition-colors last:border-0 hover:bg-[color:var(--nx-hover)]">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-medium text-[color:var(--nx-text)]">{{ $list->name }}</span>
                                @if ($list->is_default)
                                    <x-nx-badge variant="accent">Team-Standard</x-nx-badge>
                                @endif
                                <span class="text-xs text-[color:var(--nx-faint)]">{{ $list->menu_items_count }} Artikel</span>
                            </div>
                            @if ($list->description)
                                <p class="m-0 mt-0.5 text-xs text-[color:var(--nx-muted)]">{{ $list->description }}</p>
                            @endif
                        </div>
                        <div class="flex shrink-0 items-center justify-end gap-1">
                            <x-nx-button variant="primary" wire:click="openAssignment({{ $list->id }})">Artikel zuordnen</x-nx-button>
                            <div class="flex items-center gap-0.5 opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100">
                                <x-nx-button icon variant="ghost" wire:click="openListForm({{ $list->id }})" title="Bearbeiten">
                                    @svg('heroicon-o-pencil', 'w-4 h-4')
                                </x-nx-button>
                                <button type="button" wire:click="deleteList({{ $list->id }})" wire:confirm="Verkaufsliste wirklich löschen?" title="Löschen"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-[6px] text-[color:var(--nx-danger)] transition-colors hover:bg-[rgba(224,49,49,.08)]">
                                    @svg('heroicon-o-trash', 'w-4 h-4')
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-nx-card>

        {{-- Raum-Defaults --}}
        @if ($this->floorPlans->isNotEmpty())
            <x-nx-card flush>
                <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
                    @svg('heroicon-o-building-storefront', 'w-4 h-4 text-[color:var(--nx-muted)]')
                    <div>
                        <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Standard-Liste pro Raum</h2>
                        <p class="m-0 mt-0.5 text-[11px] text-[color:var(--nx-muted)]">Vorbelegung beim Anlegen eines Termins für diesen Raum.</p>
                    </div>
                </div>
                <div>
                    @foreach ($this->floorPlans as $plan)
                        <div wire:key="plan-{{ $plan->id }}" class="flex items-center justify-between gap-3 border-b border-[color:var(--nx-line)] px-4 py-2.5 last:border-0">
                            <span class="text-sm text-[color:var(--nx-text)]">
                                {{ $plan->venue?->name }} – {{ $plan->name }}
                            </span>
                            <div class="w-52">
                                <x-nx-input-select
                                    name="plan-default-{{ $plan->id }}"
                                    size="sm"
                                    :options="$this->salesLists"
                                    optionValue="id"
                                    optionLabel="name"
                                    :nullable="true"
                                    nullLabel="– keine –"
                                    :value="$plan->default_sales_list_id"
                                    wire:change="setFloorPlanDefault({{ $plan->id }}, $event.target.value)"
                                />
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-nx-card>
        @endif
    @endif

    {{-- Modal: Liste anlegen/bearbeiten --}}
    <x-nx-modal size="sm" wire:model="showListForm">
        <x-slot name="header">
            <h3 class="m-0 text-base font-semibold leading-tight text-[color:var(--nx-text)]">
                {{ $editingListId ? 'Verkaufsliste bearbeiten' : 'Neue Verkaufsliste' }}
            </h3>
        </x-slot>

        <div class="space-y-3">
            <x-nx-input-text name="listName" label="Name" wire:model="listName" placeholder="z.B. Konzert, Kantine …" required errorKey="listName" />
            <x-nx-input-textarea name="listDescription" label="Beschreibung" wire:model="listDescription" rows="2" />
            <x-nx-input-checkbox wire:model="listIsDefault" label="Team-Standard (Fallback, wenn ein Termin keine Liste hat)" />
        </div>

        <x-slot name="footer">
            <x-nx-button wire:click="$set('showListForm', false)">Abbrechen</x-nx-button>
            <x-nx-button variant="primary" wire:click="saveList">Speichern</x-nx-button>
        </x-slot>
    </x-nx-modal>

    {{-- Modal: Artikel zuordnen --}}
    <x-nx-modal size="md" wire:model="showAssignForm">
        <x-slot name="header">
            <h3 class="m-0 text-base font-semibold leading-tight text-[color:var(--nx-text)]">Artikel zuordnen</h3>
            <p class="m-0 mt-1 text-xs text-[color:var(--nx-muted)]">{{ count($assignedItemIds) }} ausgewählt</p>
        </x-slot>

        <div class="space-y-4">
            <x-nx-input-text name="itemSearch" size="sm" wire:model.live.debounce.300ms="itemSearch" placeholder="Artikel suchen…" />

            @if ($this->categoriesWithItems->isNotEmpty())
                <div class="flex items-center justify-between">
                    <button type="button" wire:click="toggleAllVisible"
                        class="text-xs font-medium text-[color:var(--nx-text)] hover:underline">
                        {{ $this->allVisibleSelected() ? 'Alle abwählen' : 'Alle auswählen' }}
                        @if ($itemSearch !== '') <span class="text-[color:var(--nx-muted)]">(Suchergebnisse)</span> @endif
                    </button>
                    <span class="text-[11px] text-[color:var(--nx-muted)]">{{ count($assignedItemIds) }} ausgewählt</span>
                </div>
            @endif

            <div class="max-h-[50vh] space-y-4 overflow-y-auto pr-1">
                @forelse ($this->categoriesWithItems as $category)
                    <div wire:key="assign-cat-{{ $category->id }}">
                        <p class="m-0 mb-1.5 text-xs font-semibold text-[color:var(--nx-muted)]">
                            {{ $category->name }}
                        </p>
                        <div class="space-y-1">
                            @foreach ($category->menuItems as $item)
                                <label wire:key="assign-item-{{ $item->id }}"
                                    class="flex cursor-pointer items-center justify-between gap-2 rounded-[8px] border px-3 py-2 text-sm transition-colors
                                    {{ in_array((string) $item->id, $assignedItemIds) ? 'border-[color:var(--nx-text)] bg-[color:var(--nx-accent-soft)]' : 'border-[color:var(--nx-line)] hover:bg-[color:var(--nx-hover)]' }}">
                                    <span class="flex items-center gap-2 text-[color:var(--nx-text)]">
                                        <input type="checkbox" wire:model.live="assignedItemIds" value="{{ $item->id }}" class="h-4 w-4 rounded-[4px] accent-[var(--nx-accent)]" />
                                        {{ $item->name }}
                                    </span>
                                    <span class="whitespace-nowrap text-xs tabular-nums text-[color:var(--nx-muted)]">{{ number_format($item->price, 2, ',', '.') }} €</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <x-nx-empty icon="heroicon-o-inbox">Keine Artikel gefunden</x-nx-empty>
                @endforelse
            </div>
        </div>

        <x-slot name="footer">
            <x-nx-button wire:click="$set('showAssignForm', false)">Abbrechen</x-nx-button>
            <x-nx-button variant="primary" wire:click="saveAssignment">Speichern</x-nx-button>
        </x-slot>
    </x-nx-modal>

    </div>
    </x-ui-page-container>
</x-ui-page>
