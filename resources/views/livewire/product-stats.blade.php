<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Artikel" icon="heroicon-o-chart-bar" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Artikel'],
        ]" />
    </x-slot>

    <x-ui-page-container width="contained">
    <div class="space-y-5">

    {{-- Zeitraum: dieselbe Leiste wie im Export --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="flex flex-wrap items-center gap-1 rounded-[8px] border border-[color:var(--nx-line)] bg-[color:var(--nx-bg)] p-1">
            @foreach (\Platform\Reservation\Livewire\ProductStats::presets() as $preset => $label)
                <button type="button" wire:click="setPreset('{{ $preset }}')"
                    class="inline-flex h-7 items-center rounded-[6px] px-3 text-xs font-medium transition-colors
                        {{ $activePreset === $preset ? 'bg-[color:var(--nx-surface)] font-semibold text-[color:var(--nx-text)]' : 'bg-transparent text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
        <div class="flex flex-wrap items-end gap-2">
            {{-- Termin statt Zeitraum: Ein Caterer plant nach Veranstaltung,
                 nicht nach Kalendermonat. --}}
            @if ($this->events->isNotEmpty())
                <x-nx-input-select
                    name="eventId"
                    label="Termin"
                    size="sm"
                    nullable
                    nullLabel="– alle –"
                    :options="$this->events->map(fn ($e) => [
                        'value' => $e->id,
                        'label' => $e->date?->format('d.m.Y') . ' · ' . $e->name,
                    ])->all()"
                    wire:model.live="eventId"
                />
            @endif
            <x-nx-input-date name="dateFrom" label="Von" size="sm" wire:model.live="dateFrom" />
            <x-nx-input-date name="dateTo" label="Bis" size="sm" wire:model.live="dateTo" />
        </div>
    </div>

    @php ($davor = $this->vorzeitraum)
    @php ($pfeil = fn ($jetzt, $vorher) => $vorher === null ? null : ($jetzt <=> $vorher))

    <x-nx-stat-grid>
        <x-nx-stat label="Verkaufte Artikel"
            :value="number_format($this->totals->menge, 0, ',', '.') . ' Stück'"
            :hint="$this->totals->menge_davor !== null
                ? 'davor ' . number_format($this->totals->menge_davor, 0, ',', '.') . ' Stück'
                : null" />
        <x-nx-stat label="Umsatz"
            :value="number_format($this->totals->umsatz, 2, ',', '.') . ' €'"
            :hint="$this->totals->umsatz_davor !== null
                ? 'davor ' . number_format($this->totals->umsatz_davor, 2, ',', '.') . ' €'
                : null" />
        <x-nx-stat label="Verschiedene Artikel" :value="(string) $this->totals->sorten" />
        @if ($this->totals->menge_bundle > 0)
            <x-nx-stat label="davon über Bundles"
                :value="number_format($this->totals->menge_bundle, 0, ',', '.') . ' Stück'"
                :hint="$this->totals->menge > 0 ? round($this->totals->menge_bundle / $this->totals->menge * 100) . ' % der Menge' : null" />
        @endif
    </x-nx-stat-grid>

    {{-- Artikel --}}
    <x-nx-card flush>
        <div class="flex flex-wrap items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
            @svg('heroicon-o-rectangle-stack', 'w-4 h-4 text-[color:var(--nx-muted)]')
            <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Verkaufte Artikel</h2>

            <div class="ml-auto flex items-center gap-1 rounded-[6px] border border-[color:var(--nx-line)] p-0.5">
                @foreach (['menge' => 'nach Menge', 'umsatz' => 'nach Umsatz'] as $feld => $label)
                    <button type="button" wire:click="sortNach('{{ $feld }}')"
                        class="inline-flex h-6 items-center rounded-[4px] px-2 text-[11px] font-medium transition-colors
                            {{ $sortBy === $feld ? 'bg-[color:var(--nx-surface)] text-[color:var(--nx-text)]' : 'text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        @if ($this->articles->isEmpty())
            <x-nx-empty icon="heroicon-o-rectangle-stack">
                <span class="text-sm font-medium text-[color:var(--nx-text)]">Nichts verkauft in diesem Zeitraum</span>
                <span class="mt-1 block">Gezählt werden bestätigte und abgeschlossene Buchungen.</span>
            </x-nx-empty>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-[color:var(--nx-line)] bg-[color:var(--nx-hover)] text-left text-[11px] uppercase tracking-wide text-[color:var(--nx-muted)]">
                            <th class="px-4 py-2 font-medium">#</th>
                            <th class="px-4 py-2 font-medium">Artikel</th>
                            <th class="px-4 py-2 text-right font-medium">Menge</th>
                            @if ($davor)
                                <th class="px-4 py-2 text-right font-medium" title="{{ \Illuminate\Support\Carbon::parse($davor[0])->format('d.m.Y') }} – {{ \Illuminate\Support\Carbon::parse($davor[1])->format('d.m.Y') }}">davor</th>
                            @endif
                            <th class="px-4 py-2 text-right font-medium">davon im Bundle</th>
                            <th class="px-4 py-2 text-right font-medium">Umsatz</th>
                            <th class="px-4 py-2 text-right font-medium">Anteil</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->articles as $i => $zeile)
                            <tr class="border-b border-[color:var(--nx-line)] last:border-0" wire:key="art-{{ $zeile['id'] }}">
                                <td class="px-4 py-2 tabular-nums text-[color:var(--nx-faint)]">{{ $i + 1 }}</td>
                                <td class="px-4 py-2">
                                    <span class="font-medium text-[color:var(--nx-text)]">{{ $zeile['name'] }}</span>
                                    @if ($zeile['category'])
                                        <span class="ml-2 text-[11px] text-[color:var(--nx-muted)]">{{ $zeile['category'] }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-right font-semibold tabular-nums text-[color:var(--nx-text)]">
                                    {{ number_format($zeile['menge'], 0, ',', '.') }}
                                </td>
                                @if ($davor)
                                    @php ($richtung = $pfeil($zeile['menge'], $zeile['menge_davor']))
                                    <td class="whitespace-nowrap px-4 py-2 text-right tabular-nums">
                                        <span class="text-[color:var(--nx-muted)]">{{ number_format($zeile['menge_davor'] ?? 0, 0, ',', '.') }}</span>
                                        @if ($richtung !== null && $richtung !== 0)
                                            <span class="ml-1 text-[11px]"
                                                style="color: {{ $richtung > 0 ? 'var(--nx-success)' : 'var(--nx-danger)' }}">
                                                {{ $richtung > 0 ? '▲' : '▼' }}{{ number_format(abs($zeile['menge'] - ($zeile['menge_davor'] ?? 0)), 0, ',', '.') }}
                                            </span>
                                        @endif
                                    </td>
                                @endif
                                <td class="whitespace-nowrap px-4 py-2 text-right tabular-nums text-[color:var(--nx-muted)]">
                                    @if ($zeile['menge_bundle'] > 0)
                                        {{ number_format($zeile['menge_bundle'], 0, ',', '.') }}
                                        <span class="text-[11px]">({{ round($zeile['menge_bundle'] / $zeile['menge'] * 100) }} %)</span>
                                    @else
                                        –
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-right tabular-nums text-[color:var(--nx-text)]">
                                    {{ number_format($zeile['umsatz'], 2, ',', '.') }} €
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-right tabular-nums text-[color:var(--nx-muted)]">
                                    {{ number_format($zeile['anteil'], 1, ',', '.') }} %
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="m-0 border-t border-[color:var(--nx-line)] px-4 py-2 text-[11px] text-[color:var(--nx-muted)]">
                Gezählt werden Bestandteile: Ein Bundle zerfällt beim Bestellen in seine Teile, und drei Bier im
                Paket sind drei Bier. Die Spalte daneben zeigt, wie viel davon über ein Bundle ging.
            </p>
        @endif
    </x-nx-card>

    {{-- Nicht verkauft. Standardmäßig zu: Bei einer großen Karte ist das die
         längste Liste der Seite, und gesucht wird sie nur, wenn jemand die Karte
         ausmistet. --}}
    @if ($this->unsold->isNotEmpty())
        <x-nx-card flush>
            <button type="button" wire:click="$toggle('showUnsold')"
                class="flex w-full items-center gap-2 px-4 py-3 text-left transition-colors hover:bg-[color:var(--nx-hover)]">
                @svg('heroicon-o-eye-slash', 'w-4 h-4 text-[color:var(--nx-muted)]')
                <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Nicht verkauft</h2>
                <span class="text-[11px] text-[color:var(--nx-muted)]">
                    {{ $this->unsold->count() }} {{ $this->unsold->count() === 1 ? 'Artikel stand' : 'Artikel standen' }}
                    auf der Karte, ohne bestellt zu werden
                </span>
                <span class="ml-auto text-[11px] text-[color:var(--nx-muted)]">{{ $showUnsold ? 'ausblenden' : 'einblenden' }}</span>
                @svg('heroicon-o-chevron-down', 'w-3.5 h-3.5 text-[color:var(--nx-muted)] transition-transform ' . ($showUnsold ? 'rotate-180' : ''))
            </button>

            @if ($showUnsold)
                <div class="border-t border-[color:var(--nx-line)] p-5">
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($this->unsold as $zeile)
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-[color:var(--nx-line)] px-2.5 py-1 text-[11px] text-[color:var(--nx-muted)]"
                                wire:key="uns-{{ $zeile['id'] }}">
                                {{ $zeile['name'] }}
                                @if ($zeile['category'])
                                    <span class="text-[color:var(--nx-faint)]">{{ $zeile['category'] }}</span>
                                @endif
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif
        </x-nx-card>
    @endif

    {{-- Bundles --}}
    @if ($this->bundles->isNotEmpty())
        <x-nx-card flush>
            <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
                @svg('heroicon-o-gift', 'w-4 h-4 text-[color:var(--nx-muted)]')
                <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Verkaufte Bundles</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-[color:var(--nx-line)] bg-[color:var(--nx-hover)] text-left text-[11px] uppercase tracking-wide text-[color:var(--nx-muted)]">
                            <th class="px-4 py-2 font-medium">Bundle</th>
                            <th class="px-4 py-2 text-right font-medium">verkauft</th>
                            <th class="px-4 py-2 text-right font-medium">Umsatz</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->bundles as $zeile)
                            <tr class="border-b border-[color:var(--nx-line)] last:border-0" wire:key="bun-{{ $zeile['id'] }}">
                                <td class="px-4 py-2 font-medium text-[color:var(--nx-text)]">{{ $zeile['name'] }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right font-semibold tabular-nums text-[color:var(--nx-text)]">
                                    {{ number_format($zeile['menge'], 0, ',', '.') }}×
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-right tabular-nums text-[color:var(--nx-text)]">
                                    {{ number_format($zeile['umsatz'], 2, ',', '.') }} €
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-nx-card>
    @endif

    </div>
    </x-ui-page-container>
</x-ui-page>
