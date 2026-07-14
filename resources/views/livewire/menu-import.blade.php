<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Produkt-Import" icon="heroicon-o-arrow-up-tray" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Produkte', 'href' => route('reservation.menu.index')],
            ['label' => 'Import'],
        ]" />
    </x-slot>

    <x-ui-page-container>
    <div class="pt-4 space-y-4">

    {{-- Erfolgs-Ergebnis --}}
    @if ($createdCount !== null)
        <section class="rounded-xl border border-[var(--ui-success)]/30 bg-[var(--ui-success-10)] p-4">
            <p class="font-semibold text-[var(--ui-success)] m-0">
                Import abgeschlossen: {{ $createdCount }} Produkte angelegt{{ $skippedCount ? ", {$skippedCount} übersprungen" : '' }}.
            </p>
            <p class="mt-1 text-sm text-[var(--ui-secondary)] m-0">
                Alle importierten Produkte stehen auf „Entwurf“ und durchlaufen die Vier-Augen-Freigabe.
            </p>
            <div class="mt-3 flex gap-2">
                <x-ui-button variant="primary" size="sm" :href="route('reservation.menu.index')" wire:navigate>Zu den Produkten</x-ui-button>
                <x-ui-button variant="secondary-outline" size="sm" wire:click="resetImport">Weitere Datei importieren</x-ui-button>
            </div>
        </section>
    @endif

    {{-- Upload --}}
    @if (empty($previewRows) && $createdCount === null)
        <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
                @svg('heroicon-o-arrow-up-tray', 'w-4 h-4 text-[var(--ui-muted)]')
                <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">CSV-Datei hochladen</h2>
            </div>
            <div class="p-5 space-y-4">
                <p class="text-sm text-[var(--ui-muted)] m-0">
                    Es wird nichts gespeichert, bevor du die Vorschau bestätigst (Dry-Run).
                </p>

                <input type="file" wire:model="csvFile" accept=".csv,.txt"
                    class="w-full text-sm text-[var(--ui-muted)]" />
                <div wire:loading wire:target="csvFile" class="text-xs text-[var(--ui-muted)]">Datei wird gelesen…</div>
                @error('csvFile') <p class="text-xs text-[var(--ui-danger)] m-0">{{ $message }}</p> @enderror

                {{-- Format-Hilfe --}}
                <div class="rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 p-3 text-xs text-[var(--ui-muted)]">
                    <p class="font-semibold mb-1 m-0">Erwartete Spalten (Kopfzeile, Trennzeichen ; oder ,):</p>
                    <code>name; beschreibung; kategorie; preis; mwst; allergene; zusatzstoffe; vegetarisch; vegan; alkohol; verfuegbar</code>
                    <p class="mt-2 m-0">Allergene als Buchstaben („A,C,G“), Zusatzstoffe als Nummern („1,2“) gemäß Legende.
                    Preise mit Komma oder Punkt. Ja/Nein-Spalten: ja/nein bzw. 1/0.</p>
                    <p class="mt-3 m-0">
                        <a href="{{ route('reservation.menu.import.sample') }}"
                            class="inline-flex items-center gap-1 font-medium text-[var(--ui-primary)] hover:underline">
                            @svg('heroicon-o-arrow-down-tray', 'w-4 h-4')
                            Beispiel-Vorlage herunterladen (.csv)
                        </a>
                    </p>
                </div>
            </div>
        </section>
    @endif

    @foreach ($parseErrors as $error)
        <div class="rounded-lg border border-[var(--ui-danger)]/30 bg-[var(--ui-danger-10)] p-3 text-sm text-[var(--ui-danger)]">{{ $error }}</div>
    @endforeach

    {{-- Dry-Run-Vorschau --}}
    @if (!empty($previewRows))
        @php
            $okCount = collect($previewRows)->filter(fn ($r) => $r['status'] !== 'error' && !$r['duplicate'])->count();
            $warningCount = collect($previewRows)->where('status', 'warning')->count();
            $errorCount = count($previewRows) - $okCount;
        @endphp

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-[var(--ui-muted)] m-0">
                <strong class="text-[var(--ui-secondary)]">{{ count($previewRows) }}</strong> Zeilen gelesen –
                <span class="text-[var(--ui-success)]">{{ $okCount }} importierbar</span>@if($warningCount), <span class="text-[var(--ui-warning)]">{{ $warningCount }} mit Warnungen</span>@endif@if($errorCount), <span class="text-[var(--ui-danger)]">{{ $errorCount }} übersprungen</span>@endif
            </p>
            <div class="flex gap-2">
                <x-ui-button variant="secondary-outline" size="sm" wire:click="resetImport">Abbrechen</x-ui-button>
                <x-ui-button variant="primary" size="sm" wire:click="import" wire:loading.attr="disabled" :disabled="$okCount === 0">
                    {{ $okCount }} Produkte importieren
                </x-ui-button>
            </div>
        </div>

        <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
            <x-ui-table compact="true">
                <x-ui-table-header>
                    <x-ui-table-header-cell compact="true">Zeile</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Name</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Kategorie</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true" align="right">Preis</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true" align="right">MwSt.</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Hinweise</x-ui-table-header-cell>
                </x-ui-table-header>
                <x-ui-table-body>
                    @foreach ($previewRows as $row)
                        <x-ui-table-row compact="true" wire:key="row-{{ $row['line'] }}">
                            <x-ui-table-cell compact="true">{{ $row['line'] }}</x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                @if ($row['status'] === 'error' || $row['duplicate'])
                                    <x-ui-badge variant="danger" size="xs">Übersprungen</x-ui-badge>
                                @elseif ($row['status'] === 'warning')
                                    <x-ui-badge variant="warning" size="xs">Warnung</x-ui-badge>
                                @else
                                    <x-ui-badge variant="success" size="xs">OK</x-ui-badge>
                                @endif
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <span class="font-medium text-[var(--ui-secondary)]">{{ $row['name'] ?: '—' }}</span>
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true">{{ $row['category'] }}</x-ui-table-cell>
                            <x-ui-table-cell compact="true" align="right">
                                <span class="whitespace-nowrap tabular-nums">{{ number_format((float) $row['price'], 2, ',', '.') }} €</span>
                            </x-ui-table-cell>
                            <x-ui-table-cell compact="true" align="right">{{ rtrim(rtrim($row['tax_rate'], '0'), '.') }} %</x-ui-table-cell>
                            <x-ui-table-cell compact="true">
                                <span class="text-xs text-[var(--ui-muted)]">{{ implode(' ', $row['messages']) }}</span>
                            </x-ui-table-cell>
                        </x-ui-table-row>
                    @endforeach
                </x-ui-table-body>
            </x-ui-table>
        </section>
    @endif

    </div>
    </x-ui-page-container>
</x-ui-page>
