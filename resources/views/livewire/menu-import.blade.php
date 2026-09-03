<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Artikel-Import" icon="heroicon-o-arrow-up-tray" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Artikel', 'href' => route('reservation.menu.index')],
            ['label' => 'Import'],
        ]" />
    </x-slot>

    <x-ui-page-container width="contained">
    <div class="space-y-5">

    {{-- Erfolgs-Ergebnis --}}
    @if ($createdCount !== null)
        <x-nx-callout variant="success" :title="'Import abgeschlossen: ' . $createdCount . ' Artikel angelegt' . ($skippedCount ? ', ' . $skippedCount . ' übersprungen' : '') . '.'">
            Alle importierten Artikel stehen auf „Entwurf" und durchlaufen die Vier-Augen-Freigabe.
            <x-slot name="action">
                <div class="flex gap-2">
                    <x-nx-button variant="primary" :href="route('reservation.menu.index')" wire:navigate>Zu den Artikeln</x-nx-button>
                    <x-nx-button wire:click="resetImport">Weitere Datei importieren</x-nx-button>
                </div>
            </x-slot>
        </x-nx-callout>
    @endif

    {{-- Upload --}}
    @if (empty($previewRows) && $createdCount === null)
        <x-nx-card flush>
            <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
                @svg('heroicon-o-arrow-up-tray', 'w-4 h-4 text-[color:var(--nx-muted)]')
                <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">CSV-Datei hochladen</h2>
            </div>
            <div class="p-5 space-y-4">
                <p class="text-sm text-[color:var(--nx-muted)] m-0">
                    Es wird nichts gespeichert, bevor du die Vorschau bestätigst (Dry-Run).
                </p>

                {{-- Dieselbe Dropzone wie bei den Saalplan-Bildern.

                     Hier stand das nackte <input type="file">: ein Knopf im
                     Systemstil mit "Keine ausgewaehlt" daneben, mitten in einer
                     Seite, die sonst durchgestaltet ist. Und es liess sich nicht
                     bewerfen - ausgerechnet bei einer Datei, die man aus dem
                     Dateimanager zieht.

                     Der Baustein ist bewusst nicht auf Bilder beschraenkt; er
                     nimmt accept und die Texte entgegen. Ein zweiter fuer CSV
                     waere dieselbe Dropzone mit einem anderen Symbol. --}}
                @include('reservation::partials.datei-upload', [
                    'model'      => 'csvFile',
                    'accept'     => '.csv,.txt,text/csv',
                    'text'       => 'CSV-Datei hierher ziehen oder',
                    'buttonText' => 'Datei auswählen',
                    'hint'       => 'CSV oder TXT, Trennzeichen ; oder ,',
                ])

                {{-- Format-Hilfe --}}
                <div class="rounded-[8px] border border-[color:var(--nx-line)] bg-[color:var(--nx-bg)] p-3 text-xs text-[color:var(--nx-muted)]">
                    <p class="font-semibold mb-1 m-0">Erwartete Spalten (Kopfzeile, Trennzeichen ; oder ,):</p>
                    <code>name; beschreibung; portionsgroesse; kategorie; preis; mwst; allergene; zusatzstoffe; vegetarisch; vegan; alkohol; verfuegbar; standzeit; altersgrenze; koffein; koffein_mg</code>
                    <p class="mt-2 m-0">Allergene als Buchstaben („A,C,G“), Zusatzstoffe als Nummern („1,2“) gemäß Legende.
                    Preise mit Komma oder Punkt. Ja/Nein-Spalten: ja/nein bzw. 1/0.</p>
                    {{-- Nur „name“ ist Pflicht. Der Satz stand nirgends, und ohne ihn
                         sieht die Liste oben nach fünfzehn Pflichtfeldern aus. --}}
                    <p class="mt-2 m-0"><strong>Nur „name“ ist Pflicht</strong> – jede andere Spalte darf fehlen oder leer bleiben.
                    <em>standzeit</em> ist der Name einer vorhandenen Standzeit-Klasse (z.&nbsp;B. „Sollte kalt sein“); unbekannte
                    werden gemeldet und übersprungen, angelegt wird keine. <em>altersgrenze</em> ist 16 oder 18.
                    <em>koffein</em> (ja/nein) blendet beim Gast den Pflichthinweis ein; steht in
                    <em>koffein_mg</em> ein Gehalt (mg je 100&nbsp;ml), erscheint er in Klammern dahinter – die Spalte darf
                    leer bleiben.</p>
                    <p class="mt-3 m-0">
                        <a href="{{ route('reservation.menu.import.sample') }}"
                            class="inline-flex items-center gap-1 font-medium text-[color:var(--nx-text)] hover:underline">
                            @svg('heroicon-o-arrow-down-tray', 'w-4 h-4')
                            Beispiel-Vorlage herunterladen (.csv)
                        </a>
                    </p>
                </div>
            </div>
        </x-nx-card>
    @endif

    @foreach ($parseErrors as $error)
        <x-nx-callout variant="danger">{{ $error }}</x-nx-callout>
    @endforeach

    {{-- Dry-Run-Vorschau --}}
    @if (!empty($previewRows))
        @php
            $okCount = collect($previewRows)->filter(fn ($r) => $r['status'] !== 'error' && !$r['duplicate'])->count();
            $warningCount = collect($previewRows)->where('status', 'warning')->count();
            $errorCount = count($previewRows) - $okCount;
        @endphp

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-[color:var(--nx-muted)] m-0">
                <strong class="text-[color:var(--nx-text)]">{{ count($previewRows) }}</strong> Zeilen gelesen –
                <span class="text-[color:var(--nx-success)]">{{ $okCount }} importierbar</span>{{ $warningCount ? ',' : '' }}@if($warningCount) <span class="text-[color:var(--nx-warning)]">{{ $warningCount }} mit Warnungen</span>@endif{{ $errorCount ? ',' : '' }}@if($errorCount) <span class="text-[color:var(--nx-danger)]">{{ $errorCount }} übersprungen</span>@endif
            </p>
            <div class="flex gap-2">
                <x-nx-button wire:click="resetImport">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="import" wire:loading.attr="disabled" :disabled="$okCount === 0">
                    {{ $okCount }} Artikel importieren
                </x-nx-button>
            </div>
        </div>

        <x-nx-card flush>
            <x-nx-table compact>
                <x-nx-table-header>
                    <x-nx-table-header-cell compact>Zeile</x-nx-table-header-cell>
                    <x-nx-table-header-cell compact>Status</x-nx-table-header-cell>
                    <x-nx-table-header-cell compact>Name</x-nx-table-header-cell>
                    <x-nx-table-header-cell compact>Kategorie</x-nx-table-header-cell>
                    <x-nx-table-header-cell compact align="right">Preis</x-nx-table-header-cell>
                    <x-nx-table-header-cell compact align="right">MwSt.</x-nx-table-header-cell>
                    <x-nx-table-header-cell compact>Hinweise</x-nx-table-header-cell>
                </x-nx-table-header>
                <x-nx-table-body>
                    @foreach ($previewRows as $row)
                        <x-nx-table-row compact wire:key="row-{{ $row['line'] }}">
                            <x-nx-table-cell compact>{{ $row['line'] }}</x-nx-table-cell>
                            <x-nx-table-cell compact>
                                @if ($row['status'] === 'error' || $row['duplicate'])
                                    <x-nx-badge variant="danger">Übersprungen</x-nx-badge>
                                @elseif ($row['status'] === 'warning')
                                    <x-nx-badge variant="warning">Warnung</x-nx-badge>
                                @else
                                    <x-nx-badge variant="success">OK</x-nx-badge>
                                @endif
                            </x-nx-table-cell>
                            <x-nx-table-cell compact>
                                <span class="font-medium text-[color:var(--nx-text)]">{{ $row['name'] ?: '—' }}</span>
                            </x-nx-table-cell>
                            <x-nx-table-cell compact>{{ $row['category'] }}</x-nx-table-cell>
                            <x-nx-table-cell compact align="right">
                                <span class="whitespace-nowrap tabular-nums">{{ number_format((float) $row['price'], 2, ',', '.') }} €</span>
                            </x-nx-table-cell>
                            <x-nx-table-cell compact align="right">{{ rtrim(rtrim($row['tax_rate'], '0'), '.') }} %</x-nx-table-cell>
                            <x-nx-table-cell compact>
                                <span class="text-xs text-[color:var(--nx-muted)]">{{ implode(' ', $row['messages']) }}</span>
                            </x-nx-table-cell>
                        </x-nx-table-row>
                    @endforeach
                </x-nx-table-body>
            </x-nx-table>
        </x-nx-card>
    @endif

    </div>
    </x-ui-page-container>
</x-ui-page>
