{{--
    RAUMUMRISS – VERSUCHSSTAND, rückstandsfrei entfernbar.

    Eine eigene Ebene über dem Grundriss-Bild und unter den Tischen. Sie fasst
    die Tische nicht an: Solange nicht gezeichnet wird, nimmt sie keine
    Mausereignisse an, und der Tisch-Teil des Editors bleibt unverändert.

    Koordinaten sind normalisiert (0…1) wie bei den Tischen. Das SVG nutzt
    dafür viewBox="0 0 1 1" mit preserveAspectRatio="none" – dadurch entfällt
    jede Umrechnung. Damit die Linie dabei nicht mitverzerrt wird, trägt sie
    vector-effect="non-scaling-stroke".

    Die Griffe sind bewusst HTML und kein SVG: Kreise im gestreckten viewBox
    würden zu Ellipsen.

    Erwartet: $paths (Züge aus RoomLayout), $roomMode (bool)
--}}

{{-- Linien: immer sichtbar, auch außerhalb des Zeichenmodus --}}
<svg
    class="pointer-events-none absolute inset-0 h-full w-full"
    viewBox="0 0 1 1"
    preserveAspectRatio="none"
    aria-hidden="true"
>
    <template x-for="(pfad, pi) in paths" :key="'p' + pi">
        <polyline
            :points="pfad.map(pt => pt[0] + ',' + pt[1]).join(' ')"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linejoin="round"
            stroke-linecap="round"
            vector-effect="non-scaling-stroke"
            class="text-[color:var(--nx-text)] opacity-70"
        />
    </template>

    {{-- Zug, der gerade entsteht: gestrichelt bis zum Abschluss --}}
    <template x-if="entwurf.length > 1">
        <polyline
            :points="entwurf.map(pt => pt[0] + ',' + pt[1]).join(' ')"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-dasharray="6 4"
            stroke-linejoin="round"
            vector-effect="non-scaling-stroke"
            class="text-[color:var(--nx-accent)]"
        />
    </template>
</svg>

{{-- Fangfläche + Griffe: nur im Zeichenmodus --}}
<div
    x-show="$wire.roomMode"
    class="absolute inset-0"
    style="cursor: crosshair; pointer-events: auto; z-index: 20;"
    x-on:click="punktSetzen($event)"
    x-on:dblclick.prevent="zugAbschliessen()"
    x-on:contextmenu.prevent="zugAbschliessen()"
>
    {{-- Griffe der fertigen Züge: ziehen verschiebt, Alt-Klick löscht --}}
    <template x-for="(pfad, pi) in paths" :key="'g' + pi">
        <template x-for="(pt, ii) in pfad" :key="'g' + pi + '-' + ii">
            <div
                x-on:pointerdown.stop="griffAnfassen($event, pi, ii)"
                x-on:click.stop="$event.altKey && punktLoeschen(pi, ii)"
                class="absolute h-3 w-3 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 bg-white"
                style="border-color: var(--nx-accent); cursor: grab;"
                :style="'left:' + (pt[0] * 100) + '%; top:' + (pt[1] * 100) + '%'"
                :title="'Punkt ' + (ii + 1) + ' – ziehen zum Verschieben, Alt-Klick löscht'"
            ></div>
        </template>
    </template>

    {{-- Punkte des laufenden Zugs, kleiner und ohne Griff-Funktion --}}
    <template x-for="(pt, ii) in entwurf" :key="'e' + ii">
        <div
            class="pointer-events-none absolute h-2 w-2 -translate-x-1/2 -translate-y-1/2 rounded-full"
            style="background: var(--nx-accent)"
            :style="'left:' + (pt[0] * 100) + '%; top:' + (pt[1] * 100) + '%'"
        ></div>
    </template>
</div>
