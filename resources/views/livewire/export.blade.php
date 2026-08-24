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
            {{-- Schnellzeiträume. Dasselbe Muster wie in den Finanzen, damit man
                 es nicht zweimal lernen muss. --}}
            <div class="flex flex-wrap items-center gap-1 rounded-[8px] border border-[color:var(--nx-line)] bg-[color:var(--nx-bg)] p-1">
                @foreach (\Platform\Reservation\Livewire\Export::presets() as $preset => $label)
                    <button type="button" wire:click="setPreset('{{ $preset }}')"
                        class="inline-flex h-7 items-center rounded-[6px] px-3 text-xs font-medium transition-colors
                            {{ $activePreset === $preset ? 'bg-[color:var(--nx-surface)] font-semibold text-[color:var(--nx-text)]' : 'bg-transparent text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

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
                        ['value' => 'datev', 'label' => 'DATEV (Buchungsstapel)'],
                    ]"
                    wire:model.live="format"
                />
            </div>

            @if ($format === 'datev')
                @php ($fehlt = $this->settings->datevMissing())
                @if ($fehlt)
                    <x-nx-callout variant="warning" title="Angaben fehlen">
                        Für den DATEV-Export fehlen: {{ implode(', ', $fehlt) }}.
                        Die Werte werden in den <a href="{{ route('reservation.settings.checkout') }}" class="underline">Einstellungen</a> gepflegt.
                    </x-nx-callout>
                @else
                    <x-nx-callout>
                        Ausgegeben werden <strong>bestätigte und abgeschlossene</strong> Buchungen – der Statusfilter
                        wirkt hier nicht, in die Buchhaltung gehören keine ausstehenden oder stornierten Umsätze.
                        Gebucht wird je Steuersatz auf
                        {{ $this->settings->datev_erloes_7 }} (7 %) und {{ $this->settings->datev_erloes_19 }} (19 %),
                        Gegenkonto {{ $this->settings->datev_geldkonto }},
                        {{ $this->settings->datev_modus === 'tagessumme' ? 'als Tagessummen' : 'je Buchung einzeln' }}.
                    </x-nx-callout>
                @endif
            @endif

            @if ($exportError)
                <x-nx-callout variant="danger">{{ $exportError }}</x-nx-callout>
            @endif

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

    {{-- Vorschau auf den Buchungsstapel. Nur bei DATEV: Bei CSV und JSON sieht
         man die Datei in Excel, hier kann sie niemand lesen. --}}
    @if ($format === 'datev' && $this->settings->datevReady())
        @php ($vorschau = $this->datevPreview)
        <x-nx-card flush>
            <div class="flex flex-wrap items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
                @svg('heroicon-o-eye', 'w-4 h-4 text-[color:var(--nx-muted)]')
                <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Vorschau</h2>
                <span class="ml-auto text-[11px] text-[color:var(--nx-muted)]">
                    {{ $vorschau['buchungen'] }} {{ $vorschau['buchungen'] === 1 ? 'Buchung' : 'Buchungen' }}
                    → {{ count($vorschau['saetze']) }} {{ count($vorschau['saetze']) === 1 ? 'Buchungssatz' : 'Buchungssätze' }}
                </span>
            </div>

            @if ($vorschau['saetze'])
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-[color:var(--nx-line)] bg-[color:var(--nx-hover)] text-left text-[11px] uppercase tracking-wide text-[color:var(--nx-muted)]">
                                <th class="px-4 py-2 font-medium">Datum</th>
                                <th class="px-4 py-2 text-right font-medium">Umsatz</th>
                                <th class="px-4 py-2 font-medium">S/H</th>
                                <th class="px-4 py-2 font-medium">Konto</th>
                                <th class="px-4 py-2 font-medium">Gegenkonto</th>
                                <th class="px-4 py-2 font-medium">Beleg</th>
                                <th class="px-4 py-2 font-medium">Buchungstext</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- Nur die ersten Zeilen: Die Vorschau soll zeigen, dass die
                                 Konten stimmen, nicht die Datei ersetzen. --}}
                            @foreach (array_slice($vorschau['saetze'], 0, 10) as $satz)
                                <tr class="border-b border-[color:var(--nx-line)] last:border-0">
                                    <td class="whitespace-nowrap px-4 py-2 tabular-nums text-[color:var(--nx-muted)]">
                                        {{ substr($satz['belegdatum'], 0, 2) }}.{{ substr($satz['belegdatum'], 2, 2) }}.
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right tabular-nums text-[color:var(--nx-text)]">{{ $satz['umsatz'] }} €</td>
                                    <td class="px-4 py-2 text-[color:var(--nx-muted)]">{{ $satz['soll_haben'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 tabular-nums text-[color:var(--nx-text)]">{{ $satz['konto'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 tabular-nums text-[color:var(--nx-text)]">{{ $satz['gegenkonto'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 tabular-nums text-[color:var(--nx-muted)]">{{ $satz['belegfeld1'] }}</td>
                                    <td class="px-4 py-2 text-[color:var(--nx-muted)]">{{ $satz['buchungstext'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if (count($vorschau['saetze']) > 10)
                    <p class="m-0 border-b border-[color:var(--nx-line)] px-4 py-2 text-[11px] text-[color:var(--nx-muted)]">
                        … und {{ count($vorschau['saetze']) - 10 }} weitere Sätze in der Datei.
                    </p>
                @endif

                <div class="flex items-center justify-between px-4 py-3">
                    <span class="text-sm font-medium text-[color:var(--nx-text)]">Summe</span>
                    <span class="whitespace-nowrap text-sm font-semibold tabular-nums text-[color:var(--nx-text)]">
                        {{ number_format($vorschau['summe'], 2, ',', '.') }} €
                    </span>
                </div>
                <p class="m-0 border-t border-[color:var(--nx-line)] px-4 py-2 text-[11px] text-[color:var(--nx-muted)]">
                    Diese Summe muss mit dem Umsatz auf der Finanzen-Seite für denselben Zeitraum übereinstimmen –
                    das ist die Prüfung, die die Kanzlei als Erstes macht.
                </p>
            @else
                <div class="px-4 py-6 text-sm text-[color:var(--nx-muted)]">
                    Im gewählten Zeitraum gibt es keine bestätigten oder abgeschlossenen Buchungen –
                    es gäbe nichts zu buchen.
                </div>
            @endif
        </x-nx-card>
    @endif

    {{-- Felder-Übersicht --}}
    <x-nx-card flush>
        <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
            @svg('heroicon-o-table-cells', 'w-4 h-4 text-[color:var(--nx-muted)]')
            <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Exportierte Felder</h2>
        </div>
        <div class="p-5">
            <div class="flex flex-wrap gap-1.5">
                @foreach($format === 'datev'
                    ? ['Umsatz', 'Soll/Haben', 'WKZ', 'Konto', 'Gegenkonto', 'Belegdatum', 'Belegfeld 1', 'Buchungstext']
                    : ['Buchungs-ID', 'Datum', 'Uhrzeit', 'Tisch', 'Venue', 'Gast', 'E-Mail', 'Telefon', 'Personen', 'Status', 'Betrag', 'Zahlungsart', 'Mollie-ID', 'Steuersatz', 'Erstellt'] as $field)
                    <x-nx-badge >{{ $field }}</x-nx-badge>
                @endforeach
            </div>
        </div>
    </x-nx-card>

    </div>
    </x-ui-page-container>
</x-ui-page>
