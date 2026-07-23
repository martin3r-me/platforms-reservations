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

    <x-ui-page-container width="contained">
    <div class="space-y-5">

    <x-nx-card flush>
        <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
            @svg('heroicon-o-funnel', 'w-4 h-4 text-[color:var(--nx-muted)]')
            <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Zeitraum &amp; Filter</h2>
        </div>
        <div class="p-5 space-y-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <x-nx-input-date name="dateFrom" label="Von" wire:model.live="dateFrom" />
                <x-nx-input-date name="dateTo" label="Bis" wire:model.live="dateTo" />
                <x-nx-input-select
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
                <x-nx-input-select
                    name="format"
                    label="Format"
                    :options="[
                        ['value' => 'csv', 'label' => 'CSV (Excel)'],
                        ['value' => 'json', 'label' => 'JSON'],
                    ]"
                    wire:model="format"
                />
            </div>

            <div class="flex items-center justify-between rounded-[8px] border border-[color:var(--nx-line)] bg-[color:var(--nx-bg)] p-3">
                <p class="text-sm text-[color:var(--nx-muted)] m-0">
                    <strong class="text-[color:var(--nx-text)] tabular-nums">{{ $this->previewCount }}</strong>
                    Buchungen im Zeitraum
                </p>
                <x-nx-button variant="primary" wire:click="export" :disabled="$this->previewCount === 0">
                    @svg('heroicon-o-arrow-down-tray', 'w-4 h-4')
                    <span>Exportieren</span>
                </x-nx-button>
            </div>
        </div>
    </x-nx-card>

    {{-- Felder-Übersicht --}}
    <x-nx-card flush>
        <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
            @svg('heroicon-o-table-cells', 'w-4 h-4 text-[color:var(--nx-muted)]')
            <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Exportierte Felder</h2>
        </div>
        <div class="p-5">
            <div class="flex flex-wrap gap-1.5">
                @foreach(['Buchungs-ID', 'Datum', 'Uhrzeit', 'Tisch', 'Venue', 'Gast', 'E-Mail', 'Telefon', 'Personen', 'Status', 'Betrag', 'Zahlungsart', 'Mollie-ID', 'Steuersatz', 'Erstellt'] as $field)
                    <x-nx-badge >{{ $field }}</x-nx-badge>
                @endforeach
            </div>
        </div>
    </x-nx-card>

    </div>
    </x-ui-page-container>
</x-ui-page>
