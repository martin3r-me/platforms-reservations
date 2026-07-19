@php
    $currency = strtoupper((string) config('reservation.currency', 'EUR'));
    $sym = $currency === 'EUR' ? '€' : $currency;
    $fmt = fn ($v) => number_format((float) $v, 2, ',', '.') . ' ' . $sym;
    $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',') . ' %';
    $venue = $order->event?->venue?->name;
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #1f2937; font-size: 12px; margin: 0; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        .muted { color: #6b7280; }
        .head { border-bottom: 3px solid #285567; padding-bottom: 10px; margin-bottom: 16px; }
        .eyebrow { text-transform: uppercase; letter-spacing: .08em; font-size: 10px; color: #285567; font-weight: bold; }
        .label { font-size: 10px; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; margin-bottom: 3px; }
        .box { border: 1px solid #d1d5db; padding: 5px 8px; font-size: 12px; margin-bottom: 10px; }
        .blank { min-height: 20px; }
        .hint { font-size: 9px; color: #9ca3af; }
        table { width: 100%; border-collapse: collapse; }
        .grid td { vertical-align: top; width: 50%; }
        .grid td.left { padding-right: 8px; }
        .grid td.right { padding-left: 8px; }
        .items th { text-align: left; font-size: 10px; color: #6b7280; border-bottom: 1px solid #e5e7eb; padding: 5px 4px; }
        .items td { padding: 5px 4px; border-bottom: 1px solid #f3f4f6; }
        .num { text-align: right; }
        .sum { width: 55%; float: right; margin-top: 8px; }
        .sum td { padding: 3px 4px; }
        .sum tr.total td { border-top: 2px solid #285567; font-weight: bold; font-size: 13px; padding-top: 6px; }
        .sigbox { border-bottom: 1px solid #9ca3af; height: 26px; }
        .foot { color: #9ca3af; font-size: 9px; border-top: 1px solid #e5e7eb; padding-top: 8px; margin-top: 20px; }
    </style>
</head>
<body>
    @if ($issuer['name'])
        <div style="font-size: 10px; color: #6b7280; margin-bottom: 8px; line-height: 1.5;">
            <strong style="color: #374151;">{{ $issuer['name'] }}</strong>@if ($issuer['street']) · {{ $issuer['street'] }}@endif@if ($issuer['zip'] || $issuer['city']) · {{ $issuer['zip'] }} {{ $issuer['city'] }}@endif
            @if ($issuer['vat_id'] || $issuer['tax_number'])<br>@endif
            @if ($issuer['vat_id'])USt-IdNr: {{ $issuer['vat_id'] }}@endif@if ($issuer['tax_number']) · Steuernr.: {{ $issuer['tax_number'] }}@endif@if ($issuer['email']) · {{ $issuer['email'] }}@endif@if ($issuer['phone']) · {{ $issuer['phone'] }}@endif
        </div>
    @endif

    <div class="head">
        <div class="eyebrow">Bewirtungsbeleg</div>
        <h1>Nachweis von Bewirtungsaufwendungen</h1>
        <div class="muted">gem. § 4 Abs. 5 Satz 1 Nr. 2 EStG</div>
    </div>

    @php $billing = $order->billingAddress(); @endphp
    @if ($order->company || $billing)
        <div class="label">Firma / Anschrift</div>
        <div class="box" style="margin-bottom: 10px;">
            @if ($order->company)<strong>{{ $order->company }}</strong> · @endif{{ $order->customerName() }}@if ($billing) · {{ $billing['street'] }}, {{ $billing['zip'] }} {{ $billing['city'] }}@if ($billing['country']) ({{ $billing['country'] }})@endif @endif
        </div>
    @endif

    <table class="grid" style="margin-bottom: 6px;">
        <tr>
            <td class="left">
                <div class="label">Tag der Bewirtung</div>
                <div class="box">{{ optional($date)->format('d.m.Y') }}</div>
            </td>
            <td class="right">
                <div class="label">Ort der Bewirtung</div>
                <div class="box">{{ $venue ?: ($order->event?->name) }}</div>
            </td>
        </tr>
    </table>

    <div class="label">Anlass der Bewirtung <span class="hint">(bitte ausfüllen)</span></div>
    <div class="box blank"></div>

    <div class="label">Bewirtete Personen <span class="hint">(Namen aller Teilnehmer – bitte ausfüllen)</span></div>
    <div class="box blank"></div>
    <div class="box blank"></div>
    <div class="box blank"></div>

    <div class="label" style="margin-top: 6px;">Aufwendungen</div>
    <table class="items">
        <thead>
            <tr><th>Position</th><th class="num">Menge</th><th class="num">MwSt</th><th class="num">Betrag</th></tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                <tr>
                    <td>{{ $line['name'] }}</td>
                    <td class="num">{{ $line['quantity'] }}</td>
                    <td class="num">{{ $pct($line['tax_rate']) }}</td>
                    <td class="num">{{ $fmt($line['total']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="sum">
        <tr><td>Netto</td><td class="num">{{ $fmt($total_net) }}</td></tr>
        @foreach ($vat as $v)
            <tr><td class="muted">MwSt {{ $pct($v['tax_rate']) }}</td><td class="num">{{ $fmt($v['vat']) }}</td></tr>
        @endforeach
        <tr><td>Rechnungsbetrag (brutto)</td><td class="num">{{ $fmt($total_gross) }}</td></tr>
        <tr><td>Trinkgeld</td><td class="num">&nbsp;</td></tr>
        <tr class="total"><td>Gesamtbetrag</td><td class="num">&nbsp;</td></tr>
    </table>
    <div style="clear: both;"></div>

    <table class="grid" style="margin-top: 30px;">
        <tr>
            <td class="left">
                <div class="sigbox">&nbsp;</div>
                <div class="label">Ort, Datum</div>
            </td>
            <td class="right">
                <div class="sigbox">{{ $order->customerName() }}</div>
                <div class="label">Bewirtende Person (Unterschrift)</div>
            </td>
        </tr>
    </table>

    <div class="foot">
        Bestellnr. {{ $order->uuid }} · Beleg erstellt am {{ now()->format('d.m.Y H:i') }} Uhr. Vorausgefüllt aus der Bestellung; Anlass, Teilnehmer, Trinkgeld und Unterschrift sind vor Ort zu ergänzen.
    </div>
</body>
</html>
