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
    $statusFarben = ['published' => '#2f9e44', 'draft' => '#868e96', 'closed' => '#e8590c', 'cancelled' => '#e03131'];
    $statusPunkt  = $statusFarben[$event->status->value] ?? '#868e96';
@endphp

<div class="pp-no-print">
    <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
        <h1 class="m-0 text-2xl font-bold tracking-tight text-[color:var(--nx-text)]">{{ $event->name }}</h1>
        <span class="inline-flex items-center gap-1.5 text-sm text-[color:var(--nx-muted)]">
            <span class="h-2 w-2 rounded-full" style="background:{{ $statusPunkt }}"></span>{{ $event->status->label() }}
        </span>
        @if ($event->date?->isToday())
            <x-nx-badge variant="success">Heute</x-nx-badge>
        @endif
    </div>

    <p class="m-0 mt-1 text-sm text-[color:var(--nx-muted)]">
        {{ $event->date?->format('d.m.Y') }}
        @if ($event->venue) · {{ $event->venue->name }} @endif
        @if ($event->slots->isNotEmpty()) · {{ $event->slots->map(fn ($sl) => $sl->displayLabel())->implode(', ') }} @endif
    </p>

    @if (! empty($hinweis))
        <p class="m-0 mt-1 text-xs text-[color:var(--nx-faint)]">{{ $hinweis }}</p>
    @endif
</div>
