<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="'Abend-Übersicht – ' . $this->event->name" icon="heroicon-o-presentation-chart-bar" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Veranstaltungen', 'href' => route('reservation.operations.index')],
            ['label' => $this->event->name, 'href' => route('reservation.events.dashboard', $this->event->id)],
            ['label' => 'Abend-Übersicht'],
        ]">
            <x-ui-button variant="secondary-outline" size="sm" :href="route('reservation.events.briefing', $this->event->id)" target="_blank">
                @svg('heroicon-o-printer', 'w-4 h-4')
                <span>Drucken</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        @include('reservation::partials.event-sidebar', ['event' => $this->event, 'active' => 'overview'])
    </x-slot>

    <x-ui-page-container>
    <div class="pt-4 space-y-4">
        @php
            $sheet = $this->sheet;
            $t = $sheet['totals'];
            $currency = strtoupper((string) config('reservation.currency', 'EUR'));
            $sym = $currency === 'EUR' ? '€' : $currency;
            $tiles = [
                ['Gäste', $t['guests']],
                ['Bestellungen', $t['parties']],
                ['Buchungen', $t['bookings']],
                ['Tische', $t['tables']],
                ['Artikel', $t['items']],
                ['Umsatz', number_format($t['revenue'], 2, ',', '.') . ' ' . $sym],
            ];
        @endphp

        <p class="text-xs text-[var(--ui-muted)] m-0">
            {{ optional($sheet['event']['date'])->format('d.m.Y') }}
            @if ($sheet['event']['venue']) · {{ $sheet['event']['venue'] }} @endif
            · {{ $t['pauses'] }} {{ $t['pauses'] === 1 ? 'Pause' : 'Pausen' }}
            · erstellt {{ $sheet['generated_at']->format('d.m.Y H:i') }}
        </p>

        {{-- Kennzahlen --}}
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            @foreach ($tiles as [$label, $value])
                <div class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm p-3">
                    <span class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)]">{{ $label }}</span>
                    <p class="m-0 mt-1 text-lg font-bold tabular-nums text-[var(--ui-secondary)]">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        {{-- Pausen --}}
        <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
                @svg('heroicon-o-clock', 'w-4 h-4 text-[var(--ui-muted)]')
                <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Pausen</h2>
            </div>
            <x-ui-table compact="true">
                <x-ui-table-header>
                    <x-ui-table-header-cell compact="true">Pause</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true">Zeit</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true" align="center">Gäste</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true" align="center">Buchungen</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true" align="center">Tische</x-ui-table-header-cell>
                    <x-ui-table-header-cell compact="true" align="center">Artikel</x-ui-table-header-cell>
                </x-ui-table-header>
                <x-ui-table-body>
                    @forelse ($sheet['pauses'] as $p)
                        <x-ui-table-row compact="true" wire:key="brief-pause-{{ $loop->index }}">
                            <x-ui-table-cell compact="true"><span class="font-medium text-[var(--ui-secondary)]">{{ $p['name'] }}</span></x-ui-table-cell>
                            <x-ui-table-cell compact="true">{{ $p['time'] ? $p['time'] . ' Uhr' : '–' }}</x-ui-table-cell>
                            <x-ui-table-cell compact="true" align="center" class="tabular-nums">{{ $p['guests'] }}</x-ui-table-cell>
                            <x-ui-table-cell compact="true" align="center" class="tabular-nums">{{ $p['bookings'] }}</x-ui-table-cell>
                            <x-ui-table-cell compact="true" align="center" class="tabular-nums">{{ $p['tables'] }}</x-ui-table-cell>
                            <x-ui-table-cell compact="true" align="center" class="tabular-nums">{{ $p['items'] }}</x-ui-table-cell>
                        </x-ui-table-row>
                    @empty
                        <tr><td colspan="6"><div class="py-6 text-center text-xs text-[var(--ui-muted)]">Keine Pausen.</div></td></tr>
                    @endforelse
                </x-ui-table-body>
            </x-ui-table>
        </section>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            {{-- Top-Speisen --}}
            <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
                    @svg('heroicon-o-trophy', 'w-4 h-4 text-[var(--ui-muted)]')
                    <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Top-Speisen</h2>
                </div>
                <div class="divide-y divide-[var(--ui-border)]/30">
                    @forelse ($sheet['top_items'] as $item)
                        <div class="flex items-center justify-between gap-3 px-4 py-2 text-sm" wire:key="brief-top-{{ $loop->index }}">
                            <span class="min-w-0 truncate text-[var(--ui-secondary)]">{{ $loop->iteration }}. {{ $item['name'] }}</span>
                            <span class="shrink-0 font-bold tabular-nums text-[var(--ui-secondary)]">{{ $item['quantity'] }}×</span>
                        </div>
                    @empty
                        <div class="px-4 py-6 text-center text-xs text-[var(--ui-muted)]">Keine Bestellungen.</div>
                    @endforelse
                </div>
            </section>

            {{-- Gästeliste --}}
            <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
                    @svg('heroicon-o-users', 'w-4 h-4 text-[var(--ui-muted)]')
                    <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Gästeliste</h2>
                    <span class="ml-auto text-[11px] text-[var(--ui-muted)]">{{ count($sheet['guests']) }}</span>
                </div>
                <div class="divide-y divide-[var(--ui-border)]/30">
                    @forelse ($sheet['guests'] as $g)
                        <div class="px-4 py-2 text-sm" wire:key="brief-guest-{{ $loop->index }}">
                            <div class="flex items-center justify-between gap-3">
                                <span class="min-w-0 truncate font-medium text-[var(--ui-secondary)]">{{ $g['name'] }}</span>
                                <span class="shrink-0 text-xs text-[var(--ui-muted)]">{{ $g['count'] }} {{ $g['count'] === 1 ? 'Person' : 'Pers.' }} · {{ $g['items'] }} Art.</span>
                            </div>
                            @if (!empty($g['tables']) || !empty($g['pauses']))
                                <p class="m-0 mt-0.5 text-xs text-[var(--ui-muted)]">
                                    @if (!empty($g['tables'])) Tisch {{ implode(', ', $g['tables']) }} @endif
                                    @if (!empty($g['pauses'])) · {{ implode(', ', $g['pauses']) }} @endif
                                </p>
                            @endif
                        </div>
                    @empty
                        <div class="px-4 py-6 text-center text-xs text-[var(--ui-muted)]">Keine Gäste.</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
    </x-ui-page-container>
</x-ui-page>
