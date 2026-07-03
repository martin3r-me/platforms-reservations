<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Import aus Alt-System" icon="heroicon-o-arrow-down-on-square" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Venues & Tischpläne', 'href' => route('reservation.venues.index')],
            ['label' => 'Import aus Alt-System'],
        ]" />
    </x-slot>

    <x-ui-page-container>
    <div class="pt-4 space-y-4 max-w-2xl">

    {{-- Ergebnis --}}
    @if ($result)
        <section class="rounded-xl border border-[var(--ui-success)]/30 bg-[var(--ui-success-10)] p-4">
            <p class="font-semibold text-[var(--ui-success)] m-0">
                Import abgeschlossen: {{ $result['created'] }} {{ $result['created'] === 1 ? 'Raum' : 'Räume' }} mit
                {{ $result['tables'] }} Tischen angelegt{{ $result['skipped'] ? ", {$result['skipped']} übersprungen (Name existierte bereits)" : '' }}.
            </p>
            <div class="mt-3 flex gap-2">
                <x-ui-button variant="primary" size="sm" :href="route('reservation.venues.index')" wire:navigate>Zu Venues & Tischplänen</x-ui-button>
                <x-ui-button variant="secondary-outline" size="sm" wire:click="resetImport">Weiteren Import</x-ui-button>
            </div>
        </section>
    @endif

    {{-- Schritt 1: Quelle --}}
    @if (!$result)
        <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
                @svg('heroicon-o-globe-alt', 'w-4 h-4 text-[var(--ui-muted)]')
                <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Alt-System</h2>
            </div>
            <div class="p-5 space-y-4">
                <p class="text-sm text-[var(--ui-muted)] m-0">
                    Übernimmt <strong>Räume samt Tischplänen</strong> (Positionen &amp; Kapazitäten) aus dem alten
                    Guestofy-System. Termine und Produkte werden separat gepflegt.
                </p>
                <x-ui-input-text
                    name="sourceUrl"
                    label="URL des Alt-Systems"
                    wire:model="sourceUrl"
                    placeholder="https://….guestofy.events"
                    errorKey="sourceUrl"
                />
                <div class="flex justify-end">
                    <x-ui-button variant="primary" size="sm" wire:click="fetchPreview" wire:loading.attr="disabled" wire:target="fetchPreview">
                        <span wire:loading.remove wire:target="fetchPreview">Räume abrufen</span>
                        <span wire:loading wire:target="fetchPreview">Wird abgerufen…</span>
                    </x-ui-button>
                </div>
            </div>
        </section>
    @endif

    {{-- Schritt 2: Vorschau + Ziel --}}
    @if (!empty($previewRooms))
        <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
                @svg('heroicon-o-building-storefront', 'w-4 h-4 text-[var(--ui-muted)]')
                <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Gefundene Räume</h2>
                <span class="ml-auto text-[11px] text-[var(--ui-muted)]">{{ count($previewRooms) }}</span>
            </div>
            <div class="divide-y divide-[var(--ui-border)]/30">
                @foreach ($previewRooms as $room)
                    <div class="flex items-center justify-between px-4 py-2.5 text-sm">
                        <span class="font-medium text-[var(--ui-secondary)]">{{ $room['name'] }}</span>
                        <span class="text-[var(--ui-muted)]">{{ count($room['tables']) }} {{ count($room['tables']) === 1 ? 'Tisch' : 'Tische' }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
                @svg('heroicon-o-arrow-down-tray', 'w-4 h-4 text-[var(--ui-muted)]')
                <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Ziel-Venue</h2>
            </div>
            <div class="p-5 space-y-4">
                @if ($this->venues->isNotEmpty())
                    <x-ui-input-select
                        name="venueId"
                        label="Bestehendes Venue"
                        :options="$this->venues"
                        optionValue="id"
                        optionLabel="name"
                        :nullable="true"
                        nullLabel="– neues Venue anlegen –"
                        wire:model="venueId"
                    />
                @endif
                @if (!$venueId)
                    <x-ui-input-text
                        name="newVenueName"
                        label="Name des neuen Venues"
                        wire:model="newVenueName"
                        placeholder="z.B. Stadthalle"
                        errorKey="newVenueName"
                    />
                @endif

                <div class="flex justify-end gap-2">
                    <x-ui-button variant="secondary-outline" size="sm" wire:click="resetImport">Abbrechen</x-ui-button>
                    <x-ui-button variant="primary" size="sm" wire:click="import">Räume importieren</x-ui-button>
                </div>
            </div>
        </section>
    @endif

    </div>
    </x-ui-page-container>
</x-ui-page>
