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

    <x-ui-page-container>
    <div class="pt-4 space-y-4">

    @if (session('hc_message'))
        <div class="rounded-lg border border-[var(--ui-success)]/30 bg-[var(--ui-success-10)] p-3 text-sm text-[var(--ui-success)]">
            {{ session('hc_message') }}
        </div>
    @endif

    <p class="text-sm text-[var(--ui-muted)] m-0">
        Standzeit-/Zeitkritikalitäts-Stufen (z. B. „Unbedenklich", „Sollte kalt sein", „Sollte heiß sein"). Sie werden im Artikel zugewiesen; die <strong>Vorlaufzeit</strong> (Minuten vor Pausenbeginn) bestimmt Ziel-Uhrzeit und Reihenfolge der Laufrunde im Function Sheet. <strong>Leer = egal</strong> (zeitunkritisch, vorab platzierbar).
    </p>

    <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
            @svg('heroicon-o-fire', 'w-4 h-4 text-[var(--ui-primary)]')
            <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Stufen</h2>
            <span class="ml-auto text-[11px] text-[var(--ui-muted)]">{{ $this->classes->count() }}</span>
        </div>

        {{-- Neu anlegen --}}
        <div class="flex items-end gap-2 border-b border-[var(--ui-border)]/30 p-3">
            <div class="w-12">
                <label class="block text-[11px] font-medium text-[var(--ui-muted)] mb-1">Farbe</label>
                <input type="color" wire:model="newColor" class="h-9 w-full cursor-pointer rounded-md border border-[var(--ui-border)] bg-white p-1" />
            </div>
            <div class="w-48">
                <x-ui-input-text name="newName" label="Bezeichnung" size="sm" wire:model="newName" placeholder="Sollte heiß sein" errorKey="newName" />
            </div>
            <div class="flex-1">
                <x-ui-input-text name="newDescription" label="Beschreibung (optional)" size="sm" wire:model="newDescription" placeholder="Kühlt schnell aus …" errorKey="newDescription" />
            </div>
            <div class="w-28">
                <x-ui-input-text type="number" name="newLeadTime" label="Vorlauf (Min.)" size="sm" wire:model="newLeadTime" placeholder="leer = egal" errorKey="newLeadTime" />
            </div>
            <x-ui-button variant="primary" size="sm" wire:click="add">Hinzufügen</x-ui-button>
        </div>

        <div class="divide-y divide-[var(--ui-border)]/30">
            @forelse ($this->classes as $index => $c)
                <div wire:key="hc-{{ $c->id }}" class="flex items-center gap-2 px-4 py-2 text-sm">
                    @if ($editingId === $c->id)
                        <input type="color" wire:model="editColor" class="h-8 w-10 shrink-0 cursor-pointer rounded-md border border-[var(--ui-border)] bg-white p-0.5" />
                        <input wire:model="editName" class="w-44 rounded-md border border-[var(--ui-border)] px-2 py-1 text-sm dark:bg-gray-800 dark:text-white" />
                        <input wire:model="editDescription" class="flex-1 rounded-md border border-[var(--ui-border)] px-2 py-1 text-sm dark:bg-gray-800 dark:text-white" />
                        <input type="number" wire:model="editLeadTime" placeholder="egal" title="Vorlaufzeit in Minuten (leer = egal)" class="w-20 shrink-0 rounded-md border border-[var(--ui-border)] px-2 py-1 text-sm dark:bg-gray-800 dark:text-white" />
                        <x-ui-button variant="primary" size="sm" wire:click="update">Speichern</x-ui-button>
                        <x-ui-button variant="secondary-ghost" size="sm" wire:click="cancelEdit">Abbrechen</x-ui-button>
                    @else
                        <span class="inline-block h-4 w-4 shrink-0 rounded-full border border-black/10" style="background: {{ $c->color ?: '#94a3b8' }}"></span>
                        <div class="min-w-0 flex-1">
                            <span class="font-medium text-[var(--ui-secondary)]">{{ $c->name }}</span>
                            @if ($c->description)
                                <span class="ml-2 text-xs text-[var(--ui-muted)]">{{ $c->description }}</span>
                            @endif
                        </div>
                        <span class="shrink-0 rounded-full bg-[var(--ui-muted-5)] px-2 py-0.5 text-[11px] font-medium text-[var(--ui-muted)]" title="Vorlaufzeit vor Pausenbeginn">
                            {{ $c->lead_time_minutes !== null ? $c->lead_time_minutes . ' min vor' : 'egal' }}
                        </span>
                        <span class="shrink-0 text-[11px] text-[var(--ui-muted)]" title="zugeordnete Artikel">{{ $c->menu_items_count }} Art.</span>
                        <div class="flex shrink-0 items-center">
                            <x-ui-button variant="secondary-ghost" size="sm" :iconOnly="true" wire:click="moveUp({{ $c->id }})" title="Nach oben" :disabled="$index === 0">
                                @svg('heroicon-o-chevron-up', 'w-4 h-4')
                            </x-ui-button>
                            <x-ui-button variant="secondary-ghost" size="sm" :iconOnly="true" wire:click="moveDown({{ $c->id }})" title="Nach unten" :disabled="$index === $this->classes->count() - 1">
                                @svg('heroicon-o-chevron-down', 'w-4 h-4')
                            </x-ui-button>
                        </div>
                        <x-ui-button variant="secondary-ghost" size="sm" :iconOnly="true" wire:click="edit({{ $c->id }})" title="Bearbeiten">
                            @svg('heroicon-o-pencil', 'w-4 h-4')
                        </x-ui-button>
                        <div class="shrink-0">
                            <x-ui-confirm-button action="delete" :value="$c->id" text="" confirmText="Löschen? Zugeordnete Artikel verlieren nur die Zuordnung." variant="danger-outline" size="sm" :icon="svg('heroicon-o-trash','w-4 h-4')->toHtml()" />
                        </div>
                    @endif
                </div>
            @empty
                <p class="px-4 py-6 text-center text-xs text-[var(--ui-muted)]">Noch keine Stufen. Lege oben welche an oder nutze „Standard-Stufen anlegen".</p>
            @endforelse
        </div>
    </section>

    </div>
    </x-ui-page-container>
</x-ui-page>
