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
        $hero = $this->event->imageUrl('medium_16_9');
        $tiles = [
            ['Buchungen', $s['bookings'], 'heroicon-o-clipboard-document-check', 'primary'],
            ['Gäste', $s['guests'], 'heroicon-o-users', 'primary'],
            ['Umsatz', number_format($s['revenue'], 2, ',', '.') . ' ' . $sym, 'heroicon-o-banknotes', 'success'],
            ['Pausen', $s['pauses'], 'heroicon-o-clock', 'muted'],
        ];
        $chips = [
            'primary' => 'bg-[var(--ui-primary-10)] text-[var(--ui-primary)]',
            'success' => 'bg-[var(--ui-success-10)] text-[var(--ui-success)]',
            'muted'   => 'bg-[var(--ui-muted-5)] text-[var(--ui-muted)]',
        ];
    @endphp

    <div class="pt-4 space-y-5">

        {{-- Hero --}}
        <section class="relative overflow-hidden rounded-2xl border border-[var(--ui-border)]/40 shadow-sm {{ $hero ? '' : 'bg-gradient-to-br from-[var(--ui-primary-10)] via-white to-white' }}">
            @if ($hero)
                <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('{{ $hero }}')"></div>
                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/35 to-black/5"></div>
            @endif
            <div class="relative flex min-h-[160px] flex-col justify-end gap-2 p-5 sm:min-h-[190px]">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $hero ? 'bg-white/20 text-white backdrop-blur-sm' : 'bg-[var(--ui-primary-10)] text-[var(--ui-primary)]' }}">{{ $this->event->status->label() }}</span>
                    @if ($this->event->date->isToday())
                        <span class="rounded-full bg-[var(--ui-success)] px-2.5 py-0.5 text-[11px] font-semibold text-white">Heute</span>
                    @endif
                </div>
                <h1 class="m-0 text-2xl font-bold leading-tight {{ $hero ? 'text-white drop-shadow' : 'text-[var(--ui-secondary)]' }}">{{ $this->event->name }}</h1>
                <p class="m-0 text-sm {{ $hero ? 'text-white/85' : 'text-[var(--ui-muted)]' }}">
                    {{ $this->event->date->format('d.m.Y') }}
                    @if ($this->event->venue) · {{ $this->event->venue->name }} @endif
                    @if ($this->event->slots->isNotEmpty()) · {{ $this->event->slots->map(fn ($sl) => $sl->displayLabel())->implode(', ') }} @endif
                </p>
            </div>
        </section>

        {{-- Kennzahlen --}}
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach ($tiles as [$label, $value, $icon, $tone])
                <div class="flex items-center gap-3 rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm p-3.5">
                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $chips[$tone] }}">
                        @svg($icon, 'w-5 h-5')
                    </span>
                    <div class="min-w-0">
                        <span class="block text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)]">{{ $label }}</span>
                        <span class="block text-lg font-bold leading-tight tabular-nums text-[var(--ui-secondary)]">{{ $value }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Vollwertige Views --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @php
                $nav = [
                    ['reservation.events.orders', 'heroicon-o-fire', 'Küche', 'Gesamtbestellungen je Pause – was die Küche bereitstellen muss.'],
                    ['reservation.events.function', 'heroicon-o-clipboard-document-list', 'Laufzettel', 'Laufrunden je Pause: Standzeit-Klasse → Tisch → Bestellung.'],
                    ['reservation.events.overview', 'heroicon-o-presentation-chart-bar', 'Abend-Übersicht', 'Kennzahlen, Pausen, Top-Speisen und Gästeliste auf einen Blick.'],
                ];
            @endphp
            @foreach ($nav as [$route, $icon, $title, $desc])
                <a href="{{ route($route, $this->event->id) }}" wire:navigate
                   class="group relative flex items-start gap-4 overflow-hidden rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm p-5 transition-all hover:-translate-y-0.5 hover:border-[var(--ui-primary)]/50 hover:shadow-md">
                    <span class="absolute inset-x-0 top-0 h-1 scale-x-0 bg-[var(--ui-primary)] transition-transform group-hover:scale-x-100"></span>
                    <div class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-[var(--ui-primary-10)] text-[var(--ui-primary)] transition-colors group-hover:bg-[var(--ui-primary)] group-hover:text-white">
                        @svg($icon, 'w-6 h-6')
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="m-0 text-base font-semibold text-[var(--ui-secondary)]">{{ $title }}</h3>
                        <p class="m-0 mt-1 text-sm text-[var(--ui-muted)]">{{ $desc }}</p>
                    </div>
                    @svg('heroicon-o-arrow-right', 'w-5 h-5 shrink-0 text-[var(--ui-muted)] transition-transform group-hover:translate-x-1 group-hover:text-[var(--ui-primary)]')
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

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
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
