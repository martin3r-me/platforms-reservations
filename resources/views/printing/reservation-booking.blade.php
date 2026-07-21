@php
    /** @var \Platform\Reservation\Models\Booking $printable */
    /** @var \Platform\Printing\Models\PrintJob $job */

    // Bon-Drucker optimierte Formatierung (80mm = ~48 Zeichen)
    $width = 48;
    $sep   = str_repeat('=', $width);
    $line  = str_repeat('-', $width);

    // Zeile mit Bezeichnung links, Wert rechtsbündig.
    // WICHTIG: mb_strlen (Zeichen), nicht strlen (Bytes) – sonst verrutscht
    // die Wert-Spalte bei Umlauten/ß (ä/ö/ü/ß sind in UTF-8 je 2 Bytes).
    $row = function (string $left, string $right) use ($width) {
        $right = (string) $right;
        $left  = \Illuminate\Support\Str::limit($left, max(1, $width - mb_strlen($right) - 1), '');
        $pad   = max(1, $width - mb_strlen($left) - mb_strlen($right));
        return $left . str_repeat(' ', $pad) . $right;
    };

    // Mittig zentrieren – mb-aware, ohne mb_str_pad (PHP-Version-unabhängig)
    $center = function (string $s, int $w) {
        $s   = (string) $s;
        $pad = max(0, $w - mb_strlen($s));
        $l   = intdiv($pad, 2);
        return str_repeat(' ', $l) . $s . str_repeat(' ', $pad - $l);
    };

    $money = fn ($v) => number_format((float) $v, 2, ',', '.');

    $currency = strtoupper((string) config('reservation.currency', 'EUR'));
    $sym      = $currency === 'EUR' ? 'EUR' : $currency;

    // Aussteller-Stammdaten (Team der Buchung)
    $settings = \Platform\Reservation\Models\CheckoutSetting::forTeam((int) $printable->team_id);
    $issuer   = $settings->hasIssuer() ? $settings->issuer() : null;

    // Order-/Zahlungs-Kontext
    $order   = $printable->order;
    $payment = $printable->payment; // Accessor: order->payment
    $payLabels = ['card' => 'Karte', 'paypal' => 'PayPal', 'applepay' => 'Apple Pay', 'ideal' => 'iDEAL', 'sofort' => 'Sofort'];

    // Steuersatz-Klassen (A, B, C …) + MwSt-Summen je Satz
    $byRate = [];
    foreach ($printable->items as $it) {
        $r = (float) $it->tax_rate;
        $byRate[(string) $r] = ($byRate[(string) $r] ?? 0) + ((float) $it->unit_price * $it->quantity);
    }
    ksort($byRate, SORT_NUMERIC);
    $letters = [];
    $i = 0;
    foreach (array_keys($byRate) as $rk) {
        $letters[$rk] = chr(65 + $i);
        $i++;
    }
    $letterFor = fn ($rate) => $letters[(string) (float) $rate] ?? '';

    $netTotal = 0.0; $vatTotal = 0.0; $grossTotal = 0.0;
    $vatRows = [];
    foreach ($byRate as $rk => $gross) {
        $v = \Platform\Reservation\Support\Vat::fromGross((float) $gross, (float) $rk);
        $vatRows[] = ['letter' => $letters[$rk], 'rate' => (float) $rk] + $v;
        $netTotal += $v['net']; $vatTotal += $v['vat']; $grossTotal += $v['gross'];
    }
    $ratePct = fn ($r) => rtrim(rtrim(number_format((float) $r, 1, ',', ''), '0'), ',');
@endphp
@if($issuer)
{{ $center($issuer['name'], $width) }}
@if($issuer['street']){{ $center($issuer['street'], $width) }}
@endif
@if($issuer['zip'] || $issuer['city']){{ $center(trim(($issuer['zip'] ?? '') . ' ' . ($issuer['city'] ?? '')), $width) }}
@endif
@if($issuer['vat_id']){{ $center('USt-IdNr: ' . $issuer['vat_id'], $width) }}
@endif
@if($issuer['tax_number']){{ $center('Steuer-Nr: ' . $issuer['tax_number'], $width) }}
@endif
@endif
{{ $sep }}
{{ $center('BON  Buchung #' . $printable->id, $width) }}
{{ $sep }}

@if($printable->event)
{{ $row('VA:', Str::limit($printable->event->name, $width - 5)) }}
@endif
{{ str_pad('Datum:', 12) }}{{ $printable->date?->format('d.m.Y') }}@if($printable->time_start) · {{ substr($printable->time_start, 0, 5) }} Uhr @endif
@if($printable->slot)
{{ str_pad('Pause:', 12) }}{{ Str::limit($printable->slot->name, $width - 12) }}
@endif
@if($printable->table)
{{ str_pad('Tisch:', 12) }}{{ $printable->table->label }}@if($printable->table->floorPlan) · {{ Str::limit($printable->table->floorPlan->name, 24) }}@endif
@endif
{{ str_pad('Gast:', 12) }}{{ Str::limit($printable->guest_name ?? '-', $width - 12) }}
{{ str_pad('Personen:', 12) }}{{ $printable->guest_count }}
@if($order)
{{ str_pad('Bestellung:', 12) }}{{ $order->uuid }}
@endif
@if($payment)
{{ str_pad('Zahlung:', 12) }}{{ $payment->status }}@if($printable->payment_method) · {{ $payLabels[$printable->payment_method] ?? $printable->payment_method }}@endif
@endif
{{ str_pad('Gebucht:', 12) }}{{ $printable->created_at?->format('d.m.Y H:i') }}

{{ $line }}
{{ str_pad('ARTIKEL', 12) }}
{{ $line }}
@forelse($printable->items as $item)
@php $right = $money($item->unit_price * $item->quantity) . ' ' . $sym . ' ' . $letterFor($item->tax_rate); @endphp
{{ $row($item->quantity . 'x ' . ($item->menuItem?->name ?? 'Artikel'), $right) }}
@if($item->notes)
   > {{ Str::limit($item->notes, $width - 5) }}
@endif
@empty
{{ 'Keine Vorbestellung - nur Tischreservierung' }}
@endforelse
@if($printable->items->isNotEmpty())
{{ $line }}
{{ $row('SUMME (brutto)', $money($grossTotal) . ' ' . $sym) }}

{{ $line }}
{{ str_pad('MWST-AUSWEIS', 12) }}
{{ $line }}
@foreach($vatRows as $vr)
{{ $vr['letter'] }} · {{ $ratePct($vr['rate']) }}% · Netto {{ $money($vr['net']) }}
{{ $row('     MwSt', $money($vr['vat']) . ' ' . $sym) }}
@endforeach
{{ $line }}
{{ $row('Netto gesamt', $money($netTotal) . ' ' . $sym) }}
{{ $row('MwSt gesamt', $money($vatTotal) . ' ' . $sym) }}
{{ $row('Brutto gesamt', $money($grossTotal) . ' ' . $sym) }}
@endif

@if($order && $order->hasBusinessData())
{{ $line }}
{{ str_pad('RECHNUNGSANSCHRIFT', 12) }}
{{ $line }}
{{ Str::limit($order->company, $width) }}
@if($order->customerName()){{ Str::limit($order->customerName(), $width) }}
@endif
@php $ba = $order->billingAddress(); @endphp
@if($ba)
@if($ba['street']){{ Str::limit($ba['street'], $width) }}
@endif
{{ trim(($ba['zip'] ?? '') . ' ' . ($ba['city'] ?? '')) }}@if($ba['country']) · {{ $ba['country'] }}@endif
@endif
@endif

@if($printable->notes)
{{ $line }}
{{ str_pad('Anmerkung:', 12) }}
{{ wordwrap($printable->notes, $width, "\n", true) }}
@endif
{{ $sep }}
@if($issuer && ($issuer['email'] || $issuer['phone'] || $issuer['website']))
{{ $center(trim(implode(' · ', array_filter([$issuer['phone'], $issuer['email'], $issuer['website']]))), $width) }}
@endif
@if(isset($data['requested_by']))
{{ $center('Gedruckt von: ' . $data['requested_by'], $width) }}
@endif
{{ $center(now()->format('d.m.Y H:i:s'), $width) }}
{{ $sep }}
{{ "\n\n\n" }}
