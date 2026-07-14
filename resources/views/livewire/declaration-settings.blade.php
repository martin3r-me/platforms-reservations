<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Allergene & Zusatzstoffe" icon="heroicon-o-beaker" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Allergene & Zusatzstoffe'],
        ]">
            <x-ui-button variant="secondary-outline" size="sm" wire:click="loadStandard"
                wire:confirm="Fehlende Einträge der Standard-Legende ergänzen? Vorhandene bleiben unverändert.">
                @svg('heroicon-o-arrow-path', 'w-4 h-4')
                <span>Standard-Legende ergänzen</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
    <div class="pt-4 space-y-4">

    @if (session('decl_message'))
        <div class="rounded-lg border border-[var(--ui-success)]/30 bg-[var(--ui-success-10)] p-3 text-sm text-[var(--ui-success)]">
            {{ session('decl_message') }}
        </div>
    @endif

    <p class="text-sm text-[var(--ui-muted)] m-0">
        Diese Listen werden in den Artikeln zur Auswahl angeboten und beim Gast angezeigt. Änderungen wirken sofort.
    </p>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {{-- Allergene --}}
        <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
                @svg('heroicon-o-exclamation-triangle', 'w-4 h-4 text-[var(--ui-warning)]')
                <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Allergene</h2>
                <span class="ml-auto text-[11px] text-[var(--ui-muted)]">{{ $this->allergens->count() }}</span>
            </div>

            {{-- Neu anlegen --}}
            <div class="flex items-end gap-2 border-b border-[var(--ui-border)]/30 p-3">
                <div class="w-20">
                    <x-ui-input-text name="newAllergenCode" label="Code" size="sm" wire:model="newAllergenCode" placeholder="A1" errorKey="newAllergenCode" />
                </div>
                <div class="flex-1">
                    <x-ui-input-text name="newAllergenName" label="Bezeichnung" size="sm" wire:model="newAllergenName" placeholder="enthält Weizen" errorKey="newAllergenName" />
                </div>
                <x-ui-button variant="primary" size="sm" wire:click="addAllergen">Hinzufügen</x-ui-button>
            </div>

            <div class="divide-y divide-[var(--ui-border)]/30">
                @forelse ($this->allergens as $a)
                    <div wire:key="al-{{ $a->id }}" class="flex items-center gap-2 px-4 py-2 text-sm">
                        @if ($editingAllergenId === $a->id)
                            <input wire:model="editAllergenCode" class="w-16 rounded-md border border-[var(--ui-border)] px-2 py-1 text-sm dark:bg-gray-800 dark:text-white" />
                            <input wire:model="editAllergenName" class="flex-1 rounded-md border border-[var(--ui-border)] px-2 py-1 text-sm dark:bg-gray-800 dark:text-white" />
                            <x-ui-button variant="primary" size="sm" wire:click="updateAllergen">Speichern</x-ui-button>
                            <x-ui-button variant="secondary-ghost" size="sm" wire:click="$set('editingAllergenId', null)">Abbrechen</x-ui-button>
                        @else
                            <span class="w-12 shrink-0 font-mono text-xs font-semibold text-[var(--ui-warning)]">{{ $a->code }}</span>
                            <span class="flex-1 text-[var(--ui-secondary)]">{{ $a->name }}</span>
                            <x-ui-button variant="secondary-ghost" size="sm" :iconOnly="true" wire:click="editAllergen({{ $a->id }})" title="Bearbeiten">
                                @svg('heroicon-o-pencil', 'w-4 h-4')
                            </x-ui-button>
                            <div class="shrink-0">
                                <x-ui-confirm-button action="deleteAllergen" :value="$a->id" text="" confirmText="Löschen?" variant="danger-outline" size="sm" :icon="svg('heroicon-o-trash','w-4 h-4')->toHtml()" />
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-xs text-[var(--ui-muted)]">Noch keine Allergene.</p>
                @endforelse
            </div>
        </section>

        {{-- Zusatzstoffe --}}
        <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
                @svg('heroicon-o-beaker', 'w-4 h-4 text-[var(--ui-info)]')
                <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Zusatzstoffe</h2>
                <span class="ml-auto text-[11px] text-[var(--ui-muted)]">{{ $this->additives->count() }}</span>
            </div>

            <div class="flex items-end gap-2 border-b border-[var(--ui-border)]/30 p-3">
                <div class="w-20">
                    <x-ui-input-text name="newAdditiveCode" label="Code" size="sm" wire:model="newAdditiveCode" placeholder="1.1" errorKey="newAdditiveCode" />
                </div>
                <div class="flex-1">
                    <x-ui-input-text name="newAdditiveName" label="Bezeichnung" size="sm" wire:model="newAdditiveName" placeholder="Zuckerkulör E150d" errorKey="newAdditiveName" />
                </div>
                <x-ui-button variant="primary" size="sm" wire:click="addAdditive">Hinzufügen</x-ui-button>
            </div>

            <div class="divide-y divide-[var(--ui-border)]/30">
                @forelse ($this->additives as $z)
                    <div wire:key="ad-{{ $z->id }}" class="flex items-center gap-2 px-4 py-2 text-sm">
                        @if ($editingAdditiveId === $z->id)
                            <input wire:model="editAdditiveCode" class="w-16 rounded-md border border-[var(--ui-border)] px-2 py-1 text-sm dark:bg-gray-800 dark:text-white" />
                            <input wire:model="editAdditiveName" class="flex-1 rounded-md border border-[var(--ui-border)] px-2 py-1 text-sm dark:bg-gray-800 dark:text-white" />
                            <x-ui-button variant="primary" size="sm" wire:click="updateAdditive">Speichern</x-ui-button>
                            <x-ui-button variant="secondary-ghost" size="sm" wire:click="$set('editingAdditiveId', null)">Abbrechen</x-ui-button>
                        @else
                            <span class="w-12 shrink-0 font-mono text-xs font-semibold text-[var(--ui-info)]">{{ $z->code }}</span>
                            <span class="flex-1 text-[var(--ui-secondary)]">{{ $z->name }}</span>
                            <x-ui-button variant="secondary-ghost" size="sm" :iconOnly="true" wire:click="editAdditive({{ $z->id }})" title="Bearbeiten">
                                @svg('heroicon-o-pencil', 'w-4 h-4')
                            </x-ui-button>
                            <div class="shrink-0">
                                <x-ui-confirm-button action="deleteAdditive" :value="$z->id" text="" confirmText="Löschen?" variant="danger-outline" size="sm" :icon="svg('heroicon-o-trash','w-4 h-4')->toHtml()" />
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-xs text-[var(--ui-muted)]">Noch keine Zusatzstoffe.</p>
                @endforelse
            </div>
        </section>
    </div>

    </div>
    </x-ui-page-container>
</x-ui-page>
