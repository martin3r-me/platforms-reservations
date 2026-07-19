<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$this->event->name" icon="heroicon-o-fire" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Veranstaltungen', 'href' => route('reservation.operations.index')],
            ['label' => $this->event->name],
        ]">
            @if (\Illuminate\Support\Facades\Route::has('reservation.guest.checkout') && $this->event->status->value === 'published')
                <x-ui-button variant="secondary-outline" size="sm" :href="route('reservation.guest.checkout', $this->event->uuid)" target="_blank">
                    @svg('heroicon-o-eye', 'w-4 h-4')
                    <span>Gast-Ansicht</span>
                </x-ui-button>
            @endif
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
    @php
        $currency = strtoupper((string) config('reservation.currency', 'EUR'));
        $sym = $currency === 'EUR' ? '€' : $currency;
        $s = $this->stats;
        $tiles = [
            ['Buchungen', $s['bookings']],
            ['Gäste', $s['guests']],
            ['Umsatz', number_format($s['revenue'], 2, ',', '.') . ' ' . $sym],
            ['Pausen', $s['pauses']],
        ];
    @endphp

    <div class="pt-5 space-y-6">

        {{-- Titelzeile --}}
        <div>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                <h1 class="m-0 text-xl font-bold leading-tight text-[var(--ui-secondary)]">{{ $this->event->name }}</h1>
                <x-ui-badge :variant="$this->event->status->badgeVariant()" size="xs">{{ $this->event->status->label() }}</x-ui-badge>
                @if ($this->event->date->isToday())
                    <x-ui-badge variant="success" size="xs">Heute</x-ui-badge>
                @endif
            </div>
            <p class="m-0 mt-1 text-sm text-[var(--ui-muted)]">
                {{ $this->event->date->format('d.m.Y') }}
                @if ($this->event->venue) · {{ $this->event->venue->name }} @endif
                @if ($this->event->slots->isNotEmpty()) · {{ $this->event->slots->map(fn ($sl) => $sl->displayLabel())->implode(', ') }} @endif
            </p>
        </div>

        {{-- Kennzahlen-Leiste --}}
        <div class="grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-[var(--ui-border)]/40 bg-[var(--ui-border)]/25 shadow-sm sm:grid-cols-4">
            @foreach ($tiles as [$label, $value])
                <div class="bg-white px-4 py-3.5">
                    <span class="block whitespace-nowrap text-2xl font-bold leading-none tabular-nums text-[var(--ui-secondary)]">{{ $value }}</span>
                    <span class="mt-1.5 block text-[11px] font-medium uppercase tracking-wider text-[var(--ui-muted)]">{{ $label }}</span>
                </div>
            @endforeach
        </div>

        {{-- Aktionen (kompakt) --}}
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
            @php
                $nav = [
                    ['reservation.events.orders', 'heroicon-o-fire', 'Küche'],
                    ['reservation.events.function', 'heroicon-o-clipboard-document-list', 'Laufzettel'],
                    ['reservation.events.overview', 'heroicon-o-presentation-chart-bar', 'Abend-Übersicht'],
                ];
            @endphp
            @foreach ($nav as [$route, $icon, $title])
                <a href="{{ route($route, $this->event->id) }}" wire:navigate
                   class="group flex items-center gap-3 rounded-xl border border-[var(--ui-border)]/40 bg-white px-4 py-3 shadow-sm transition-colors hover:border-[var(--ui-primary)]/50 hover:bg-[var(--ui-primary-10)]/30">
                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[var(--ui-primary-10)] text-[var(--ui-primary)]">
                        @svg($icon, 'w-5 h-5')
                    </span>
                    <span class="text-sm font-semibold text-[var(--ui-secondary)]">{{ $title }}</span>
                    @svg('heroicon-o-arrow-right', 'w-4 h-4 ml-auto shrink-0 text-[var(--ui-muted)] transition-transform group-hover:translate-x-0.5 group-hover:text-[var(--ui-primary)]')
                </a>
            @endforeach
        </div>

        {{-- Bestellte Artikel, geclustert nach Kategorie --}}
        @if ($this->itemsByCategory->isNotEmpty())
            <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
                    @svg('heroicon-o-rectangle-stack', 'w-4 h-4 text-[var(--ui-muted)]')
                    <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Bestellte Artikel</h2>
                    <span class="ml-auto text-[11px] text-[var(--ui-muted)]">{{ $this->totalItems }} Stk.</span>
                </div>
                <div class="grid grid-cols-1 gap-px bg-[var(--ui-border)]/20 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($this->itemsByCategory as $category => $items)
                        <div class="bg-white p-4" wire:key="cat-{{ $loop->index }}">
                            <p class="m-0 mb-2 flex items-center justify-between text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)]">
                                <span class="truncate">{{ $category }}</span>
                                <span class="rounded-full bg-[var(--ui-muted-5)] px-2 py-0.5 tabular-nums text-[var(--ui-secondary)]">{{ $items->sum('quantity') }}</span>
                            </p>
                            <div class="divide-y divide-[var(--ui-border)]/20">
                                @foreach ($items as $item)
                                    <div class="flex items-center justify-between gap-2 py-1.5 text-sm" wire:key="cat-{{ $loop->parent->index }}-item-{{ $loop->index }}">
                                        <span class="min-w-0 truncate text-[var(--ui-secondary)]">{{ $item['name'] }}</span>
                                        <span class="shrink-0 font-bold tabular-nums text-[var(--ui-primary)]">{{ $item['quantity'] }}×</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{-- Standzeit-Klassen-Verteilung (Timing) --}}
            @if ($this->holdingClassDistribution->isNotEmpty())
                @php $hcTotal = max(1, $this->totalItems); @endphp
                <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
                    <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
                        @svg('heroicon-o-fire', 'w-4 h-4 text-[var(--ui-muted)]')
                        <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Standzeit-Klassen</h2>
                        <span class="ml-auto text-[11px] text-[var(--ui-muted)]">Timing</span>
                    </div>
                    <div class="space-y-3.5 p-4">
                        @foreach ($this->holdingClassDistribution as $hc)
                            @php
                                $share = round($hc['quantity'] / $hcTotal * 100);
                                $color = $hc['color'] ?: '#94a3b8';
                            @endphp
                            <div wire:key="hc-dist-{{ $loop->index }}">
                                <div class="mb-1 flex items-center gap-2 text-sm">
                                    <span class="inline-block h-3 w-3 shrink-0 rounded-full border border-black/10" style="background: {{ $color }}"></span>
                                    <span class="font-medium text-[var(--ui-secondary)]">{{ $hc['name'] }}</span>
                                    @if ($hc['lead_time_minutes'] !== null)
                                        <span class="rounded-full bg-[var(--ui-muted-5)] px-2 py-0.5 text-[10px] font-medium text-[var(--ui-muted)]">{{ $hc['lead_time_minutes'] }} min vor</span>
                                    @endif
                                    <span class="ml-auto shrink-0 tabular-nums text-[var(--ui-secondary)]"><span class="font-bold">{{ $hc['quantity'] }}×</span> <span class="text-[var(--ui-muted)]">· {{ $share }} %</span></span>
                                </div>
                                <div class="h-2 w-full overflow-hidden rounded-full bg-[var(--ui-muted-5)]">
                                    <div class="h-full rounded-full transition-all" style="width: {{ $share }}%; background: {{ $color }}"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Tisch-Auslastung je Raum --}}
            @if ($this->roomUtilization->isNotEmpty())
                <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
                    <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-3">
                        @svg('heroicon-o-squares-2x2', 'w-4 h-4 text-[var(--ui-muted)]')
                        <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Auslastung</h2>
                        <span class="ml-auto flex items-center gap-3 text-[10px] text-[var(--ui-muted)]">
                            <span class="flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-[var(--ui-primary)]"></span>belegt</span>
                            <span class="flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-[var(--ui-danger)]"></span>gesperrt</span>
                            <span class="flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-[var(--ui-muted-5)] border border-[var(--ui-border)]"></span>frei</span>
                        </span>
                    </div>
                    <div class="space-y-3.5 p-4">
                        @foreach ($this->roomUtilization as $r)
                            @php
                                $total = max(1, $r['total']);
                                $occPct  = $r['occupied'] / $total * 100;
                                $blkPct  = $r['blocked'] / $total * 100;
                                $freePct = $r['free'] / $total * 100;
                            @endphp
                            <div wire:key="util-{{ $loop->index }}">
                                <div class="mb-1 flex items-center gap-2 text-sm">
                                    <span class="font-medium text-[var(--ui-secondary)]">{{ $r['room'] }}</span>
                                    <span class="ml-auto shrink-0 text-xs tabular-nums text-[var(--ui-muted)]">
                                        <span class="font-semibold text-[var(--ui-secondary)]">{{ $r['occupied'] }}</span> belegt
                                        @if ($r['blocked'] > 0) · <span class="font-semibold text-[var(--ui-danger)]">{{ $r['blocked'] }}</span> gesperrt @endif
                                        · {{ $r['free'] }} frei / {{ $r['total'] }}
                                    </span>
                                </div>
                                <div class="flex h-2.5 w-full overflow-hidden rounded-full bg-[var(--ui-muted-5)]">
                                    <div class="h-full bg-[var(--ui-primary)]" style="width: {{ $occPct }}%"></div>
                                    <div class="h-full bg-[var(--ui-danger)]" style="width: {{ $blkPct }}%"></div>
                                    <div class="h-full" style="width: {{ $freePct }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

    </div>
    </x-ui-page-container>
</x-ui-page>
