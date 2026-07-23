<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Allergene & Zusatzstoffe" icon="heroicon-o-beaker" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Allergene & Zusatzstoffe'],
        ]">
            <x-nx-button wire:click="loadStandard"
                wire:confirm="Fehlende Einträge der Standard-Legende ergänzen? Vorhandene bleiben unverändert.">
                @svg('heroicon-o-arrow-path', 'w-4 h-4')
                <span>Standard-Legende ergänzen</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
    <div class="space-y-5">

    @if (session('decl_message'))
        <x-nx-callout variant="success">{{ session('decl_message') }}</x-nx-callout>
    @endif

    <p class="m-0 text-sm text-[color:var(--nx-muted)]">
        Diese Listen werden in den Artikeln zur Auswahl angeboten und beim Gast angezeigt. Änderungen wirken sofort.
    </p>

    @php $declField = 'rounded-[6px] border border-[color:var(--nx-line-strong)] bg-[color:var(--nx-surface)] px-2 py-1 text-sm text-[color:var(--nx-text)] focus:border-[color:var(--nx-accent)] focus:outline-none focus:ring-1 focus:ring-[color:var(--nx-accent)]'; @endphp

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {{-- Allergene --}}
        <x-nx-card flush>
            <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
                @svg('heroicon-o-exclamation-triangle', 'w-4 h-4', ['style' => 'color:var(--nx-warning)'])
                <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Allergene</h2>
                <span class="ml-auto text-xs tabular-nums text-[color:var(--nx-faint)]">{{ $this->allergens->count() }}</span>
            </div>

            {{-- Neu anlegen --}}
            <div class="flex items-end gap-2 border-b border-[color:var(--nx-line)] p-3">
                <div class="w-20">
                    <x-nx-input-text name="newAllergenCode" label="Code" size="sm" wire:model="newAllergenCode" placeholder="A1" errorKey="newAllergenCode" />
                </div>
                <div class="flex-1">
                    <x-nx-input-text name="newAllergenName" label="Bezeichnung" size="sm" wire:model="newAllergenName" placeholder="enthält Weizen" errorKey="newAllergenName" />
                </div>
                <x-nx-button variant="primary" wire:click="addAllergen">Hinzufügen</x-nx-button>
            </div>

            <div>
                @forelse ($this->allergens as $a)
                    <div wire:key="al-{{ $a->id }}" class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-2 text-sm last:border-0">
                        @if ($editingAllergenId === $a->id)
                            <input wire:model="editAllergenCode" class="w-16 {{ $declField }}" />
                            <input wire:model="editAllergenName" class="flex-1 {{ $declField }}" />
                            <x-nx-button variant="primary" wire:click="updateAllergen">Speichern</x-nx-button>
                            <x-nx-button variant="ghost" wire:click="$set('editingAllergenId', null)">Abbrechen</x-nx-button>
                        @else
                            <span class="w-12 shrink-0 font-mono text-xs font-semibold" style="color:var(--nx-warning)">{{ $a->code }}</span>
                            <span class="flex-1 text-[color:var(--nx-text)]">{{ $a->name }}</span>
                            <x-nx-button icon variant="ghost" wire:click="editAllergen({{ $a->id }})" title="Bearbeiten">
                                @svg('heroicon-o-pencil', 'w-4 h-4')
                            </x-nx-button>
                            <button type="button" wire:click="deleteAllergen({{ $a->id }})" wire:confirm="Löschen?" title="Löschen"
                                class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-[6px] text-[color:var(--nx-danger)] transition-colors hover:bg-[rgba(224,49,49,.08)]">
                                @svg('heroicon-o-trash', 'w-4 h-4')
                            </button>
                        @endif
                    </div>
                @empty
                    <x-nx-empty icon="heroicon-o-exclamation-triangle">Noch keine Allergene.</x-nx-empty>
                @endforelse
            </div>
        </x-nx-card>

        {{-- Zusatzstoffe --}}
        <x-nx-card flush>
            <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
                @svg('heroicon-o-beaker', 'w-4 h-4', ['style' => 'color:var(--nx-info)'])
                <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Zusatzstoffe</h2>
                <span class="ml-auto text-xs tabular-nums text-[color:var(--nx-faint)]">{{ $this->additives->count() }}</span>
            </div>

            <div class="flex items-end gap-2 border-b border-[color:var(--nx-line)] p-3">
                <div class="w-20">
                    <x-nx-input-text name="newAdditiveCode" label="Code" size="sm" wire:model="newAdditiveCode" placeholder="1.1" errorKey="newAdditiveCode" />
                </div>
                <div class="flex-1">
                    <x-nx-input-text name="newAdditiveName" label="Bezeichnung" size="sm" wire:model="newAdditiveName" placeholder="Zuckerkulör E150d" errorKey="newAdditiveName" />
                </div>
                <x-nx-button variant="primary" wire:click="addAdditive">Hinzufügen</x-nx-button>
            </div>

            <div>
                @forelse ($this->additives as $z)
                    <div wire:key="ad-{{ $z->id }}" class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-2 text-sm last:border-0">
                        @if ($editingAdditiveId === $z->id)
                            <input wire:model="editAdditiveCode" class="w-16 {{ $declField }}" />
                            <input wire:model="editAdditiveName" class="flex-1 {{ $declField }}" />
                            <x-nx-button variant="primary" wire:click="updateAdditive">Speichern</x-nx-button>
                            <x-nx-button variant="ghost" wire:click="$set('editingAdditiveId', null)">Abbrechen</x-nx-button>
                        @else
                            <span class="w-12 shrink-0 font-mono text-xs font-semibold" style="color:var(--nx-info)">{{ $z->code }}</span>
                            <span class="flex-1 text-[color:var(--nx-text)]">{{ $z->name }}</span>
                            <x-nx-button icon variant="ghost" wire:click="editAdditive({{ $z->id }})" title="Bearbeiten">
                                @svg('heroicon-o-pencil', 'w-4 h-4')
                            </x-nx-button>
                            <button type="button" wire:click="deleteAdditive({{ $z->id }})" wire:confirm="Löschen?" title="Löschen"
                                class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-[6px] text-[color:var(--nx-danger)] transition-colors hover:bg-[rgba(224,49,49,.08)]">
                                @svg('heroicon-o-trash', 'w-4 h-4')
                            </button>
                        @endif
                    </div>
                @empty
                    <x-nx-empty icon="heroicon-o-beaker">Noch keine Zusatzstoffe.</x-nx-empty>
                @endforelse
            </div>
        </x-nx-card>
    </div>

    </div>
    </x-ui-page-container>
</x-ui-page>
