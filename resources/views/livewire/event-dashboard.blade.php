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
                <x-nx-button :href="route('reservation.guest.checkout', $this->event->uuid)" target="_blank">
                    @svg('heroicon-o-eye', 'w-4 h-4')
                    <span>Gast-Ansicht</span>
                </x-nx-button>
            @endif
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        @include('reservation::partials.event-sidebar', ['event' => $this->event, 'active' => 'dashboard'])
    </x-slot>

    <x-ui-page-container width="contained">

    {{-- Ultrawide-Ambient: generatives Bauhaus-Panel im Passepartout.
         Sichtbarkeit/Breite platz-getrieben (JS): füllt nur den echten freien Rand rechts,
         blendet aus sobald der Content ihn braucht (Sidebars offen). Hinter allem Interaktiven. --}}
    @verbatim
    <style>
        .pp-art{ position:fixed; top:88px; bottom:52px; right:0; z-index:0; pointer-events:none;
            display:none; padding:56px; background:#fff; border-left:1px solid var(--nx-line); }
        .pp-art .plate{ position:relative; width:100%; height:100%; overflow:hidden;
            background:var(--nx-bg); border:1px solid var(--nx-line); border-radius:2px; box-shadow:0 1px 3px rgba(0,0,0,.04); }
    </style>
    <script>
    (function(){
        if (window.__ppArtInit) return; window.__ppArtInit = true;
        var MIN = 380, MAX = 880, BUFFER = 24, t = 0;
        function apply(){
            var panel = document.querySelector('.pp-art');
            if (!panel) return;
            var content = document.querySelector('[data-nx-content]');
            if (!content){ panel.style.display = 'none'; return; }
            // freier Rand rechts der echten Content-Kante, minus Sicherheitsabstand
            var gap = window.innerWidth - content.getBoundingClientRect().right - BUFFER;
            if (gap >= MIN){ panel.style.display = 'block'; panel.style.width = Math.min(gap, MAX) + 'px'; }
            else { panel.style.display = 'none'; }
        }
        // Trailing-Debounce via setTimeout (feuert auch im Hintergrund-Tab, anders als rAF)
        // und wartet, bis der Livewire-Morph des Sidebar-Toggles eingerastet ist.
        function measure(){ clearTimeout(t); t = setTimeout(apply, 60); }
        // Neu berechnen bei jeder Layout-Änderung: Viewport, Sidebar-Toggle (DOM-Mutation,
        // meist ein instanter Livewire-Morph ohne Transition), evtl. Transition, Navigation.
        window.addEventListener('resize', measure);
        document.addEventListener('transitionend', measure, true);
        document.addEventListener('livewire:navigated', measure);
        try {
            new MutationObserver(measure).observe(document.body, {
                subtree: true, childList: true, attributes: true, attributeFilter: ['class', 'style'],
            });
        } catch (e) {}
        apply();
    })();
    </script>
    @endverbatim
    <div class="pp-art" aria-hidden="true">
        <div class="plate">
            <x-nx-bauhaus :seed="$artSeed" :count="7" />
        </div>
    </div>

    @php
        $currency = strtoupper((string) config('reservation.currency', 'EUR'));
        $sym = $currency === 'EUR' ? '€' : $currency;
        $s = $this->stats;
        $tiles = [
            ['Buchungen', $s['bookings']],
            ['Gäste', $s['guests']],
            ['Umsatz', number_format($s['revenue'], 2, ',', '.') . ' ' . $sym],
            [$s['pauses'] === 1 ? 'Pause' : 'Pausen', $s['pauses']],
        ];
        $statusColors = ['published' => '#2f9e44', 'draft' => '#868e96', 'closed' => '#e8590c', 'cancelled' => '#e03131'];
        $statusDot = $statusColors[$this->event->status->value] ?? '#868e96';
    @endphp

    <div class="space-y-6">

        {{-- Titel --}}
        <div>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                <h1 class="m-0 text-2xl font-bold tracking-tight text-[color:var(--nx-text)]">{{ $this->event->name }}</h1>
                <span class="inline-flex items-center gap-1.5 text-sm text-[color:var(--nx-muted)]">
                    <span class="h-2 w-2 rounded-full" style="background:{{ $statusDot }}"></span>{{ $this->event->status->label() }}
                </span>
                @if ($this->event->date->isToday())
                    <x-nx-badge variant="success">Heute</x-nx-badge>
                @endif
            </div>
            <p class="m-0 mt-1 text-sm text-[color:var(--nx-muted)]">
                {{ $this->event->date->format('d.m.Y') }}
                @if ($this->event->venue) · {{ $this->event->venue->name }} @endif
                @if ($this->event->slots->isNotEmpty()) · {{ $this->event->slots->map(fn ($sl) => $sl->displayLabel())->implode(', ') }} @endif
            </p>
        </div>

        {{-- Kennzahlen --}}
        <div class="grid grid-cols-2 gap-x-4 gap-y-4 border-y border-[color:var(--nx-line)] py-4 sm:grid-cols-4">
            @foreach ($tiles as [$label, $value])
                <div wire:key="stat-{{ $loop->index }}">
                    <div class="text-2xl font-bold leading-none tabular-nums text-[color:var(--nx-text)]">{{ $value }}</div>
                    <div class="mt-1.5 text-xs text-[color:var(--nx-muted)]">{{ $label }}</div>
                </div>
            @endforeach
        </div>

        {{-- Bestellte Artikel --}}
        @if ($this->itemsByCategory->isNotEmpty())
            <section class="space-y-3">
                <div class="flex items-baseline gap-2">
                    <h2 class="m-0 text-sm font-semibold text-[color:var(--nx-text)]">Bestellte Artikel</h2>
                    <span class="ml-auto text-xs text-[color:var(--nx-faint)]">{{ $this->totalItems }} Stück</span>
                </div>
                <x-nx-card flush>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($this->itemsByCategory as $category => $items)
                            <div class="p-4" wire:key="cat-{{ $loop->index }}">
                                <div class="mb-1 flex items-center justify-between border-b border-[color:var(--nx-line)] pb-2 text-xs font-semibold text-[color:var(--nx-muted)]">
                                    <span class="truncate">{{ $category }}</span>
                                    <span class="tabular-nums">{{ $items->sum('quantity') }}</span>
                                </div>
                                @foreach ($items as $item)
                                    <div class="flex items-center justify-between gap-2 border-b border-[color:var(--nx-line)] py-1.5 text-sm" wire:key="cat-{{ $loop->parent->index }}-i-{{ $loop->index }}">
                                        <span class="min-w-0 truncate text-[color:var(--nx-text)]">{{ $item['name'] }}</span>
                                        <span class="shrink-0 font-semibold tabular-nums text-[color:var(--nx-text)]">{{ $item['quantity'] }}×</span>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </x-nx-card>
            </section>
        @endif

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{-- Standzeit-Klassen --}}
            @if ($this->holdingClassDistribution->isNotEmpty())
                @php $hcTotal = max(1, $this->totalItems); @endphp
                <section class="space-y-3">
                    <div class="flex items-baseline gap-2">
                        <h2 class="m-0 text-sm font-semibold text-[color:var(--nx-text)]">Standzeit-Klassen</h2>
                        <span class="ml-auto text-xs text-[color:var(--nx-faint)]">Timing</span>
                    </div>
                    <div class="space-y-3.5">
                        @foreach ($this->holdingClassDistribution as $hc)
                            @php $share = round($hc['quantity'] / $hcTotal * 100); $color = $hc['color'] ?: '#9b9a97'; @endphp
                            <div wire:key="hc-{{ $loop->index }}">
                                <div class="mb-1 flex items-center gap-2 text-sm">
                                    <span class="h-3 w-3 shrink-0 rounded-full" style="background:{{ $color }}"></span>
                                    <span class="text-[color:var(--nx-text)]">{{ $hc['name'] }}</span>
                                    @if ($hc['lead_time_minutes'] !== null)
                                        <span class="rounded-full px-2 py-0.5 text-xs text-[color:var(--nx-muted)]" style="background:var(--nx-accent-soft)">{{ $hc['lead_time_minutes'] }} min vor</span>
                                    @endif
                                    <span class="ml-auto shrink-0 tabular-nums text-[color:var(--nx-muted)]"><span class="font-semibold text-[color:var(--nx-text)]">{{ $hc['quantity'] }}×</span> · {{ $share }} %</span>
                                </div>
                                <div class="h-1.5 w-full overflow-hidden rounded-full bg-[color:var(--nx-active)]">
                                    <div class="h-full rounded-full" style="width:{{ $share }}%;background:{{ $color }}"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Auslastung --}}
            @if ($this->roomUtilization->isNotEmpty())
                <section class="space-y-3">
                    <div class="flex items-center gap-3">
                        <h2 class="m-0 text-sm font-semibold text-[color:var(--nx-text)]">Auslastung</h2>
                        <span class="ml-auto flex items-center gap-3 text-xs text-[color:var(--nx-faint)]">
                            <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-sm" style="background:var(--nx-accent)"></span>belegt</span>
                            <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-sm" style="background:#e03131"></span>gesperrt</span>
                            <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-sm bg-[color:var(--nx-active)]"></span>frei</span>
                        </span>
                    </div>
                    <div class="space-y-3.5">
                        @foreach ($this->roomUtilization as $r)
                            @php $total = max(1, $r['total']); @endphp
                            <div wire:key="util-{{ $loop->index }}">
                                <div class="mb-1 flex items-center gap-2 text-sm">
                                    <span class="text-[color:var(--nx-text)]">{{ $r['room'] }}</span>
                                    <span class="ml-auto shrink-0 text-xs tabular-nums text-[color:var(--nx-muted)]">
                                        <span class="font-semibold text-[color:var(--nx-text)]">{{ $r['occupied'] }}</span> belegt @if ($r['blocked'] > 0)· {{ $r['blocked'] }} gesperrt @endif· {{ $r['free'] }} frei / {{ $r['total'] }}
                                    </span>
                                </div>
                                <div class="flex h-2.5 w-full overflow-hidden rounded-full bg-[color:var(--nx-active)]">
                                    <div class="h-full" style="width:{{ $r['occupied'] / $total * 100 }}%;background:var(--nx-accent)"></div>
                                    <div class="h-full" style="width:{{ $r['blocked'] / $total * 100 }}%;background:#e03131"></div>
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
