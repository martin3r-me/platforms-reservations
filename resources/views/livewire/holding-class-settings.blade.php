<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Standzeit-Klassen" icon="heroicon-o-fire" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Standzeit-Klassen'],
        ]">
            <x-nx-button wire:click="loadStandard"
                wire:confirm="Die drei Standard-Stufen (erneut) anlegen?">
                @svg('heroicon-o-arrow-path', 'w-4 h-4')
                <span>Standard-Stufen anlegen</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
    <div class="space-y-5">

    @if (session('hc_message'))
        <x-nx-callout variant="success">{{ session('hc_message') }}</x-nx-callout>
    @endif

    <p class="m-0 text-sm text-[color:var(--nx-muted)]">
        Standzeit-/Zeitkritikalitäts-Stufen (z. B. „Unbedenklich", „Sollte kalt sein", „Sollte heiß sein"). Sie werden im Artikel zugewiesen; die <strong class="text-[color:var(--nx-text)]">Vorlaufzeit</strong> (Minuten vor Pausenbeginn) bestimmt Ziel-Uhrzeit und Reihenfolge der Laufrunde. <strong class="text-[color:var(--nx-text)]">Leer = egal</strong> (zeitunkritisch, vorab platzierbar).
    </p>

    <x-nx-card flush>
        <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
            @svg('heroicon-o-fire', 'w-4 h-4 text-[color:var(--nx-muted)]')
            <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Stufen</h2>
            <span class="ml-auto text-xs tabular-nums text-[color:var(--nx-faint)]">{{ $this->classes->count() }}</span>
        </div>

        {{-- Neu anlegen --}}
        <div class="flex flex-wrap items-end gap-2 border-b border-[color:var(--nx-line)] p-3">
            <div class="w-12">
                <label class="mb-1 block text-[11px] font-medium text-[color:var(--nx-text)]">Farbe</label>
                <input type="color" wire:model="newColor" class="h-9 w-full cursor-pointer rounded-[6px] border border-[color:var(--nx-line-strong)] bg-[color:var(--nx-surface)] p-1" />
            </div>
            <div class="w-48">
                <x-nx-input-text name="newName" label="Bezeichnung" size="sm" wire:model="newName" placeholder="Sollte heiß sein" errorKey="newName" />
            </div>
            <div class="min-w-[160px] flex-1">
                <x-nx-input-text name="newDescription" label="Beschreibung (optional)" size="sm" wire:model="newDescription" placeholder="Kühlt schnell aus …" errorKey="newDescription" />
            </div>
            <div class="w-28">
                <x-nx-input-text type="number" name="newLeadTime" label="Vorlauf (Min.)" size="sm" wire:model="newLeadTime" placeholder="leer = egal" errorKey="newLeadTime" />
            </div>
            <x-nx-button variant="primary" wire:click="add">Hinzufügen</x-nx-button>
        </div>

        <div>
            @forelse ($this->classes as $index => $c)
                <div wire:key="hc-{{ $c->id }}" class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-2 text-sm last:border-0">
                    @if ($editingId === $c->id)
                        @php $fieldCls = 'rounded-[6px] border border-[color:var(--nx-line-strong)] bg-[color:var(--nx-surface)] px-2 py-1 text-sm text-[color:var(--nx-text)] focus:border-[color:var(--nx-accent)] focus:outline-none focus:ring-1 focus:ring-[color:var(--nx-accent)]'; @endphp
                        <input type="color" wire:model="editColor" class="h-8 w-10 shrink-0 cursor-pointer rounded-[6px] border border-[color:var(--nx-line-strong)] bg-[color:var(--nx-surface)] p-0.5" />
                        <input wire:model="editName" class="w-44 {{ $fieldCls }}" />
                        <input wire:model="editDescription" class="flex-1 {{ $fieldCls }}" />
                        <input type="number" wire:model="editLeadTime" placeholder="egal" title="Vorlaufzeit in Minuten (leer = egal)" class="w-20 shrink-0 {{ $fieldCls }}" />
                        <x-nx-button variant="primary" wire:click="update">Speichern</x-nx-button>
                        <x-nx-button variant="ghost" wire:click="cancelEdit">Abbrechen</x-nx-button>
                    @else
                        <span class="inline-block h-4 w-4 shrink-0 rounded-full" style="background:{{ $c->color ?: '#9b9a97' }}"></span>
                        <div class="min-w-0 flex-1">
                            <span class="font-medium text-[color:var(--nx-text)]">{{ $c->name }}</span>
                            @if ($c->description)
                                <span class="ml-2 text-xs text-[color:var(--nx-muted)]">{{ $c->description }}</span>
                            @endif
                        </div>
                        <span class="shrink-0 rounded-full bg-[color:var(--nx-accent-soft)] px-2 py-0.5 text-[11px] font-medium text-[color:var(--nx-muted)]" title="Vorlaufzeit vor Pausenbeginn">
                            {{ $c->lead_time_minutes !== null ? $c->lead_time_minutes . ' min vor' : 'egal' }}
                        </span>
                        <span class="shrink-0 text-[11px] text-[color:var(--nx-faint)]" title="zugeordnete Artikel">{{ $c->menu_items_count }} Art.</span>
                        <div class="flex shrink-0 items-center">
                            <x-nx-button icon variant="ghost" wire:click="moveUp({{ $c->id }})" title="Nach oben" :disabled="$index === 0">
                                @svg('heroicon-o-chevron-up', 'w-4 h-4')
                            </x-nx-button>
                            <x-nx-button icon variant="ghost" wire:click="moveDown({{ $c->id }})" title="Nach unten" :disabled="$index === $this->classes->count() - 1">
                                @svg('heroicon-o-chevron-down', 'w-4 h-4')
                            </x-nx-button>
                        </div>
                        <x-nx-button icon variant="ghost" wire:click="edit({{ $c->id }})" title="Bearbeiten">
                            @svg('heroicon-o-pencil', 'w-4 h-4')
                        </x-nx-button>
                        <button type="button" wire:click="delete({{ $c->id }})" wire:confirm="Löschen? Zugeordnete Artikel verlieren nur die Zuordnung." title="Löschen"
                            class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-[6px] text-[color:var(--nx-danger)] transition-colors hover:bg-[rgba(224,49,49,.08)]">
                            @svg('heroicon-o-trash', 'w-4 h-4')
                        </button>
                    @endif
                </div>
            @empty
                <x-nx-empty icon="heroicon-o-fire">Noch keine Stufen. Lege oben welche an oder nutze „Standard-Stufen anlegen".</x-nx-empty>
            @endforelse
        </div>
    </x-nx-card>

    </div>
    </x-ui-page-container>
</x-ui-page>
