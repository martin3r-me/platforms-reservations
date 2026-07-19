@php
    $currency = strtoupper((string) config('reservation.currency', 'EUR'));
    $sym = $currency === 'EUR' ? '€' : $currency;
    $fmt = fn ($v) => number_format((float) $v, 2, ',', '.') . ' ' . $sym;
    $billing = $order->billingAddress();
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #1f2937; font-size: 12px; margin: 0; }
        h1 { font-size: 20px; margin: 0; }
        .muted { color: #6b7280; }
        .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #285567; padding-bottom: 12px; margin-bottom: 16px; }
        .eyebrow { text-transform: uppercase; letter-spacing: .08em; font-size: 10px; color: #285567; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        .meta td { padding: 3px 0; font-size: 12px; }
        .meta td:first-child { color: #6b7280; width: 130px; }
        .items th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; border-bottom: 1px solid #e5e7eb; padding: 6px 4px; }
        .items td { padding: 6px 4px; border-bottom: 1px solid #f3f4f6; }
        .num { text-align: right; white-space: nowrap; }
        .sum { margin-top: 14px; margin-left: auto; width: 60%; }
        .sum td { padding: 4px 4px; font-size: 12px; }
        .sum .total td { border-top: 2px solid #285567; font-weight: bold; font-size: 14px; padding-top: 8px; }
        .foot { margin-top: 24px; color: #9ca3af; font-size: 10px; border-top: 1px solid #e5e7eb; padding-top: 10px; }
        .addr { margin-top: 14px; font-size: 11px; color: #374151; }
    </style>
</head>
<body>
    <div class="head">
        <div>
            <div class="eyebrow">Bestellbestätigung / Beleg</div>
            <h1>{{ $order->event?->name ?? 'PausePlus' }}</h1>
            <div class="muted" style="margin-top:4px;">{{ optional($date)->format('d.m.Y') }}@if ($order->event?->venue) · {{ $order->event->venue->name }} @endif</div>
        </div>
        <div class="num muted" style="font-size:11px;">
            Bestellnr.<br><strong style="font-family:monospace; color:#1f2937;">{{ $order->uuid }}</strong>
        </div>
    </div>

    <table class="meta" style="margin-bottom:16px;">
        <tr><td>Kunde</td><td>{{ $order->customerName() }}@if ($order->company) · {{ $order->company }}@endif</td></tr>
        @if ($order->email)<tr><td>E-Mail</td><td>{{ $order->email }}</td></tr>@endif
        <tr><td>Status</td><td>{{ $order->status }}@if ($order->payment) · Zahlung: {{ $order->payment->status }}@endif</td></tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Pos.</th><th>Pause</th><th class="num">Menge</th><th class="num">Einzel</th><th class="num">MwSt</th><th class="num">Summe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                <tr>
                    <td>{{ $line['name'] }}</td>
                    <td class="muted">{{ $line['slot'] }}</td>
                    <td class="num">{{ $line['quantity'] }}</td>
                    <td class="num">{{ $fmt($line['unit_price']) }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format($line['tax_rate'], 2, ',', '.'), '0'), ',') }} %</td>
                    <td class="num">{{ $fmt($line['total']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="sum">
        <tr><td>Netto</td><td class="num">{{ $fmt($total_net) }}</td></tr>
        @foreach ($vat as $v)
            <tr><td class="muted">MwSt {{ rtrim(rtrim(number_format($v['tax_rate'], 2, ',', '.'), '0'), ',') }} % (aus {{ $fmt($v['gross']) }})</td><td class="num">{{ $fmt($v['vat']) }}</td></tr>
        @endforeach
        <tr class="total"><td>Gesamt (brutto)</td><td class="num">{{ $fmt($total_gross) }}</td></tr>
    </table>

    @if ($order->company || $billing)
        <div class="addr">
            <strong>Rechnungsanschrift:</strong>
            @if ($order->company){{ $order->company }}, @endif{{ $order->customerName() }}@if ($billing), {{ $billing['street'] }}, {{ $billing['zip'] }} {{ $billing['city'] }}@if ($billing['country']) ({{ $billing['country'] }})@endif @endif
        </div>
    @endif

    <div class="foot">
        Vielen Dank für Ihre Bestellung. Alle Preise inkl. gesetzlicher MwSt. Beleg erstellt am {{ now()->format('d.m.Y H:i') }} Uhr.
    </div>
</body>
</html>
