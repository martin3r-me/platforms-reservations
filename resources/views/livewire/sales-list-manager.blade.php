<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Verkaufslisten" icon="heroicon-o-queue-list" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Verkaufslisten'],
        ]">
            <x-ui-button variant="primary" size="sm" wire:click="openListForm()">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Verkaufsliste</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
    <div class="pt-4 space-y-4">

    @if (session('sales_list_message'))
        <div class="rounded-lg border border-[var(--ui-success)]/30 bg-[var(--ui-success-10)] p-3 text-sm text-[var(--ui-success)]">
            {{ session('sales_list_message') }}
        </div>
    @endif

    @if ($this->salesLists->isEmpty())
        <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm">
            <div class="flex flex-col items-center justify-center py-16 text-[var(--ui-muted)]">
                @svg('heroicon-o-queue-list', 'w-10 h-10 mb-3 opacity-40')
                <span class="text-sm font-medium text-[var(--ui-secondary)]">Noch keine Verkaufsliste</span>
                <span class="text-xs mt-1 opacity-70">Verkaufslisten sind segmentierte Sortimente (z.&nbsp;B. Konzert, Kantine), die Terminen zugewiesen werden.</span>
                <div class="mt-4">
                    <x-ui-button variant="primary" size="sm" wire:click="openListForm()">
                        @svg('heroicon-o-plus', 'w-4 h-4')
                        <span>Verkaufsliste erstellen</span>
                    </x-ui-button>
                </div>
            </div>
        </section>
    @else
        <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
                @svg('heroicon-o-queue-list', 'w-4 h-4 text-[var(--ui-muted)]')
                <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Listen</h2>
                <span class="ml-auto text-[11px] text-[var(--ui-muted)]">{{ $this->salesLists->count() }}</span>
            </div>
            <div class="divide-y divide-[var(--ui-border)]/30">
                @foreach ($this->salesLists as $list)
                    <div wire:key="list-{{ $list->id }}" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-[var(--ui-muted-5)] transition-colors">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-medium text-[var(--ui-secondary)]">{{ $list->name }}</span>
                                @if ($list->is_default)
                                    <x-ui-badge variant="primary" size="xs">Team-Standard</x-ui-badge>
                                @endif
                                <span class="text-[11px] text-[var(--ui-muted)]">{{ $list->menu_items_count }} Artikel</span>
                            </div>
                            @if ($list->description)
                                <p class="text-xs text-[var(--ui-muted)] m-0 mt-0.5">{{ $list->description }}</p>
                            @endif
                        </div>
                        <div class="flex shrink-0 items-center gap-1.5">
                            <x-ui-button variant="primary" size="sm" wire:click="openAssignment({{ $list->id }})">Artikel zuordnen</x-ui-button>
                            <x-ui-button variant="secondary-ghost" size="sm" wire:click="openListForm({{ $list->id }})">Bearbeiten</x-ui-button>
                            <x-ui-confirm-button
                                action="deleteList"
                                :value="$list->id"
                                text="Löschen"
                                confirmText="Verkaufsliste löschen? (Artikel bleiben erhalten)"
                                variant="danger"
                                size="sm"
                            />
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Raum-Defaults --}}
        @if ($this->floorPlans->isNotEmpty())
            <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
                    @svg('heroicon-o-building-storefront', 'w-4 h-4 text-[var(--ui-muted)]')
                    <div>
                        <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Standard-Liste pro Raum</h2>
                        <p class="text-[11px] text-[var(--ui-muted)] m-0 mt-0.5 normal-case tracking-normal">Vorbelegung beim Anlegen eines Termins für diesen Raum.</p>
                    </div>
                </div>
                <div class="divide-y divide-[var(--ui-border)]/30">
                    @foreach ($this->floorPlans as $plan)
                        <div wire:key="plan-{{ $plan->id }}" class="flex items-center justify-between gap-3 px-4 py-2.5">
                            <span class="text-sm text-[var(--ui-secondary)]">
                                {{ $plan->venue?->name }} – {{ $plan->name }}
                            </span>
                            <x-ui-input-select
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
                    @endforeach
                </div>
            </section>
        @endif
    @endif

    {{-- Modal: Liste anlegen/bearbeiten --}}
    <x-ui-modal size="sm" wire:model="showListForm">
        <x-slot name="header">
            <div class="flex items-center gap-3">
                <div class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-[var(--ui-primary-10)] flex-shrink-0">
                    @svg('heroicon-o-queue-list', 'w-5 h-5 text-[var(--ui-primary)]')
                </div>
                <h3 class="text-base font-semibold text-[var(--ui-secondary)] m-0 leading-tight">
                    {{ $editingListId ? 'Verkaufsliste bearbeiten' : 'Neue Verkaufsliste' }}
                </h3>
            </div>
        </x-slot>

        <div class="space-y-3">
            <x-ui-input-text name="listName" label="Name" wire:model="listName" placeholder="z.B. Konzert, Kantine …" required errorKey="listName" />
            <x-ui-input-textarea name="listDescription" label="Beschreibung" wire:model="listDescription" rows="2" />
            <label class="flex items-center gap-2 text-sm text-[var(--ui-secondary)] cursor-pointer">
                <input wire:model="listIsDefault" type="checkbox" class="rounded border-[var(--ui-border)]" />
                Team-Standard (Fallback, wenn ein Termin keine Liste hat)
            </label>
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-ui-button variant="secondary-outline" size="sm" wire:click="$set('showListForm', false)">Abbrechen</x-ui-button>
                <x-ui-button variant="primary" size="sm" wire:click="saveList">Speichern</x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>

    {{-- Modal: Artikel zuordnen --}}
    <x-ui-modal size="md" wire:model="showAssignForm">
        <x-slot name="header">
            <div class="flex items-center gap-3">
                <div class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-[var(--ui-primary-10)] flex-shrink-0">
                    @svg('heroicon-o-squares-plus', 'w-5 h-5 text-[var(--ui-primary)]')
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="text-base font-semibold text-[var(--ui-secondary)] m-0 leading-tight">Artikel zuordnen</h3>
                    <p class="text-[12px] text-[var(--ui-muted)] m-0 mt-0.5">{{ count($assignedItemIds) }} ausgewählt</p>
                </div>
            </div>
        </x-slot>

        <div class="space-y-4">
            <x-ui-input-text name="itemSearch" size="sm" wire:model.live.debounce.300ms="itemSearch" placeholder="Artikel suchen…" />

            <div class="max-h-[50vh] space-y-4 overflow-y-auto pr-1">
                @forelse ($this->categoriesWithItems as $category)
                    <div wire:key="assign-cat-{{ $category->id }}">
                        <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">
                            {{ $category->name }}
                        </p>
                        <div class="space-y-1">
                            @foreach ($category->menuItems as $item)
                                <label wire:key="assign-item-{{ $item->id }}"
                                    class="flex cursor-pointer items-center justify-between gap-2 rounded-lg border px-3 py-2 text-sm transition-colors
                                    {{ in_array((string) $item->id, $assignedItemIds) ? 'border-[var(--ui-primary)]/40 bg-[var(--ui-primary-10)]' : 'border-[var(--ui-border)]/60 hover:bg-[var(--ui-muted-5)]' }}">
                                    <span class="flex items-center gap-2 text-[var(--ui-secondary)]">
                                        <input type="checkbox" wire:model.live="assignedItemIds" value="{{ $item->id }}" class="rounded border-[var(--ui-border)]" />
                                        {{ $item->name }}
                                    </span>
                                    <span class="text-xs tabular-nums text-[var(--ui-muted)]">{{ number_format($item->price, 2, ',', '.') }} €</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center py-8 text-[var(--ui-muted)]">
                        @svg('heroicon-o-inbox', 'w-8 h-8 mb-2 opacity-40')
                        <span class="text-xs">Keine Artikel gefunden</span>
                    </div>
                @endforelse
            </div>
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-ui-button variant="secondary-outline" size="sm" wire:click="$set('showAssignForm', false)">Abbrechen</x-ui-button>
                <x-ui-button variant="primary" size="sm" wire:click="saveAssignment">Speichern</x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>

    </div>
    </x-ui-page-container>
</x-ui-page>
