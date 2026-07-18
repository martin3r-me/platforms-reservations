<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laufzettel · {{ $sheet['event']['name'] }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: #111827; margin: 0; padding: 24px; background: #fff; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .toolbar h1 { font-size: 18px; margin: 0; }
        .meta { color: #6b7280; font-size: 12px; margin-top: 2px; }
        .btn { border: 1px solid #d1d5db; background: #f9fafb; border-radius: 8px; padding: 6px 12px; font-size: 13px; cursor: pointer; }
        .pause { border: 1px solid #e5e7eb; border-radius: 10px; margin-bottom: 18px; overflow: hidden; break-inside: avoid; }
        .pause > h2 { font-size: 15px; margin: 0; padding: 10px 14px; background: #f3f4f6; border-bottom: 1px solid #e5e7eb; }
        .pause > h2 .t { color: #6b7280; font-weight: 400; }
        .run { border-bottom: 1px solid #f0f1f3; padding: 10px 14px; break-inside: avoid; }
        .run:last-child { border-bottom: 0; }
        .run-head { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
        .dot { width: 12px; height: 12px; border-radius: 50%; border: 1px solid rgba(0,0,0,.1); display: inline-block; flex: none; }
        .run-name { font-weight: 600; font-size: 13px; }
        .badge { font-size: 11px; padding: 2px 8px; border-radius: 999px; background: #eef2ff; color: #3730a3; font-weight: 600; }
        .badge.flex { background: #ecfdf5; color: #065f46; }
        .table-block { margin: 6px 0 6px 20px; padding-left: 10px; border-left: 2px solid #e5e7eb; }
        .table-title { font-size: 12px; font-weight: 600; color: #111827; }
        .table-title .room { color: #9ca3af; font-weight: 400; }
        .booking { font-size: 12px; margin: 2px 0 2px 8px; }
        .booking .guest { color: #6b7280; }
        .booking .items { margin-left: 6px; }
        .qty { display: inline-block; min-width: 22px; font-weight: 700; }
        .empty { color: #9ca3af; font-size: 12px; padding: 10px 14px; }
        @media print {
            body { padding: 0; }
            .toolbar .btn { display: none; }
            .pause { border-color: #d1d5db; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>
            <h1>Laufzettel · {{ $sheet['event']['name'] }}</h1>
            <div class="meta">
                {{ optional($sheet['event']['date'])->format('d.m.Y') }}
                @if ($sheet['event']['venue']) · {{ $sheet['event']['venue'] }} @endif
                · erstellt {{ $sheet['generated_at']->format('d.m.Y H:i') }}
            </div>
        </div>
        <button class="btn" onclick="window.print()">Drucken</button>
    </div>

    @forelse ($sheet['pauses'] as $pause)
        <section class="pause">
            <h2>
                {{ $pause['slot']['name'] }}
                @if ($pause['slot']['time_start'])<span class="t"> · {{ $pause['slot']['time_start'] }} Uhr</span>@endif
            </h2>

            @forelse ($pause['runs'] as $run)
                <div class="run">
                    <div class="run-head">
                        <span class="dot" style="background: {{ $run['holding_class']['color'] ?? '#94a3b8' }}"></span>
                        <span class="run-name">{{ $run['label'] }}</span>
                        @if ($run['target_time'])
                            <span class="badge">platzieren bis {{ $run['target_time'] }} Uhr</span>
                        @else
                            <span class="badge flex">zeitlich egal / vorab</span>
                        @endif
                    </div>

                    @foreach ($run['tables'] as $table)
                        <div class="table-block">
                            <div class="table-title">
                                Tisch {{ $table['table']['label'] ?? '—' }}
                                @if ($table['room'])<span class="room">· {{ $table['room'] }}</span>@endif
                            </div>
                            @foreach ($table['bookings'] as $booking)
                                <div class="booking">
                                    <span class="guest">{{ $booking['guest_name'] }}:</span>
                                    <span class="items">
                                        @foreach ($booking['items'] as $item)<span class="qty">{{ $item['quantity'] }}×</span> {{ $item['name'] }}@if (! $loop->last), @endif @endforeach
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @empty
                <div class="empty">Keine Bestellungen für diese Pause.</div>
            @endforelse
        </section>
    @empty
        <p class="empty">Dieser Termin hat keine Pausen.</p>
    @endforelse
</body>
</html>
