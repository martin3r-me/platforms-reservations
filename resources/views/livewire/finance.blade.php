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

    <x-ui-page-container>
    <div class="pt-4 space-y-4">

    {{-- Zeitraum --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="flex flex-wrap items-center gap-1 p-1 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
            @foreach ([
                'year'      => 'Dieses Jahr',
                'last_year' => 'Letztes Jahr',
                'last_12'   => 'Letzte 12 Monate',
                'all'       => 'Gesamt',
            ] as $preset => $label)
                <button type="button" wire:click="setPreset('{{ $preset }}')"
                    class="inline-flex items-center px-3 h-7 text-xs font-medium rounded-md transition-colors
                        {{ $activePreset === $preset ? 'bg-white text-[var(--ui-primary)] shadow-sm' : 'bg-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
        <div class="flex items-end gap-2">
            <div class="w-40">
                <x-ui-input-date name="dateFrom" label="Von" size="sm" wire:model.live="dateFrom" />
            </div>
            <div class="w-40">
                <x-ui-input-date name="dateTo" label="Bis" size="sm" wire:model.live="dateTo" />
            </div>
        </div>
    </div>

    {{-- KPI-Kacheln --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm p-3">
            <span class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)]">Umsatz im Zeitraum</span>
            <p class="m-0 mt-1 text-lg font-bold tabular-nums text-[var(--ui-secondary)]">{{ number_format($this->totals->revenue, 2, ',', '.') }} €</p>
        </div>
        <div class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm p-3">
            <span class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)]">Buchungen mit Bestellung</span>
            <p class="m-0 mt-1 text-lg font-bold tabular-nums text-[var(--ui-secondary)]">{{ $this->totals->bookings }}</p>
        </div>
        <div class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm p-3">
            <span class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)]">Ø pro Buchung</span>
            <p class="m-0 mt-1 text-lg font-bold tabular-nums text-[var(--ui-secondary)]">{{ number_format($this->totals->average, 2, ',', '.') }} €</p>
        </div>
        <div class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm p-3">
            <span class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)]">Stärkster Monat</span>
            <p class="m-0 mt-1 text-lg font-bold text-[var(--ui-secondary)]">
                {{ $this->totals->best_month ? \Illuminate\Support\Carbon::parse($this->totals->best_month . '-01')->locale('de')->isoFormat('MMM Y') : '–' }}
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        {{-- Umsatz nach Monaten --}}
        <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden lg:col-span-2">
            <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
                @svg('heroicon-o-chart-bar', 'w-4 h-4 text-[var(--ui-muted)]')
                <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Umsatz nach Monaten</h2>
            </div>
            <div class="p-4">
                @if ($this->monthlyRevenue->isEmpty() || $this->totals->revenue == 0)
                    <div class="flex flex-col items-center justify-center py-8 text-[var(--ui-muted)]">
                        @svg('heroicon-o-chart-bar', 'w-8 h-8 mb-2 opacity-40')
                        <span class="text-xs">Kein Umsatz im gewählten Zeitraum</span>
                    </div>
                @else
                    <div class="space-y-2">
                        @foreach ($this->monthlyRevenue as $ym => $month)
                            @php
                                $pct = $this->totals->max_month > 0 ? ($month->revenue / $this->totals->max_month) * 100 : 0;
                            @endphp
                            <div wire:key="month-{{ $ym }}" class="flex items-center gap-3">
                                <span class="w-20 shrink-0 text-xs text-[var(--ui-muted)]">
                                    {{ \Illuminate\Support\Carbon::parse($ym . '-01')->locale('de')->isoFormat('MMM YY') }}
                                </span>
                                <div class="h-4 flex-1 overflow-hidden rounded-full bg-[var(--ui-muted-5)]">
                                    <div class="h-full rounded-full bg-[var(--ui-primary)] transition-all"
                                        style="width: {{ max($month->revenue > 0 ? 2 : 0, $pct) }}%"></div>
                                </div>
                                <span class="w-28 shrink-0 whitespace-nowrap text-right text-xs font-semibold tabular-nums text-[var(--ui-secondary)]">
                                    {{ number_format($month->revenue, 2, ',', '.') }} €
                                </span>
                                <span class="w-16 shrink-0 text-right text-[11px] tabular-nums text-[var(--ui-muted)]">
                                    {{ $month->bookings }} Buch.
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        {{-- MwSt-Aufschlüsselung --}}
        <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
                @svg('heroicon-o-receipt-percent', 'w-4 h-4 text-[var(--ui-muted)]')
                <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Umsatz je MwSt-Satz</h2>
            </div>
            <div class="divide-y divide-[var(--ui-border)]/30">
                @forelse ($this->taxBreakdown as $tax)
                    <div class="px-4 py-2.5 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-[var(--ui-muted)]">{{ rtrim(rtrim($tax->tax_rate, '0'), '.') }} % MwSt.</span>
                            <span class="whitespace-nowrap font-semibold tabular-nums text-[var(--ui-secondary)]">{{ number_format($tax->gross, 2, ',', '.') }} €</span>
                        </div>
                        <div class="mt-0.5 flex items-center justify-between text-[11px] text-[var(--ui-muted)] tabular-nums">
                            <span>Netto {{ number_format($tax->net, 2, ',', '.') }} €</span>
                            <span>MwSt {{ number_format($tax->vat, 2, ',', '.') }} €</span>
                        </div>
                    </div>
                @empty
                    <div class="px-4 py-6 text-center text-xs text-[var(--ui-muted)]">Keine Daten</div>
                @endforelse
            </div>
        </section>
    </div>

    {{-- Umsatz nach Terminen --}}
    <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
            @svg('heroicon-o-ticket', 'w-4 h-4 text-[var(--ui-muted)]')
            <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Umsatz nach Terminen</h2>
            <span class="ml-auto text-[11px] text-[var(--ui-muted)]">{{ $this->eventRevenue->count() }}</span>
        </div>
        <x-ui-table compact="true">
            <x-ui-table-header>
                <x-ui-table-header-cell compact="true">Termin</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Datum</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true" align="center">Buchungen</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true" align="center">Gäste</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true" align="right">Ø / Buchung</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true" align="right">Umsatz</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true"></x-ui-table-header-cell>
            </x-ui-table-header>
            <x-ui-table-body>
                @forelse ($this->eventRevenue as $row)
                    <x-ui-table-row compact="true" wire:key="event-rev-{{ $row->event_id ?? 'none' }}">
                        <x-ui-table-cell compact="true">
                            <span class="font-medium text-[var(--ui-secondary)]">{{ $row->name }}</span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">{{ $row->date?->format('d.m.Y') ?? '–' }}</x-ui-table-cell>
                        <x-ui-table-cell compact="true" align="center">{{ $row->bookings }}</x-ui-table-cell>
                        <x-ui-table-cell compact="true" align="center">{{ $row->guests }}</x-ui-table-cell>
                        <x-ui-table-cell compact="true" align="right">
                            <span class="whitespace-nowrap tabular-nums">{{ number_format($row->bookings > 0 ? $row->revenue / $row->bookings : 0, 2, ',', '.') }} €</span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true" align="right">
                            <span class="whitespace-nowrap font-semibold tabular-nums text-[var(--ui-secondary)]">{{ number_format($row->revenue, 2, ',', '.') }} €</span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            @if ($row->event_id)
                                <x-ui-button variant="secondary-outline" size="sm" :href="route('reservation.events.orders', $row->event_id)" wire:navigate>
                                    @svg('heroicon-o-fire', 'w-4 h-4')
                                    <span>Küche</span>
                                </x-ui-button>
                            @endif
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @empty
                    <tr>
                        <td colspan="7">
                            <div class="flex flex-col items-center justify-center py-8 text-[var(--ui-muted)]">
                                @svg('heroicon-o-inbox', 'w-8 h-8 mb-2 opacity-40')
                                <span class="text-xs">Keine Umsätze im gewählten Zeitraum</span>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </x-ui-table-body>
        </x-ui-table>
    </section>

    <p class="text-[11px] text-[var(--ui-muted)] m-0">
        Umsatz = Bestellwert aktiver Buchungen (ohne Stornos/No-Shows). Bestätigte Zahlungseingänge folgen mit der Mollie-Integration.
    </p>

    </div>
    </x-ui-page-container>
</x-ui-page>
