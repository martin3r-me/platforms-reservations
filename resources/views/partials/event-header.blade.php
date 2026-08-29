{{--
    Kopf der drei Veranstaltungs-Reiter: Buchungen, Küche, Laufzettel.

    Einmal an einer Stelle, weil die drei Seiten sonst auseinanderlaufen –
    genau das war passiert: Buchungen hatte eine Überschrift, Küche gar keine,
    der Laufzettel eine kleine graue Zeile unter den Reitern.

    Erwartet: $event (Event). Optional: $hinweis (string) für eine dritte,
    seitenspezifische Zeile – der Laufzettel nennt dort seinen Stand.

    Steht bewusst NICHT im Ausdruck: Dort trägt jede Seite ihre eigene
    Druck-Kopfzeile, die Angaben würden sich sonst doppeln.
--}}
@php
    // Der Punkt beantwortet die Frage des Abends: kann gerade bestellt werden?
    // Deshalb nicht der Status als Farbe, sondern die Bestellbarkeit – nur eine
    // Absage sticht durch, die ist wichtiger als „geschlossen".
    $kannBestellen = $event->isOrderable();
    $abgesagt      = $event->status->value === 'cancelled';

    $statusPunkt = $abgesagt ? '#e03131' : ($kannBestellen ? '#2f9e44' : '#868e96');

    $frist = $event->order_deadline_at;
    // Am Termintag reicht die Uhrzeit; liegt die Frist auf einem anderen Tag,
    // muss das Datum mit – sonst liest man 19:59 und meint heute Abend.
    $fristText = $frist
        ? ($frist->isSameDay($event->date) ? $frist->format('H:i') . ' Uhr' : $frist->format('d.m.Y, H:i') . ' Uhr')
        : null;
@endphp

@once
    <style>
        /* Eigene Keyframes statt animate-ping: die Klasse steht nicht im
           CSS-Build der Host-Apps, die Animation liefe sonst gar nicht. */
        .pp-puls {
            position: absolute;
            inset: 0;
            border-radius: 9999px;
            animation: pp-puls 1.8s cubic-bezier(0, 0, .2, 1) infinite;
        }

        @keyframes pp-puls {
            0%   { transform: scale(1);   opacity: .7; }
            75%, 100% { transform: scale(2.4); opacity: 0; }
        }

        /* Wer Bewegung abgestellt hat, bekommt einen ruhigen Punkt. */
        @media (prefers-reduced-motion: reduce) {
            .pp-puls { animation: none; opacity: 0; }
        }
    </style>
@endonce

<div class="pp-no-print">
    <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
        <h1 class="m-0 text-2xl font-bold tracking-tight text-[color:var(--nx-text)]">{{ $event->name }}</h1>
        <span class="inline-flex items-center gap-1.5 text-sm text-[color:var(--nx-muted)]">
            <span class="relative inline-flex h-2 w-2">
                @if ($kannBestellen)
                    {{-- Der aufgehende Ring sagt „läuft gerade", ohne zu blinken.
                         Er verschwindet, sobald der Bestellschluss erreicht ist. --}}
                    <span class="pp-puls" style="background:{{ $statusPunkt }}"></span>
                @endif
                <span class="relative h-2 w-2 rounded-full" style="background:{{ $statusPunkt }}"></span>
            </span>{{ $event->istVergangen() && ! in_array($event->status->value, ['draft', 'cancelled'], true) ? 'Vergangen' : $event->status->label() }}
        </span>
        @if ($event->date?->isToday())
            <x-nx-badge variant="success">Heute</x-nx-badge>
        @endif
    </div>

    <p class="m-0 mt-1 text-sm text-[color:var(--nx-muted)]">
        {{ $event->date?->format('d.m.Y') }}
        @if ($event->venue) · {{ $event->venue->name }} @endif
        @if ($event->slots->isNotEmpty()) · {{ $event->slots->map(fn ($sl) => $sl->displayLabel())->implode(', ') }} @endif
        @if ($fristText)
            · {{ $kannBestellen ? 'Bestellschluss ' . $fristText : 'Bestellschluss war ' . $fristText }}
        @else
            {{-- Schweigen wäre hier falsch: ohne Frist bleibt der Termin bis
                 Mitternacht offen, und genau das soll auffallen. --}}
            · <span class="text-[color:var(--nx-faint)]">Kein Bestellschluss gesetzt</span>
        @endif
    </p>

    @if (! empty($hinweis))
        <p class="m-0 mt-1 text-xs text-[color:var(--nx-faint)]">{{ $hinweis }}</p>
    @endif
</div>
