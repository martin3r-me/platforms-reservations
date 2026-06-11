<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Artikel-Import" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Menü', 'href' => route('reservation.menu.index')],
            ['label' => 'Import'],
        ]" />
    </x-slot>

    <x-ui-page-container>
    <div class="pt-4 space-y-6">

    {{-- Erfolgs-Ergebnis --}}
    @if ($createdCount !== null)
        <div class="rounded-xl border border-green-300 bg-green-50 p-4 dark:border-green-800 dark:bg-green-900/20">
            <p class="font-semibold text-green-800 dark:text-green-300">
                Import abgeschlossen: {{ $createdCount }} Artikel angelegt{{ $skippedCount ? ", {$skippedCount} übersprungen" : '' }}.
            </p>
            <p class="mt-1 text-sm text-green-700 dark:text-green-400">
                Alle importierten Artikel stehen auf „Entwurf“ und durchlaufen die Vier-Augen-Freigabe.
            </p>
            <div class="mt-3 flex gap-2">
                <a href="{{ route('reservation.menu.index') }}" wire:navigate
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Zur Menü-Verwaltung</a>
                <button wire:click="resetImport"
                    class="rounded-lg border px-4 py-2 text-sm dark:border-gray-700 dark:text-white">Weitere Datei importieren</button>
            </div>
        </div>
    @endif

    {{-- Upload --}}
    @if (empty($previewRows) && $createdCount === null)
        <div class="rounded-xl border p-4 space-y-4 dark:border-gray-700">
            <div>
                <h2 class="text-sm font-semibold dark:text-white">CSV-Datei hochladen</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Es wird nichts gespeichert, bevor du die Vorschau bestätigst (Dry-Run).
                </p>
            </div>

            <input type="file" wire:model="csvFile" accept=".csv,.txt"
                class="w-full text-sm text-gray-600 dark:text-gray-300" />
            <div wire:loading wire:target="csvFile" class="text-xs text-gray-500">Datei wird gelesen…</div>
            @error('csvFile') <p class="text-xs text-red-500">{{ $message }}</p> @enderror

            {{-- Format-Hilfe --}}
            <div class="rounded-lg bg-gray-50 p-3 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                <p class="font-semibold mb-1">Erwartete Spalten (Kopfzeile, Trennzeichen ; oder ,):</p>
                <code>name; beschreibung; kategorie; preis; mwst; allergene; zusatzstoffe; vegetarisch; vegan; alkohol; verfuegbar</code>
                <p class="mt-2">Allergene als Buchstaben („A,C,G“), Zusatzstoffe als Nummern („1,2“) gemäß Legende.
                Preise mit Komma oder Punkt. Ja/Nein-Spalten: ja/nein bzw. 1/0.</p>
            </div>
        </div>
    @endif

    @foreach ($parseErrors as $error)
        <div class="rounded-lg bg-red-100 p-3 text-sm text-red-800 dark:bg-red-900/30 dark:text-red-300">{{ $error }}</div>
    @endforeach

    {{-- Dry-Run-Vorschau --}}
    @if (!empty($previewRows))
        @php
            $okCount = collect($previewRows)->filter(fn ($r) => $r['status'] !== 'error' && !$r['duplicate'])->count();
            $warningCount = collect($previewRows)->where('status', 'warning')->count();
            $errorCount = count($previewRows) - $okCount;
        @endphp

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                <strong class="text-gray-900 dark:text-white">{{ count($previewRows) }}</strong> Zeilen gelesen –
                <span class="text-green-600 dark:text-green-400">{{ $okCount }} importierbar</span>@if($warningCount), <span class="text-yellow-600 dark:text-yellow-400">{{ $warningCount }} mit Warnungen</span>@endif@if($errorCount), <span class="text-red-600 dark:text-red-400">{{ $errorCount }} übersprungen</span>@endif
            </p>
            <div class="flex gap-2">
                <button wire:click="resetImport"
                    class="rounded-lg border px-4 py-2 text-sm dark:border-gray-700 dark:text-white">Abbrechen</button>
                <button wire:click="import" wire:loading.attr="disabled"
                    class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                    @if ($okCount === 0) disabled @endif>
                    {{ $okCount }} Artikel importieren
                </button>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border dark:border-gray-700">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Zeile</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Name</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Kategorie</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Preis</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">MwSt.</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Hinweise</th>
                    </tr>
                </thead>
                <tbody class="divide-y dark:divide-gray-700">
                    @foreach ($previewRows as $row)
                        <tr class="{{ $row['status'] === 'error' || $row['duplicate'] ? 'opacity-60' : '' }}">
                            <td class="px-3 py-2 text-gray-500 dark:text-gray-400">{{ $row['line'] }}</td>
                            <td class="px-3 py-2">
                                @if ($row['status'] === 'error' || $row['duplicate'])
                                    <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-700 dark:bg-red-900/40 dark:text-red-300">Übersprungen</span>
                                @elseif ($row['status'] === 'warning')
                                    <span class="rounded-full bg-yellow-100 px-2 py-0.5 text-xs text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300">Warnung</span>
                                @else
                                    <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-700 dark:bg-green-900/40 dark:text-green-300">OK</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 font-medium dark:text-white">{{ $row['name'] ?: '—' }}</td>
                            <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ $row['category'] }}</td>
                            <td class="px-3 py-2 text-right dark:text-white">{{ number_format((float) $row['price'], 2, ',', '.') }} €</td>
                            <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-300">{{ rtrim(rtrim($row['tax_rate'], '0'), '.') }} %</td>
                            <td class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">{{ implode(' ', $row['messages']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    </div>
    </x-ui-page-container>
</x-ui-page>
