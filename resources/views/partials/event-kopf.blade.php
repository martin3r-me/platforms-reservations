{{-- Kopf und Reiter eines Termins als EIN Block.

     Den Abstand dazwischen bestimmte vorher jede Seite selbst - ueber das
     space-y ihres Rahmens. "Buchungen" stand auf 6, "Kueche" und
     "Laufzettel" auf 5. Vier Pixel; man sieht sie nicht, aber beim
     Reiterwechsel springt die Leiste, und das sieht man.

     Der Abstand gehoert dem Paar, nicht der Seite. Er steht hier, und damit
     kann keine Seite ihn mehr verstellen.

     Aus demselben Grund steht $hinweis UNTER den Reitern und nicht als dritte
     Kopfzeile darueber: Der Laufzettel nennt dort seinen Stand, und eine Zeile
     mehr im Kopf schob seine Leiste um mehr als die vier Pixel nach unten, um
     die es hier ueberhaupt geht. Unter den Reitern gehoert sie ohnehin besser
     hin - sie beschreibt den Inhalt dieser Seite, nicht den Termin.

     Erwartet: $event, $active ('dashboard'|'kitchen'|'function').
     Optional: $hinweis.
--}}
<div class="pp-no-print space-y-6">
    @include('reservation::partials.event-header', ['event' => $event])

    <div>
        @include('reservation::partials.event-tabs', [
            'event'  => $event,
            'active' => $active,
        ])

        @if (! empty($hinweis))
            <p class="m-0 mt-2 text-xs text-[color:var(--nx-faint)]">{{ $hinweis }}</p>
        @endif
    </div>
</div>
