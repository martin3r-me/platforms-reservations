<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Finanzen" icon="heroicon-o-banknotes" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Finanzen'],
        ]" />
    </x-slot>

    <x-ui-page-container width="contained">
    <div class="space-y-5">

    {{-- Zeitraum --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="flex flex-wrap items-center gap-1 rounded-[8px] border border-[color:var(--nx-line)] bg-[color:var(--nx-bg)] p-1">
            @foreach ([
                'year'      => 'Dieses Jahr',
                'last_year' => 'Letztes Jahr',
                'last_12'   => 'Letzte 12 Monate',
                'all'       => 'Gesamt',
            ] as $preset => $label)
                <button type="button" wire:click="setPreset('{{ $preset }}')"
                    class="inline-flex h-7 items-center rounded-[6px] px-3 text-xs font-medium transition-colors
                        {{ $activePreset === $preset ? 'bg-[color:var(--nx-surface)] font-semibold text-[color:var(--nx-text)]' : 'bg-transparent text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
        <div class="flex items-end gap-2">
            <div class="w-40">
                <x-nx-input-date name="dateFrom" label="Von" size="sm" wire:model.live="dateFrom" />
            </div>
            <div class="w-40">
                <x-nx-input-date name="dateTo" label="Bis" size="sm" wire:model.live="dateTo" />
            </div>
        </div>
    </div>

    {{-- KPIs --}}
    <x-nx-stat-grid>
        <x-nx-stat label="Umsatz im Zeitraum" :value="number_format($this->totals->revenue, 2, ',', '.') . ' €'" />
        <x-nx-stat label="Buchungen mit Bestellung" :value="(string) $this->totals->bookings" />
        <x-nx-stat label="Ø pro Buchung" :value="number_format($this->totals->average, 2, ',', '.') . ' €'" />
        <x-nx-stat label="Stärkster Monat" :value="$this->totals->best_month ? \Illuminate\Support\Carbon::parse($this->totals->best_month . '-01')->locale('de')->isoFormat('MMM Y') : '–'" />
    </x-nx-stat-grid>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        {{-- Umsatz nach Monaten --}}
        <x-nx-card flush class="lg:col-span-2">
            <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
                @svg('heroicon-o-chart-bar', 'w-4 h-4 text-[color:var(--nx-muted)]')
                <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Umsatz nach Monaten</h2>
            </div>
            <div class="p-4">
                @if ($this->monthlyRevenue->isEmpty() || $this->totals->revenue == 0)
                    <x-nx-empty icon="heroicon-o-chart-bar">Kein Umsatz im gewählten Zeitraum</x-nx-empty>
                @else
                    <div class="space-y-2">
                        @foreach ($this->monthlyRevenue as $ym => $month)
                            @php
                                $pct = $this->totals->max_month > 0 ? ($month->revenue / $this->totals->max_month) * 100 : 0;
                            @endphp
                            <div wire:key="month-{{ $ym }}" class="flex items-center gap-3">
                                <span class="w-20 shrink-0 text-xs text-[color:var(--nx-muted)]">
                                    {{ \Illuminate\Support\Carbon::parse($ym . '-01')->locale('de')->isoFormat('MMM YY') }}
                                </span>
                                <div class="h-4 flex-1 overflow-hidden rounded-full bg-[color:var(--nx-active)]">
                                    <div class="h-full rounded-full bg-[color:var(--nx-accent)] transition-all"
                                        style="width: {{ max($month->revenue > 0 ? 2 : 0, $pct) }}%"></div>
                                </div>
                                <span class="w-28 shrink-0 whitespace-nowrap text-right text-xs font-semibold tabular-nums text-[color:var(--nx-text)]">
                                    {{ number_format($month->revenue, 2, ',', '.') }} €
                                </span>
                                <span class="w-16 shrink-0 text-right text-[11px] tabular-nums text-[color:var(--nx-faint)]">
                                    {{ $month->bookings }} Buch.
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </x-nx-card>

        {{-- MwSt-Aufschlüsselung --}}
        <x-nx-card flush>
            <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
                @svg('heroicon-o-receipt-percent', 'w-4 h-4 text-[color:var(--nx-muted)]')
                <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Umsatz je MwSt-Satz</h2>
            </div>
            <div>
                @forelse ($this->taxBreakdown as $tax)
                    <div class="border-b border-[color:var(--nx-line)] px-4 py-2.5 text-sm last:border-0">
                        <div class="flex items-center justify-between">
                            <span class="text-[color:var(--nx-muted)]">{{ rtrim(rtrim($tax->tax_rate, '0'), '.') }} % MwSt.</span>
                            <span class="whitespace-nowrap font-semibold tabular-nums text-[color:var(--nx-text)]">{{ number_format($tax->gross, 2, ',', '.') }} €</span>
                        </div>
                        <div class="mt-0.5 flex items-center justify-between text-[11px] tabular-nums text-[color:var(--nx-faint)]">
                            <span>Netto {{ number_format($tax->net, 2, ',', '.') }} €</span>
                            <span>MwSt {{ number_format($tax->vat, 2, ',', '.') }} €</span>
                        </div>
                    </div>
                @empty
                    <x-nx-empty icon="heroicon-o-receipt-percent">Keine Daten</x-nx-empty>
                @endforelse
            </div>
        </x-nx-card>
    </div>

    {{-- Umsatz nach Terminen --}}
    <x-nx-card flush>
        <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
            @svg('heroicon-o-ticket', 'w-4 h-4 text-[color:var(--nx-muted)]')
            <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Umsatz nach Terminen</h2>
            <span class="ml-auto text-xs tabular-nums text-[color:var(--nx-faint)]">{{ $this->eventRevenue->count() }}</span>
        </div>
        <x-nx-table>
            <x-nx-table-header>
                <x-nx-table-header-cell compact>Termin</x-nx-table-header-cell>
                <x-nx-table-header-cell compact>Datum</x-nx-table-header-cell>
                <x-nx-table-header-cell compact align="center">Buchungen</x-nx-table-header-cell>
                <x-nx-table-header-cell compact align="center">Gäste</x-nx-table-header-cell>
                <x-nx-table-header-cell compact align="right">Ø / Buchung</x-nx-table-header-cell>
                <x-nx-table-header-cell compact align="right">Umsatz</x-nx-table-header-cell>
                <x-nx-table-header-cell compact><span class="sr-only">Aktion</span></x-nx-table-header-cell>
            </x-nx-table-header>
            <x-nx-table-body>
                @forelse ($this->eventRevenue as $row)
                    <x-nx-table-row compact wire:key="event-rev-{{ $row->event_id ?? 'none' }}" class="group">
                        <x-nx-table-cell compact>
                            <span class="font-medium text-[color:var(--nx-text)]">{{ $row->name }}</span>
                        </x-nx-table-cell>
                        <x-nx-table-cell compact class="tabular-nums text-[color:var(--nx-muted)]">{{ $row->date?->format('d.m.Y') ?? '–' }}</x-nx-table-cell>
                        <x-nx-table-cell compact align="center" class="tabular-nums text-[color:var(--nx-muted)]">{{ $row->bookings }}</x-nx-table-cell>
                        <x-nx-table-cell compact align="center" class="tabular-nums text-[color:var(--nx-muted)]">{{ $row->guests }}</x-nx-table-cell>
                        <x-nx-table-cell compact align="right" class="tabular-nums text-[color:var(--nx-muted)]">
                            {{ number_format($row->bookings > 0 ? $row->revenue / $row->bookings : 0, 2, ',', '.') }} €
                        </x-nx-table-cell>
                        <x-nx-table-cell compact align="right">
                            <span class="whitespace-nowrap font-semibold tabular-nums text-[color:var(--nx-text)]">{{ number_format($row->revenue, 2, ',', '.') }} €</span>
                        </x-nx-table-cell>
                        <x-nx-table-cell compact align="right">
                            @if ($row->event_id)
                                <span class="opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100">
                                    <x-nx-button icon variant="ghost" :href="route('reservation.events.orders', $row->event_id)" wire:navigate title="Küche">
                                        @svg('heroicon-o-fire', 'w-4 h-4')
                                    </x-nx-button>
                                </span>
                            @endif
                        </x-nx-table-cell>
                    </x-nx-table-row>
                @empty
                    <tr>
                        <td colspan="7">
                            <x-nx-empty icon="heroicon-o-inbox">Keine Umsätze im gewählten Zeitraum</x-nx-empty>
                        </td>
                    </tr>
                @endforelse
            </x-nx-table-body>
        </x-nx-table>
    </x-nx-card>

    <p class="m-0 text-[11px] text-[color:var(--nx-faint)]">
        Umsatz = Bestellwert aktiver Buchungen (ohne Stornos/No-Shows). Bestätigte Zahlungseingänge folgen mit der Mollie-Integration.
    </p>

    </div>
    </x-ui-page-container>
</x-ui-page>
