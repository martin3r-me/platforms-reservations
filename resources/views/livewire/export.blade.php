<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Export" icon="heroicon-o-arrow-down-tray" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Export'],
        ]" />
    </x-slot>

    <x-ui-page-container>
    <div class="pt-4 space-y-4">

    <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
            @svg('heroicon-o-funnel', 'w-4 h-4 text-[var(--ui-muted)]')
            <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Zeitraum &amp; Filter</h2>
        </div>
        <div class="p-5 space-y-4">
            <x-ui-form-grid :cols="2" :gap="3">
                <x-ui-input-date name="dateFrom" label="Von" wire:model.live="dateFrom" />
                <x-ui-input-date name="dateTo" label="Bis" wire:model.live="dateTo" />
                <x-ui-input-select
                    name="filterStatus"
                    label="Status"
                    :options="[
                        ['value' => 'pending', 'label' => 'Ausstehend'],
                        ['value' => 'confirmed', 'label' => 'Bestätigt'],
                        ['value' => 'cancelled', 'label' => 'Storniert'],
                        ['value' => 'no_show', 'label' => 'No-Show'],
                        ['value' => 'completed', 'label' => 'Abgeschlossen'],
                    ]"
                    :nullable="true"
                    nullLabel="Alle Status"
                    wire:model.live="filterStatus"
                />
                <x-ui-input-select
                    name="format"
                    label="Format"
                    :options="[
                        ['value' => 'csv', 'label' => 'CSV (Excel)'],
                        ['value' => 'json', 'label' => 'JSON'],
                    ]"
                    wire:model="format"
                />
            </x-ui-form-grid>

            <div class="flex items-center justify-between rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 p-3">
                <p class="text-sm text-[var(--ui-muted)] m-0">
                    <strong class="text-[var(--ui-secondary)] tabular-nums">{{ $this->previewCount }}</strong>
                    Buchungen im Zeitraum
                </p>
                <x-ui-button variant="primary" size="sm" wire:click="export" :disabled="$this->previewCount === 0">
                    @svg('heroicon-o-arrow-down-tray', 'w-4 h-4')
                    <span>Exportieren</span>
                </x-ui-button>
            </div>
        </div>
    </section>

    {{-- Felder-Übersicht --}}
    <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
            @svg('heroicon-o-table-cells', 'w-4 h-4 text-[var(--ui-muted)]')
            <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Exportierte Felder</h2>
        </div>
        <div class="p-5">
            <div class="flex flex-wrap gap-1.5">
                @foreach(['Buchungs-ID', 'Datum', 'Uhrzeit', 'Tisch', 'Venue', 'Gast', 'E-Mail', 'Telefon', 'Personen', 'Status', 'Betrag', 'Zahlungsart', 'Mollie-ID', 'Steuersatz', 'Erstellt'] as $field)
                    <x-ui-badge variant="muted" size="xs">{{ $field }}</x-ui-badge>
                @endforeach
            </div>
        </div>
    </section>

    </div>
    </x-ui-page-container>
</x-ui-page>
