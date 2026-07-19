@php
    /** @var \Platform\Reservation\Models\Booking $printable */
    /** @var \Platform\Printing\Models\PrintJob $job */

    // Bon-Drucker optimierte Formatierung (80mm = ~48 Zeichen)
    $width = 48;
    $sep   = str_repeat('=', $width);
    $line  = str_repeat('-', $width);

    // Zeile mit Bezeichnung links, Betrag rechtsbündig
    $row = function (string $left, string $right) use ($width) {
        $right = (string) $right;
        $left  = \Illuminate\Support\Str::limit($left, $width - strlen($right) - 1, '');
        $pad   = max(1, $width - strlen($left) - strlen($right));
        return $left . str_repeat(' ', $pad) . $right;
    };

    $currency = strtoupper((string) config('reservation.currency', 'EUR'));
    $sym      = $currency === 'EUR' ? 'EUR' : $currency;
@endphp
{{ $sep }}
{{ str_pad('BON  Buchung #' . $printable->id, $width, ' ', STR_PAD_BOTH) }}
{{ $sep }}

@if($printable->event)
{{ str_pad('VA:', 12) }}{{ Str::limit($printable->event->name, $width - 12) }}
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

{{ $line }}
{{ str_pad('ARTIKEL', 12) }}
{{ $line }}
@forelse($printable->items as $item)
@php $itemSum = number_format($item->unit_price * $item->quantity, 2, ',', '.') . ' ' . $sym; @endphp
{{ $row($item->quantity . 'x ' . ($item->menuItem?->name ?? 'Artikel'), $itemSum) }}
@if($item->notes)
   > {{ Str::limit($item->notes, $width - 5) }}
@endif
@empty
{{ 'Keine Vorbestellung - nur Tischreservierung' }}
@endforelse

@if($printable->items->isNotEmpty())
{{ $line }}
{{ $row('SUMME', number_format($printable->total_amount, 2, ',', '.') . ' ' . $sym) }}
@endif

@if($printable->notes)
{{ $line }}
{{ str_pad('Anmerkung:', 12) }}
{{ wordwrap($printable->notes, $width, "\n", true) }}
@endif

@if(isset($data['requested_by']))
{{ $line }}
{{ str_pad('Gedruckt von:', 15) }}{{ Str::limit($data['requested_by'], $width - 15) }}
@endif
{{ $sep }}
{{ str_pad(now()->format('d.m.Y H:i:s'), $width, ' ', STR_PAD_BOTH) }}
{{ $sep }}
{{ "\n\n\n" }}
