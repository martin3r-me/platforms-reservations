@php
    $branding = $branding ?? [];
    $accent   = $branding['accent'] ?? \Platform\Reservation\Models\CheckoutSetting::DEFAULT_ACCENT;
    $logo     = $branding['logo']   ?? null;
    $footerText = $branding['footer'] ?? null;
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
        /* Siehe order-receipt: ohne @page klebt der Inhalt am Blattrand. Der
           Bewirtungsbeleg braucht zusätzlich Platz für Unterschriften, daher
           unten etwas mehr Rand. */
        @page { margin: 16mm 15mm 18mm; }

        body { font-family: DejaVu Sans, Arial, sans-serif; color: #1f2937; font-size: 12px; line-height: 1.45; margin: 0; }
        h1 { font-size: 19px; margin: 0 0 2px; line-height: 1.25; }
        .muted { color: #6b7280; }
        .head { border-bottom: 3px solid {{ $accent }}; padding-bottom: 12px; margin-bottom: 20px; }
        .eyebrow { text-transform: uppercase; letter-spacing: .08em; font-size: 10px; color: {{ $accent }}; font-weight: bold; }
        .label { font-size: 10px; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; margin-bottom: 3px; }
        .box { border: 1px solid #d1d5db; padding: 7px 9px; font-size: 12px; margin-bottom: 12px; }
        .blank { min-height: 20px; }
        .hint { font-size: 9px; color: #9ca3af; }
        table { width: 100%; border-collapse: collapse; }
        .grid td { vertical-align: top; width: 50%; }
        .grid td.left { padding-right: 8px; }
        .grid td.right { padding-left: 8px; }
        .items th { text-align: left; font-size: 10px; color: #6b7280; border-bottom: 1px solid #e5e7eb; padding: 7px 5px; }
        /* .items th (0,1,1) schlaegt .num (0,1,0) – ohne diese Regel bleiben die
           Zahlen-Ueberschriften linksbuendig, waehrend die Werte rechts stehen. */
        .items th.num { text-align: right; }
        .items td { padding: 7px 5px; border-bottom: 1px solid #f3f4f6; }
        .num { text-align: right; white-space: nowrap; }
        .sum { width: 100%; }
        .sum td { padding: 5px 5px; }
        .sum tr.total td { border-top: 2px solid {{ $accent }}; font-weight: bold; font-size: 13px; padding-top: 6px; }
        .sigbox { border-bottom: 1px solid #9ca3af; height: 26px; }
        .foot { color: #9ca3af; font-size: 9px; border-top: 1px solid #e5e7eb; padding-top: 8px; margin-top: 20px; }
    </style>
</head>
<body>
    @include('reservation::pdf.partials.issuer', ['issuer' => $issuer])

    <div class="head">
        <table>
            <tr>
                <td style="vertical-align: top;">
                    <div class="eyebrow">Bewirtungsbeleg</div>
                    <h1>Nachweis von Bewirtungsaufwendungen</h1>
                    <div class="muted">gem. § 4 Abs. 5 Satz 1 Nr. 2 EStG</div>
                </td>
                {{-- Logo als Data-URI: dompdf lädt keine externen Bilder. --}}
                @if ($logo)
                    <td style="vertical-align: top; text-align: right; width: 30%;">
                        <img src="{{ $logo }}" alt="" style="max-height: 46px;" />
                    </td>
                @endif
            </tr>
        </table>
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
                    {{-- Flache Aufstellung: Bundle-Bestandteile bleiben einzelne
                         Positionen (jede mit eigenem Steuersatz), der Bundle-Name
                         steht als Herkunft dahinter. --}}
                    <td>{{ $line['name'] }}@if (! empty($line['bundle_name']))
                            <span class="hint">aus {{ $line['bundle_name'] }}</span>
                        @endif</td>
                    <td class="num">{{ $line['quantity'] }}</td>
                    <td class="num">{{ $pct($line['tax_rate']) }}</td>
                    <td class="num">{{ $fmt($line['total']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Rechtsbündig über eine Hüll-Tabelle statt float: right – siehe order-receipt. --}}
    <table style="margin-top: 12px;">
        <tr>
            <td style="width: 45%;"></td>
            <td style="width: 55%;">
                <table class="sum">
                    <tr><td>Netto</td><td class="num">{{ $fmt($total_net) }}</td></tr>
                    @foreach ($vat as $v)
                        <tr><td class="muted">MwSt {{ $pct($v['tax_rate']) }}</td><td class="num">{{ $fmt($v['vat']) }}</td></tr>
                    @endforeach
                    <tr><td>Rechnungsbetrag (brutto)</td><td class="num">{{ $fmt($total_gross) }}</td></tr>
                    <tr><td>Trinkgeld</td><td class="num">&nbsp;</td></tr>
                    <tr class="total"><td>Gesamtbetrag</td><td class="num">&nbsp;</td></tr>
                </table>
            </td>
        </tr>
    </table>

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
        @if ($footerText)
            <div style="margin-bottom: 6px; white-space: pre-line;">{{ $footerText }}</div>
        @endif
        Bestellnr. {{ $order->uuid }} · Beleg erstellt am {{ now()->format('d.m.Y H:i') }} Uhr. Vorausgefüllt aus der Bestellung; Anlass, Teilnehmer, Trinkgeld und Unterschrift sind vor Ort zu ergänzen.
    </div>
</body>
</html>
