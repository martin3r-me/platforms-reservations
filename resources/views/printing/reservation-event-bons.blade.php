@php
    /** @var \Platform\Reservation\Models\Event $printable */
    /** @var \Platform\Printing\Models\PrintJob $job */

    // Sammel-Bon: alle Bons einer Veranstaltung in EINEM Druckauftrag.
    //
    // Warum überhaupt: Das Gerät wartet nach jeder Auftragsmeldung rund 30
    // Sekunden, bevor es abholt - unabhängig vom Poll-Takt und serverseitig
    // nicht abstellbar. Bei zwanzig Einzelaufträgen sind das zehn Minuten,
    // bei einem einzigen eine halbe Minute.
    //
    // Es bleiben trotzdem einzelne Bons: Zwischen zwei Buchungen steht ein
    // Schnittbefehl, sodass jede Buchung ihren eigenen Beleg bekommt.
    //
    // Der Inhalt je Buchung kommt aus derselben Vorlage wie der Einzeldruck.
    // Zwei Fassungen desselben Bons, die auseinanderlaufen können, wären der
    // sichere Weg in einen Beleg, der je nach Druckweg anders aussieht.
    $bons = $printable->bonBookings();

    // Star: ESC d 3 - Teilschnitt mit Vorschub. Über die Konfiguration
    // änderbar, weil nicht jedes Gerät dieselbe Folge versteht. Ignoriert der
    // Drucker sie, kommt ein durchgehender Bon statt einzelner - unschön,
    // aber nichts geht verloren.
    $schnitt = config('reservation.bon_cut', "\x1b\x64\x33");
@endphp
@foreach ($bons as $bon)
@include('reservation::printing.reservation-booking', ['printable' => $bon])
@if (! $loop->last){!! $schnitt !!}@endif
@endforeach
