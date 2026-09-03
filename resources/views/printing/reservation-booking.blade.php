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
{{ $center(Str::limit($printable->event->name, $width), $width) }}

@endif
@php
    // Die Uhrzeit kommt aus der Pause, nicht aus der Buchung.
    //
    // In der Buchung steht der Stand vom Bestellzeitpunkt. Wird die Pause
    // danach verschoben - und das passiert -, druckte der Bon bisher eine
    // Zeit, zu der niemand mehr am Tisch steht. Ohne Pause bleibt der
    // eingefrorene Wert die einzige Quelle, dann gilt weiter er.
    $zeit  = $printable->slot?->time_start ?? $printable->time_start;
    $datum = (string) $printable->date?->format('d.m.Y');
    if ($zeit) { $datum = trim($datum . ' - ' . substr((string) $zeit, 0, 5) . ' Uhr'); }

    // Name plus Zeit, wie im VA-Dashboard. Nur die Trennzeichen sind andere:
    // displayLabel() setzt einen Mitteldot, time_range einen Gedankenstrich,
    // und auf beide ist beim Zeichensatz des Bondruckers kein Verlass. Im
    // Rest dieser Vorlage trennt durchgehend " - ".
    // Gekuerzt wird der Name, nicht die Zeit: Die Zeit ist der Grund, warum
    // die Zeile ueberhaupt da steht. Abgeschnitten wird ohne Puenktchen, wie
    // in $row weiter oben - auf 48 Zeichen ist jedes Zeichen eines zu viel,
    // und ein Umbruch mitten in der Uhrzeit macht den Bon unlesbar.
    $pause = null;
    if ($printable->slot) {
        $spanne = str_replace("\u{2013}", '-', (string) $printable->slot->time_range);
        $anhang = $spanne !== '' ? ' - ' . $spanne . ' Uhr' : '';
        $platz  = $width - 12 - mb_strlen($anhang);
        $pause  = Str::limit((string) $printable->slot->name, max(1, $platz), '') . $anhang;
    }
@endphp
{{ str_pad('Datum:', 12) }}{{ $datum }}
@if($pause)
{{ str_pad('Pause:', 12) }}{{ $pause }}
@endif
@php
    // Ort aus der EINEN Auskunft: lebender Tisch oder Abholstation, sonst der
    // eingefrorene Name. Ist der Ort geloescht, steht statt des Raums der
    // Hinweis - der Service soll am Bon sehen, dass er neu zugeteilt werden muss.
    $ort = $printable->zielort();
    $tisch = null;

    // Die Beschriftung folgt der Art. "Tisch: Foyer links" waere am Tresen
    // schlicht eine Falschaussage - dort steht kein Tisch, dort wird abgeholt.
    $ortLabel = $ort['art'] === 'station' ? 'Abholung:' : 'Tisch:';

    if ($ort['label']) {
        $tisch = (string) $ort['label'];
        if ($ort['weg']) {
            $tisch .= ' - Ort entfernt';
        } elseif ($ort['raum']) {
            $tisch .= ' - ' . Str::limit($ort['raum'], 24);
        }
    }
@endphp
@if($tisch)
{{ str_pad($ortLabel, 12) }}{{ $tisch }}
@endif
{{ str_pad('Gast:', 12) }}{{ Str::limit($printable->guest_name ?? '-', $width - 12) }}
{{ str_pad('Personen:', 12) }}{{ $printable->guest_count }}
@if($order)
{{ str_pad('Bestellung:', 12) }}{{ $order->uuid }}
@endif
@if($payment)
@php
    $zahlung = (string) $payment->status;
    if ($printable->payment_method) { $zahlung .= ' - ' . ($payLabels[$printable->payment_method] ?? $printable->payment_method); }
@endphp
{{ str_pad('Zahlung:', 12) }}{{ $zahlung }}
@endif
{{ str_pad('Gebucht:', 12) }}{{ $printable->created_at?->format('d.m.Y H:i') }}

{{ $line }}
{{ str_pad('ARTIKEL', 12) }}
{{ $line }}
@php
    // Ein Bundle als EINE Zeile mit Bundle-Preis; der Inhalt darunter ohne
    // Betraege. Die Rohpositionen wuerden die interne Aufteilung zeigen –
    // bei drei Bier etwa "2x BIER 11,08" und "1x BIER 5,53".
    $blocks = \Platform\Reservation\Support\BookingItemsPresenter::blocks($printable->items);
@endphp
@forelse($blocks as $block)
@php $right = $money($block['total']) . ' ' . $sym . ($block['is_bundle'] ? '' : ' ' . $letterFor($block['tax_rate'])); @endphp
{{ $row($block['quantity'] . 'x ' . $block['name'], $right) }}
@if($block['is_bundle'] && $block['contents'])
   {{ Str::limit(\Platform\Reservation\Support\BookingItemsPresenter::contentsLabel($block['contents']), $width - 4) }}
@endif
@foreach($block['notes'] as $note)
   > {{ Str::limit($note, $width - 5) }}
@endforeach
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
{{ $row($vr['letter'] . '  ' . $ratePct($vr['rate']) . '%   Netto ' . $money($vr['net']), 'MwSt ' . $money($vr['vat']) . ' ' . $sym) }}
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
