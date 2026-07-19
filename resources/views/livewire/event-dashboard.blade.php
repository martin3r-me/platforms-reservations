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
    <div class="pt-4 space-y-4">

        @php
            $currency = strtoupper((string) config('reservation.currency', 'EUR'));
            $sym = $currency === 'EUR' ? '€' : $currency;
            $s = $this->stats;
        @endphp

        {{-- Kopf: Termin-Kontext --}}
        <div class="flex flex-wrap items-center gap-2 text-sm text-[var(--ui-muted)]">
            <x-ui-badge :variant="$this->event->status->badgeVariant()" size="xs">{{ $this->event->status->label() }}</x-ui-badge>
            <span>{{ $this->event->date->format('d.m.Y') }}</span>
            @if ($this->event->venue) <span>· {{ $this->event->venue->name }}</span> @endif
            @if ($this->event->slots->isNotEmpty())
                <span>· {{ $this->event->slots->map(fn ($sl) => $sl->displayLabel())->implode(', ') }}</span>
            @endif
        </div>

        {{-- Kennzahlen --}}
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm p-3">
                <span class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)]">Buchungen</span>
                <p class="m-0 mt-1 text-lg font-bold tabular-nums text-[var(--ui-secondary)]">{{ $s['bookings'] }}</p>
            </div>
            <div class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm p-3">
                <span class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)]">Gäste</span>
                <p class="m-0 mt-1 text-lg font-bold tabular-nums text-[var(--ui-secondary)]">{{ $s['guests'] }}</p>
            </div>
            <div class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm p-3">
                <span class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)]">Umsatz</span>
                <p class="m-0 mt-1 text-lg font-bold tabular-nums text-[var(--ui-secondary)]">{{ number_format($s['revenue'], 2, ',', '.') }} {{ $sym }}</p>
            </div>
            <div class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm p-3">
                <span class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)]">Pausen</span>
                <p class="m-0 mt-1 text-lg font-bold tabular-nums text-[var(--ui-secondary)]">{{ $s['pauses'] }}</p>
            </div>
        </div>

        {{-- Vollwertige Views --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <a href="{{ route('reservation.events.orders', $this->event->id) }}" wire:navigate
               class="group flex items-start gap-4 rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm p-5 transition-colors hover:border-[var(--ui-primary)]/50 hover:bg-[var(--ui-primary-10)]/20">
                <div class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-[var(--ui-primary-10)]">
                    @svg('heroicon-o-fire', 'w-6 h-6 text-[var(--ui-primary)]')
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="m-0 text-base font-semibold text-[var(--ui-secondary)]">Küche</h3>
                    <p class="m-0 mt-1 text-sm text-[var(--ui-muted)]">Gesamtbestellungen je Pause – was die Küche bereitstellen muss.</p>
                </div>
                @svg('heroicon-o-arrow-right', 'w-5 h-5 text-[var(--ui-muted)] transition-transform group-hover:translate-x-0.5')
            </a>

            <a href="{{ route('reservation.events.function', $this->event->id) }}" wire:navigate
               class="group flex items-start gap-4 rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm p-5 transition-colors hover:border-[var(--ui-primary)]/50 hover:bg-[var(--ui-primary-10)]/20">
                <div class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-[var(--ui-primary-10)]">
                    @svg('heroicon-o-clipboard-document-list', 'w-6 h-6 text-[var(--ui-primary)]')
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="m-0 text-base font-semibold text-[var(--ui-secondary)]">Laufzettel</h3>
                    <p class="m-0 mt-1 text-sm text-[var(--ui-muted)]">Laufrunden je Pause: Standzeit-Klasse → Tisch → Bestellung.</p>
                </div>
                @svg('heroicon-o-arrow-right', 'w-5 h-5 text-[var(--ui-muted)] transition-transform group-hover:translate-x-0.5')
            </a>

            <a href="{{ route('reservation.events.overview', $this->event->id) }}" wire:navigate
               class="group flex items-start gap-4 rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm p-5 transition-colors hover:border-[var(--ui-primary)]/50 hover:bg-[var(--ui-primary-10)]/20">
                <div class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-[var(--ui-primary-10)]">
                    @svg('heroicon-o-presentation-chart-bar', 'w-6 h-6 text-[var(--ui-primary)]')
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="m-0 text-base font-semibold text-[var(--ui-secondary)]">Abend-Übersicht</h3>
                    <p class="m-0 mt-1 text-sm text-[var(--ui-muted)]">Kennzahlen, Pausen, Top-Speisen und Gästeliste auf einen Blick.</p>
                </div>
                @svg('heroicon-o-arrow-right', 'w-5 h-5 text-[var(--ui-muted)] transition-transform group-hover:translate-x-0.5')
            </a>
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
                        <div class="bg-white p-3" wire:key="cat-{{ $loop->index }}">
                            <p class="m-0 mb-1.5 flex items-center justify-between text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)]">
                                <span class="truncate">{{ $category }}</span>
                                <span class="tabular-nums">{{ $items->sum('quantity') }}</span>
                            </p>
                            <div class="divide-y divide-[var(--ui-border)]/20">
                                @foreach ($items as $item)
                                    <div class="flex items-center justify-between gap-2 py-1 text-sm" wire:key="cat-{{ $loop->parent->index }}-item-{{ $loop->index }}">
                                        <span class="min-w-0 truncate text-[var(--ui-secondary)]">{{ $item['name'] }}</span>
                                        <span class="shrink-0 font-bold tabular-nums text-[var(--ui-secondary)]">{{ $item['quantity'] }}×</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Standzeit-Klassen-Verteilung (Timing) --}}
        @if ($this->holdingClassDistribution->isNotEmpty())
            @php $hcTotal = max(1, $this->totalItems); @endphp
            <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
                    @svg('heroicon-o-fire', 'w-4 h-4 text-[var(--ui-muted)]')
                    <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Standzeit-Klassen</h2>
                    <span class="ml-auto text-[11px] text-[var(--ui-muted)]">Timing-Verteilung</span>
                </div>
                <div class="space-y-3 p-4">
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
                                <div class="h-full rounded-full" style="width: {{ $share }}%; background: {{ $color }}"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

    </div>
    </x-ui-page-container>
</x-ui-page>
