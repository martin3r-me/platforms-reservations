@php
    $currency = strtoupper((string) config('reservation.currency', 'EUR'));
    $sym = $currency === 'EUR' ? '€' : $currency;
    $fmt = fn ($v) => number_format((float) $v, 2, ',', '.') . ' ' . $sym;
    $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',') . ' %';
    $billing = $order->billingAddress();
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #1f2937; font-size: 12px; margin: 0; }
        h1 { font-size: 18px; margin: 0; }
        .muted { color: #6b7280; }
        .eyebrow { text-transform: uppercase; letter-spacing: .08em; font-size: 10px; color: #285567; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        .meta td { padding: 3px 0; font-size: 12px; }
        .meta td.k { color: #6b7280; width: 130px; }
        .items th { text-align: left; font-size: 10px; color: #6b7280; border-bottom: 1px solid #e5e7eb; padding: 6px 4px; }
        .items td { padding: 6px 4px; border-bottom: 1px solid #f3f4f6; }
        .num { text-align: right; }
        .sum { width: 55%; float: right; margin-top: 14px; }
        .sum td { padding: 4px 4px; font-size: 12px; }
        .sum tr.total td { border-top: 2px solid #285567; font-weight: bold; font-size: 14px; padding-top: 8px; }
        .foot { color: #9ca3af; font-size: 10px; border-top: 1px solid #e5e7eb; padding-top: 10px; margin-top: 20px; }
        .addr { margin-top: 14px; font-size: 11px; color: #374151; }
    </style>
</head>
<body>
    @if ($issuer['name'])
        <div style="font-size: 10px; color: #6b7280; margin-bottom: 10px; line-height: 1.5;">
            <strong style="color: #374151;">{{ $issuer['name'] }}</strong>@if ($issuer['street']) · {{ $issuer['street'] }}@endif@if ($issuer['zip'] || $issuer['city']) · {{ $issuer['zip'] }} {{ $issuer['city'] }}@endif
            @if ($issuer['vat_id'] || $issuer['tax_number'] || $issuer['email'] || $issuer['phone'] || $issuer['website'])<br>@endif
            @if ($issuer['vat_id'])USt-IdNr: {{ $issuer['vat_id'] }}@endif@if ($issuer['tax_number']) · Steuernr.: {{ $issuer['tax_number'] }}@endif@if ($issuer['email']) · {{ $issuer['email'] }}@endif@if ($issuer['phone']) · {{ $issuer['phone'] }}@endif@if ($issuer['website']) · {{ $issuer['website'] }}@endif
        </div>
    @endif

    <table style="border-bottom: 3px solid #285567; margin-bottom: 16px;">
        <tr>
            <td style="vertical-align: top; padding-bottom: 12px;">
                <div class="eyebrow">Bestellbestätigung / Beleg</div>
                <h1>{{ $order->event?->name ?? 'PausePlus' }}</h1>
                <div class="muted" style="margin-top: 4px;">{{ optional($date)->format('d.m.Y') }}@if ($order->event?->venue) · {{ $order->event->venue->name }} @endif</div>
            </td>
            <td style="vertical-align: top; text-align: right; padding-bottom: 12px; font-size: 11px; color: #6b7280;">
                Bestellnr.<br><strong style="color: #1f2937;">{{ $order->uuid }}</strong>
            </td>
        </tr>
    </table>

    <table class="meta" style="margin-bottom: 16px;">
        <tr><td class="k">Kunde</td><td>{{ $order->customerName() }}@if ($order->company) · {{ $order->company }}@endif</td></tr>
        @if ($order->email)<tr><td class="k">E-Mail</td><td>{{ $order->email }}</td></tr>@endif
        <tr><td class="k">Status</td><td>{{ $order->status }}@if ($order->payment) · Zahlung: {{ $order->payment->status }}@endif</td></tr>
    </table>

    <table class="items">
        <thead>
            <tr><th>Position</th><th class="num">Menge</th><th class="num">Einzel</th><th class="num">MwSt</th><th class="num">Summe</th></tr>
        </thead>
        <tbody>
            @foreach ($groups as $g)
                <tr>
                    <td colspan="5" style="background: #f3f4f6; color: #285567; font-weight: bold; padding: 7px 4px;">
                        {{ $g['slot'] }}@if ($g['table']) · Tisch {{ $g['table'] }}@endif@if ($g['room']) · {{ $g['room'] }}@endif
                    </td>
                </tr>
                @foreach ($g['items'] as $line)
                    <tr>
                        <td>{{ $line['name'] }}</td>
                        <td class="num">{{ $line['quantity'] }}</td>
                        <td class="num">{{ $fmt($line['unit_price']) }}</td>
                        <td class="num">{{ $pct($line['tax_rate']) }}</td>
                        <td class="num">{{ $fmt($line['total']) }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    <table class="sum">
        <tr><td>Netto</td><td class="num">{{ $fmt($total_net) }}</td></tr>
        @foreach ($vat as $v)
            <tr><td class="muted">MwSt {{ $pct($v['tax_rate']) }} (aus {{ $fmt($v['gross']) }})</td><td class="num">{{ $fmt($v['vat']) }}</td></tr>
        @endforeach
        <tr class="total"><td>Gesamt (brutto)</td><td class="num">{{ $fmt($total_gross) }}</td></tr>
    </table>
    <div style="clear: both;"></div>

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
