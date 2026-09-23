{{-- Kopf und Reiter eines Termins als EIN Block.

     Den Abstand dazwischen bestimmte vorher jede Seite selbst - ueber das
     space-y ihres Rahmens. "Buchungen" stand auf 6, "Kueche" und
     "Laufzettel" auf 5. Vier Pixel; man sieht sie nicht, aber beim
     Reiterwechsel springt die Leiste, und das sieht man.

     Der Abstand gehoert dem Paar, nicht der Seite. Er steht hier, und damit
     kann keine Seite ihn mehr verstellen.

     Aus demselben Grund traegt $hinweis die Reiterleiste selbst, rechts auf
     Hoehe der Reiter: Als eigene Zeile - ob ueber oder unter der Leiste -
     kostete er Hoehe, und die Leiste sass auf dem Laufzettel tiefer als
     anderswo. Genau das Problem, um das es hier geht.

     Erwartet: $event, $active ('dashboard'|'kitchen'|'function').
     Optional: $hinweis.
--}}
<div class="pp-no-print space-y-6">
    @include('reservation::partials.event-header', ['event' => $event])

    @include('reservation::partials.event-tabs', [
        'event'   => $event,
        'active'  => $active,
        'hinweis' => $hinweis ?? null,
    ])
</div>
