@php
    $branding = $branding ?? [];
    $accent   = $branding['accent'] ?? \Platform\Reservation\Models\CheckoutSetting::DEFAULT_ACCENT;
    $logo     = $branding['logo']   ?? null;
    $footerText = $branding['footer'] ?? null;
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
        /* Ohne @page klebt der Inhalt am Blattrand – dompdf setzt von sich aus
           keinen Seitenrand, und body{margin:0} nimmt auch den letzten weg.
           Das war die Ursache für die gestauchte Optik. */
        @page { margin: 18mm 15mm 16mm; }

        body { font-family: DejaVu Sans, Arial, sans-serif; color: #1f2937; font-size: 12px; line-height: 1.5; margin: 0; }
        h1 { font-size: 19px; margin: 0; line-height: 1.25; }
        .muted { color: #6b7280; }
        .eyebrow { text-transform: uppercase; letter-spacing: .08em; font-size: 10px; color: {{ $accent }}; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        .meta td { padding: 5px 0; font-size: 12px; }
        .meta td.k { color: #6b7280; width: 130px; }
        .items th { text-align: left; font-size: 10px; color: #6b7280; border-bottom: 1px solid #e5e7eb; padding: 8px 5px; }
        /* .items th (0,1,1) schlaegt .num (0,1,0) – ohne diese Regel bleiben die
           Zahlen-Ueberschriften linksbuendig, waehrend die Werte rechts stehen. */
        .items th.num { text-align: right; }
        .items td { padding: 8px 5px; border-bottom: 1px solid #f3f4f6; }
        .num { text-align: right; white-space: nowrap; }
        .sum { width: 100%; }
        .sum td { padding: 6px 5px; font-size: 12px; }
        .sum tr.total td { border-top: 2px solid {{ $accent }}; font-weight: bold; font-size: 14px; padding-top: 10px; }
        .foot { color: #9ca3af; font-size: 10px; border-top: 1px solid #e5e7eb; padding-top: 12px; margin-top: 28px; }
        .addr { margin-top: 20px; font-size: 11px; color: #374151; }
    </style>
</head>
<body>
    @include('reservation::pdf.partials.issuer', ['issuer' => $issuer])

    <table style="border-bottom: 3px solid {{ $accent }}; margin-bottom: 22px;">
        <tr>
            <td style="vertical-align: top; padding-bottom: 14px;">
                <div class="eyebrow">Bestellbestätigung / Beleg</div>
                <h1>{{ $order->event?->name ?? 'PausePlus' }}</h1>
                <div class="muted" style="margin-top: 4px;">{{ optional($date)->format('d.m.Y') }}@if ($order->event?->venue) · {{ $order->event->venue->name }} @endif</div>
            </td>
            <td style="vertical-align: top; text-align: right; padding-bottom: 14px; font-size: 11px; color: #6b7280;">
                {{-- Logo als Data-URI: dompdf lädt keine externen Bilder. --}}
                @if ($logo)
                    <img src="{{ $logo }}" alt="" style="max-height: 46px; margin-bottom: 8px;" />
                    <br>
                @endif
                Bestellnr.<br><strong style="color: #1f2937;">{{ $order->uuid }}</strong>
            </td>
        </tr>
    </table>

    <table class="meta" style="margin-bottom: 22px;">
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
                    <td colspan="5" style="background: #f3f4f6; color: {{ $accent }}; font-weight: bold; padding: 9px 5px;">
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

    {{-- Rechtsbündig über eine Hüll-Tabelle statt float: right. Floats über einen
         Seitenumbruch rendert dompdf unzuverlässig, und mit den größeren Abständen
         rutscht der Summenblock bei langen Bestellungen leichter auf Seite 2. --}}
    <table style="margin-top: 18px;">
        <tr>
            <td style="width: 45%;"></td>
            <td style="width: 55%;">
                <table class="sum">
                    <tr><td>Netto</td><td class="num">{{ $fmt($total_net) }}</td></tr>
                    @foreach ($vat as $v)
                        <tr><td class="muted">MwSt {{ $pct($v['tax_rate']) }} (aus {{ $fmt($v['gross']) }})</td><td class="num">{{ $fmt($v['vat']) }}</td></tr>
                    @endforeach
                    <tr class="total"><td>Gesamt (brutto)</td><td class="num">{{ $fmt($total_gross) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    @if ($order->company || $billing)
        <div class="addr">
            <strong>Rechnungsanschrift:</strong>
            @if ($order->company){{ $order->company }}, @endif{{ $order->customerName() }}@if ($billing), {{ $billing['street'] }}, {{ $billing['zip'] }} {{ $billing['city'] }}@if ($billing['country']) ({{ $billing['country'] }})@endif @endif
        </div>
    @endif

    <div class="foot">
        @if ($footerText)
            {{-- Mandanten-Fußzeile zuerst: Bankverbindung, Hinweise o.ä. --}}
            <div style="margin-bottom: 6px; white-space: pre-line; color: #6b7280;">{{ $footerText }}</div>
        @endif
        Vielen Dank für Ihre Bestellung. Alle Preise inkl. gesetzlicher MwSt. Beleg erstellt am {{ now()->format('d.m.Y H:i') }} Uhr.
    </div>
</body>
</html>
