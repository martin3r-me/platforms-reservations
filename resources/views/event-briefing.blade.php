<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Abend-Übersicht · {{ $sheet['event']['name'] }}</title>
    <style>
        :root { --ink:#0f172a; --muted:#64748b; --line:#e2e8f0; --accent:#285567; --accent2:#0ea5e9; --bg:#f8fafc; }
        * { box-sizing: border-box; }
        html,body { margin:0; }
        body { font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif; color:var(--ink); background:var(--bg); padding:24px; }
        .page { max-width:1000px; margin:0 auto; }

        .hero { background:linear-gradient(135deg,var(--accent),#1b3a47); color:#fff; border-radius:18px; padding:22px 26px; display:flex; justify-content:space-between; align-items:flex-start; gap:16px; }
        .hero .eyebrow { text-transform:uppercase; letter-spacing:.12em; font-size:11px; opacity:.8; margin:0 0 4px; }
        .hero h1 { margin:0; font-size:26px; line-height:1.15; }
        .hero .sub { margin-top:6px; font-size:13px; opacity:.9; }
        .btn { border:0; background:rgba(255,255,255,.16); color:#fff; border-radius:10px; padding:9px 16px; font-size:13px; font-weight:600; cursor:pointer; white-space:nowrap; }
        .btn:hover { background:rgba(255,255,255,.28); }

        .kpis { display:grid; grid-template-columns:repeat(6,1fr); gap:12px; margin-top:16px; }
        .kpi { background:#fff; border:1px solid var(--line); border-radius:14px; padding:14px 16px; }
        .kpi .label { font-size:10px; text-transform:uppercase; letter-spacing:.08em; color:var(--muted); font-weight:700; }
        .kpi .value { font-size:26px; font-weight:800; margin-top:6px; letter-spacing:-.02em; }
        .kpi .value small { font-size:14px; font-weight:600; color:var(--muted); }

        .grid { display:grid; grid-template-columns:1.15fr 1fr; gap:16px; margin-top:16px; }
        .card { background:#fff; border:1px solid var(--line); border-radius:16px; overflow:hidden; break-inside:avoid; }
        .card h2 { font-size:12px; text-transform:uppercase; letter-spacing:.08em; color:var(--muted); margin:0; padding:12px 16px; border-bottom:1px solid var(--line); }
        .card .body { padding:6px 16px 12px; }

        table { width:100%; border-collapse:collapse; font-size:13px; }
        th { text-align:left; font-size:10px; text-transform:uppercase; letter-spacing:.06em; color:var(--muted); font-weight:700; padding:8px 6px; border-bottom:1px solid var(--line); }
        td { padding:8px 6px; border-bottom:1px solid #f1f5f9; vertical-align:top; }
        tr:last-child td { border-bottom:0; }
        .num { text-align:right; font-variant-numeric:tabular-nums; font-weight:700; }
        .chip { display:inline-block; background:var(--bg); border:1px solid var(--line); border-radius:999px; padding:1px 8px; font-size:11px; margin:1px 2px 1px 0; color:#334155; }

        .bar-row { display:flex; align-items:center; gap:10px; padding:6px 0; }
        .bar-row .name { flex:1; font-size:13px; }
        .bar-wrap { flex:1.4; height:8px; background:#eef2f6; border-radius:999px; overflow:hidden; }
        .bar { height:100%; background:linear-gradient(90deg,var(--accent2),var(--accent)); }
        .bar-row .q { width:34px; text-align:right; font-weight:800; font-variant-numeric:tabular-nums; font-size:13px; }

        .full { grid-column:1 / -1; }
        .foot { color:var(--muted); font-size:11px; text-align:center; margin-top:14px; }
        .empty { color:var(--muted); font-size:13px; padding:14px 6px; }

        @media print {
            body { background:#fff; padding:0; }
            .btn { display:none; }
            .hero { border-radius:0; }
            .card, .kpi { break-inside:avoid; }
        }
        @media (max-width:860px){ .kpis{grid-template-columns:repeat(3,1fr);} .grid{grid-template-columns:1fr;} }
    </style>
</head>
<body>
<div class="page">
    <div class="hero">
        <div>
            <p class="eyebrow">Abend-Übersicht</p>
            <h1>{{ $sheet['event']['name'] }}</h1>
            <div class="sub">
                {{ optional($sheet['event']['date'])->locale('de')->isoFormat('dddd, D. MMMM Y') }}
                @if ($sheet['event']['venue']) · {{ $sheet['event']['venue'] }} @endif
            </div>
        </div>
        <button class="btn" onclick="window.print()">Drucken</button>
    </div>

    @php $t = $sheet['totals']; @endphp
    <div class="kpis">
        <div class="kpi"><div class="label">Gäste</div><div class="value">{{ $t['guests'] }}</div></div>
        <div class="kpi"><div class="label">Bestellungen</div><div class="value">{{ $t['parties'] }}</div></div>
        <div class="kpi"><div class="label">Tische</div><div class="value">{{ $t['tables'] }}</div></div>
        <div class="kpi"><div class="label">Speisen/Artikel</div><div class="value">{{ $t['items'] }}</div></div>
        <div class="kpi"><div class="label">Pausen</div><div class="value">{{ $t['pauses'] }}</div></div>
        <div class="kpi"><div class="label">Umsatz</div><div class="value">{{ number_format($t['revenue'], 0, ',', '.') }}<small> €</small></div></div>
    </div>

    <div class="grid">
        {{-- Pausen --}}
        <section class="card">
            <h2>Pausen</h2>
            <div class="body">
                <table>
                    <thead><tr><th>Pause</th><th class="num">Gäste</th><th class="num">Tische</th><th class="num">Artikel</th></tr></thead>
                    <tbody>
                        @forelse ($sheet['pauses'] as $p)
                            <tr>
                                <td><strong>{{ $p['name'] }}</strong>@if ($p['time']) <span style="color:var(--muted)"> · {{ $p['time'] }} Uhr</span>@endif</td>
                                <td class="num">{{ $p['guests'] }}</td>
                                <td class="num">{{ $p['tables'] }}</td>
                                <td class="num">{{ $p['items'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty">Keine Pausen.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Top-Speisen --}}
        <section class="card">
            <h2>Beliebteste Speisen</h2>
            <div class="body">
                @php $max = collect($sheet['top_items'])->max('quantity') ?: 1; @endphp
                @forelse ($sheet['top_items'] as $item)
                    <div class="bar-row">
                        <span class="name">{{ $item['name'] }}</span>
                        <span class="bar-wrap"><span class="bar" style="width: {{ max(6, round($item['quantity'] / $max * 100)) }}%"></span></span>
                        <span class="q">{{ $item['quantity'] }}</span>
                    </div>
                @empty
                    <div class="empty">Noch keine bestellten Artikel.</div>
                @endforelse
            </div>
        </section>

        {{-- Gästeliste --}}
        <section class="card full">
            <h2>Gästeliste ({{ count($sheet['guests']) }})</h2>
            <div class="body">
                <table>
                    <thead><tr><th>Name</th><th class="num">Pers.</th><th>Tisch(e)</th><th>Pause(n)</th><th class="num">Artikel</th></tr></thead>
                    <tbody>
                        @forelse ($sheet['guests'] as $g)
                            <tr>
                                <td><strong>{{ $g['name'] }}</strong></td>
                                <td class="num">{{ $g['count'] }}</td>
                                <td>@forelse ($g['tables'] as $tbl)<span class="chip">{{ $tbl }}</span>@empty <span style="color:var(--muted)">—</span>@endforelse</td>
                                <td>@foreach ($g['pauses'] as $pause)<span class="chip">{{ $pause }}</span>@endforeach</td>
                                <td class="num">{{ $g['items'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty">Noch keine Bestellungen.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="foot">Erstellt {{ $sheet['generated_at']->format('d.m.Y H:i') }} Uhr</div>
</div>
</body>
</html>
