@php
    $currency = strtoupper((string) config('reservation.currency', 'EUR'));
    $sym = $currency === 'EUR' ? '€' : $currency;
    $fmt = fn ($v) => number_format((float) $v, 2, ',', '.') . ' ' . $sym;
    $venue = $order->event?->venue?->name;
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #1f2937; font-size: 12px; margin: 0; }
        h1 { font-size: 20px; margin: 0 0 2px; }
        .muted { color: #6b7280; }
        .head { border-bottom: 3px solid #285567; padding-bottom: 10px; margin-bottom: 16px; }
        .eyebrow { text-transform: uppercase; letter-spacing: .08em; font-size: 10px; color: #285567; font-weight: bold; }
        .row { display: flex; gap: 16px; margin-bottom: 10px; }
        .field { flex: 1; }
        .label { font-size: 10px; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; margin-bottom: 3px; }
        .box { border: 1px solid #d1d5db; border-radius: 4px; min-height: 22px; padding: 4px 8px; font-size: 12px; }
        .blank { border-bottom: 1px solid #9ca3af; min-height: 22px; }
        .lines .blank { margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .items th { text-align: left; font-size: 10px; text-transform: uppercase; color: #6b7280; border-bottom: 1px solid #e5e7eb; padding: 5px 4px; }
        .items td { padding: 5px 4px; border-bottom: 1px solid #f3f4f6; }
        .num { text-align: right; white-space: nowrap; }
        .sum { margin-top: 10px; margin-left: auto; width: 55%; }
        .sum td { padding: 3px 4px; }
        .sum .total td { border-top: 2px solid #285567; font-weight: bold; font-size: 13px; padding-top: 6px; }
        .sig { margin-top: 30px; display: flex; gap: 24px; }
        .sig .field { flex: 1; }
        .sig .blank { margin-bottom: 4px; }
        .foot { margin-top: 20px; color: #9ca3af; font-size: 9px; border-top: 1px solid #e5e7eb; padding-top: 8px; }
        .hint { font-size: 9px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="head">
        <div class="eyebrow">Bewirtungsbeleg</div>
        <h1>Nachweis von Bewirtungsaufwendungen</h1>
        <div class="muted">gem. § 4 Abs. 5 Satz 1 Nr. 2 EStG</div>
    </div>

    <div class="row">
        <div class="field">
            <div class="label">Tag der Bewirtung</div>
            <div class="box">{{ optional($date)->format('d.m.Y') }}</div>
        </div>
        <div class="field">
            <div class="label">Ort der Bewirtung</div>
            <div class="box">{{ $venue ?: ($order->event?->name) }}</div>
        </div>
    </div>

    <div class="field" style="margin-bottom:10px;">
        <div class="label">Anlass der Bewirtung <span class="hint">(bitte ausfüllen)</span></div>
        <div class="box blank"></div>
    </div>

    <div class="field lines" style="margin-bottom:10px;">
        <div class="label">Bewirtete Personen <span class="hint">(Namen aller Teilnehmer – bitte ausfüllen)</span></div>
        <div class="box blank"></div>
        <div class="box blank"></div>
        <div class="box blank"></div>
    </div>

    {{-- Verzehr / Aufwendungen (aus der Bestellung) --}}
    <div class="label" style="margin-top:6px;">Aufwendungen</div>
    <table class="items">
        <thead>
            <tr><th>Position</th><th class="num">Menge</th><th class="num">MwSt</th><th class="num">Betrag</th></tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                <tr>
                    <td>{{ $line['name'] }}</td>
                    <td class="num">{{ $line['quantity'] }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format($line['tax_rate'], 2, ',', '.'), '0'), ',') }} %</td>
                    <td class="num">{{ $fmt($line['total']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="sum">
        <tr><td>Netto</td><td class="num">{{ $fmt($total_net) }}</td></tr>
        @foreach ($vat as $v)
            <tr><td class="muted">MwSt {{ rtrim(rtrim(number_format($v['tax_rate'], 2, ',', '.'), '0'), ',') }} %</td><td class="num">{{ $fmt($v['vat']) }}</td></tr>
        @endforeach
        <tr><td>Rechnungsbetrag (brutto)</td><td class="num">{{ $fmt($total_gross) }}</td></tr>
        <tr><td>Trinkgeld</td><td class="num blank" style="min-width:80px;">&nbsp;</td></tr>
        <tr class="total"><td>Gesamtbetrag</td><td class="num blank">&nbsp;</td></tr>
    </table>

    <div class="sig">
        <div class="field">
            <div class="blank">&nbsp;</div>
            <div class="label">Ort, Datum</div>
        </div>
        <div class="field">
            <div class="blank">{{ $order->customerName() }}</div>
            <div class="label">Bewirtende Person (Unterschrift)</div>
        </div>
    </div>

    <div class="foot">
        Bestellnr. {{ $order->uuid }} · Beleg erstellt am {{ now()->format('d.m.Y H:i') }} Uhr. Vorausgefüllt aus der Bestellung; Anlass, Teilnehmer, Trinkgeld und Unterschrift sind vor Ort zu ergänzen.
    </div>
</body>
</html>
