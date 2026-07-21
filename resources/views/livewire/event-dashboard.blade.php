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
        $nav = [
            ['reservation.events.orders', 'heroicon-o-fire', 'Küche', 'Gesamtbestellungen je Pause'],
            ['reservation.events.function', 'heroicon-o-clipboard-document-list', 'Laufzettel', 'Laufrunden: Klasse → Tisch → Bestellung'],
            ['reservation.events.overview', 'heroicon-o-presentation-chart-bar', 'Abend-Übersicht', 'Kennzahlen, Top-Speisen, Gästeliste'],
        ];
        $statusColors = ['published' => '#2f9e44', 'draft' => '#868e96', 'closed' => '#e8590c', 'cancelled' => '#e03131'];
        $statusDot = $statusColors[$this->event->status->value] ?? '#868e96';
    @endphp

    <style>
        @verbatim
        .pp-dash{
            --pp-bg:#faf9f7; --pp-surface:#fff; --pp-text:#37352f; --pp-muted:#787774; --pp-faint:#9b9a97;
            --pp-line:rgba(55,53,47,.09); --pp-line-2:rgba(55,53,47,.06); --pp-hover:rgba(55,53,47,.045);
            --pp-accent:#285567; --pp-accent-soft:rgba(40,85,103,.10);
            background:var(--pp-bg); color:var(--pp-text);
            margin:-1rem -1.5rem -2rem; padding:2.25rem 2rem 3rem;
            min-height:calc(100vh - 7.5rem);
            -webkit-font-smoothing:antialiased;
        }
        .pp-wrap{max-width:1040px; margin:0 auto;}
        .pp-title{font-size:1.9rem; font-weight:700; line-height:1.15; letter-spacing:-.01em; margin:0; color:var(--pp-text);}
        .pp-meta{font-size:.875rem; color:var(--pp-muted); margin:.35rem 0 0;}
        .pp-status{display:inline-flex; align-items:center; gap:.4rem; font-size:.8rem; color:var(--pp-muted); vertical-align:middle; margin-left:.6rem;}
        .pp-status .dot{width:.5rem; height:.5rem; border-radius:50%;}
        .pp-today{margin-left:.5rem; font-size:.7rem; font-weight:600; color:#2f9e44; background:rgba(47,158,68,.12); padding:.1rem .5rem; border-radius:999px; vertical-align:middle;}

        .pp-stats{display:flex; flex-wrap:wrap; margin-top:1.75rem; border-top:1px solid var(--pp-line); border-bottom:1px solid var(--pp-line);}
        .pp-stat{flex:1 1 0; min-width:120px; padding:1rem 1.25rem 1rem 0; }
        .pp-stat + .pp-stat{padding-left:1.5rem; border-left:1px solid var(--pp-line);}
        .pp-stat .num{font-size:1.6rem; font-weight:650; line-height:1; letter-spacing:-.01em; font-variant-numeric:tabular-nums;}
        .pp-stat .lbl{font-size:.78rem; color:var(--pp-muted); margin-top:.35rem;}

        .pp-nav{display:grid; grid-template-columns:repeat(3,1fr); gap:.5rem; margin-top:1.5rem;}
        @media (max-width:720px){ .pp-nav{grid-template-columns:1fr;} }
        .pp-navitem{display:flex; align-items:center; gap:.75rem; padding:.7rem .85rem; border-radius:8px; text-decoration:none; color:var(--pp-text); transition:background .12s ease;}
        .pp-navitem:hover{background:var(--pp-hover);}
        .pp-navitem .ico{color:var(--pp-muted); flex:none; display:flex;}
        .pp-navitem:hover .ico{color:var(--pp-accent);}
        .pp-navitem .t{font-size:.9rem; font-weight:600; line-height:1.2;}
        .pp-navitem .d{font-size:.75rem; color:var(--pp-faint); margin-top:.1rem; line-height:1.2;}
        .pp-navitem .arrow{margin-left:auto; color:var(--pp-faint); flex:none; transition:transform .12s ease, color .12s ease;}
        .pp-navitem:hover .arrow{transform:translateX(2px); color:var(--pp-muted);}

        .pp-sec{margin-top:2.25rem;}
        .pp-sec-head{display:flex; align-items:baseline; gap:.5rem; margin-bottom:.85rem;}
        .pp-sec-title{font-size:.95rem; font-weight:650; color:var(--pp-text); margin:0;}
        .pp-sec-count{font-size:.78rem; color:var(--pp-faint); margin-left:auto;}

        .pp-cats{display:grid; grid-template-columns:repeat(3,1fr); gap:0 2.25rem;}
        @media (max-width:820px){ .pp-cats{grid-template-columns:repeat(2,1fr);} }
        @media (max-width:520px){ .pp-cats{grid-template-columns:1fr;} }
        .pp-cat{padding:.25rem 0 1rem;}
        .pp-cat-h{display:flex; align-items:center; justify-content:space-between; font-size:.78rem; color:var(--pp-muted); font-weight:600; padding:.4rem 0; border-bottom:1px solid var(--pp-line);}
        .pp-cat-h .c{color:var(--pp-faint); font-variant-numeric:tabular-nums;}
        .pp-item{display:flex; align-items:center; justify-content:space-between; gap:.75rem; padding:.4rem 0; font-size:.875rem; border-bottom:1px solid var(--pp-line-2);}
        .pp-item .n{color:var(--pp-text); min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;}
        .pp-item .q{flex:none; font-weight:650; color:var(--pp-text); font-variant-numeric:tabular-nums;}

        .pp-2col{display:grid; grid-template-columns:1fr 1fr; gap:2.5rem;}
        @media (max-width:820px){ .pp-2col{grid-template-columns:1fr; gap:2.25rem;} }
        .pp-bar-row{margin-bottom:1rem;}
        .pp-bar-row:last-child{margin-bottom:0;}
        .pp-bar-head{display:flex; align-items:center; gap:.5rem; font-size:.875rem; margin-bottom:.4rem;}
        .pp-bar-head .name{color:var(--pp-text); font-weight:500;}
        .pp-bar-head .val{margin-left:auto; color:var(--pp-muted); font-variant-numeric:tabular-nums; font-size:.8rem;}
        .pp-bar-head .val b{color:var(--pp-text); font-weight:650;}
        .pp-dot{width:.6rem; height:.6rem; border-radius:50%; flex:none;}
        .pp-pill{font-size:.68rem; color:var(--pp-muted); background:rgba(55,53,47,.05); padding:.05rem .4rem; border-radius:999px;}
        .pp-track{height:6px; background:rgba(55,53,47,.06); border-radius:999px; overflow:hidden; display:flex;}
        .pp-track > span{height:100%; display:block;}
        .pp-legend{display:flex; gap:.9rem; font-size:.7rem; color:var(--pp-faint);}
        .pp-legend i{display:inline-block; width:.55rem; height:.55rem; border-radius:2px; margin-right:.3rem; vertical-align:middle;}
        .pp-empty{color:var(--pp-faint); font-size:.85rem; padding:.5rem 0;}
        @endverbatim
    </style>

    <div class="pp-dash">
    <div class="pp-wrap">

        {{-- Titel --}}
        <div>
            <h1 class="pp-title">
                {{ $this->event->name }}
                <span class="pp-status"><span class="dot" style="background:{{ $statusDot }}"></span>{{ $this->event->status->label() }}</span>
                @if ($this->event->date->isToday())<span class="pp-today">Heute</span>@endif
            </h1>
            <p class="pp-meta">
                {{ $this->event->date->format('d.m.Y') }}
                @if ($this->event->venue) · {{ $this->event->venue->name }} @endif
                @if ($this->event->slots->isNotEmpty()) · {{ $this->event->slots->map(fn ($sl) => $sl->displayLabel())->implode(', ') }} @endif
            </p>
        </div>

        {{-- Kennzahlen --}}
        <div class="pp-stats">
            @foreach ($tiles as [$label, $value])
                <div class="pp-stat">
                    <div class="num">{{ $value }}</div>
                    <div class="lbl">{{ $label }}</div>
                </div>
            @endforeach
        </div>

        {{-- Einstiege --}}
        <div class="pp-nav">
            @foreach ($nav as [$route, $icon, $title, $desc])
                <a href="{{ route($route, $this->event->id) }}" wire:navigate class="pp-navitem">
                    <span class="ico">@svg($icon, 'w-5 h-5')</span>
                    <span>
                        <span class="t">{{ $title }}</span>
                        <span class="d" style="display:block">{{ $desc }}</span>
                    </span>
                    <span class="arrow">@svg('heroicon-o-arrow-right', 'w-4 h-4')</span>
                </a>
            @endforeach
        </div>

        {{-- Bestellte Artikel --}}
        @if ($this->itemsByCategory->isNotEmpty())
            <div class="pp-sec">
                <div class="pp-sec-head">
                    <h2 class="pp-sec-title">Bestellte Artikel</h2>
                    <span class="pp-sec-count">{{ $this->totalItems }} Stück</span>
                </div>
                <div class="pp-cats">
                    @foreach ($this->itemsByCategory as $category => $items)
                        <div class="pp-cat" wire:key="cat-{{ $loop->index }}">
                            <div class="pp-cat-h"><span>{{ $category }}</span><span class="c">{{ $items->sum('quantity') }}</span></div>
                            @foreach ($items as $item)
                                <div class="pp-item" wire:key="cat-{{ $loop->parent->index }}-i-{{ $loop->index }}">
                                    <span class="n">{{ $item['name'] }}</span>
                                    <span class="q">{{ $item['quantity'] }}×</span>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="pp-2col pp-sec">
            {{-- Standzeit-Klassen --}}
            @if ($this->holdingClassDistribution->isNotEmpty())
                @php $hcTotal = max(1, $this->totalItems); @endphp
                <div>
                    <div class="pp-sec-head"><h2 class="pp-sec-title">Standzeit-Klassen</h2><span class="pp-sec-count">Timing</span></div>
                    @foreach ($this->holdingClassDistribution as $hc)
                        @php $share = round($hc['quantity'] / $hcTotal * 100); $color = $hc['color'] ?: '#9b9a97'; @endphp
                        <div class="pp-bar-row" wire:key="hc-{{ $loop->index }}">
                            <div class="pp-bar-head">
                                <span class="pp-dot" style="background:{{ $color }}"></span>
                                <span class="name">{{ $hc['name'] }}</span>
                                @if ($hc['lead_time_minutes'] !== null)<span class="pp-pill">{{ $hc['lead_time_minutes'] }} min vor</span>@endif
                                <span class="val"><b>{{ $hc['quantity'] }}×</b> · {{ $share }} %</span>
                            </div>
                            <div class="pp-track"><span style="width:{{ $share }}%; background:{{ $color }}"></span></div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Auslastung --}}
            @if ($this->roomUtilization->isNotEmpty())
                <div>
                    <div class="pp-sec-head">
                        <h2 class="pp-sec-title">Auslastung</h2>
                        <span class="pp-legend" style="margin-left:auto">
                            <span><i style="background:var(--pp-accent)"></i>belegt</span>
                            <span><i style="background:#e03131"></i>gesperrt</span>
                            <span><i style="background:rgba(55,53,47,.10)"></i>frei</span>
                        </span>
                    </div>
                    @foreach ($this->roomUtilization as $r)
                        @php $total = max(1, $r['total']); @endphp
                        <div class="pp-bar-row" wire:key="util-{{ $loop->index }}">
                            <div class="pp-bar-head">
                                <span class="name">{{ $r['room'] }}</span>
                                <span class="val"><b>{{ $r['occupied'] }}</b> belegt @if ($r['blocked'] > 0)· {{ $r['blocked'] }} gesperrt @endif· {{ $r['free'] }} frei / {{ $r['total'] }}</span>
                            </div>
                            <div class="pp-track">
                                <span style="width:{{ $r['occupied'] / $total * 100 }}%; background:var(--pp-accent)"></span>
                                <span style="width:{{ $r['blocked'] / $total * 100 }}%; background:#e03131"></span>
                                <span style="width:{{ $r['free'] / $total * 100 }}%; background:transparent"></span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>
    </div>
    </x-ui-page-container>
</x-ui-page>
