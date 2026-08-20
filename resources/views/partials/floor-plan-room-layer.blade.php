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

@php
    // Indigo wie im uebrigen Editor. BEWUSST nicht --nx-text oder --nx-accent:
    // beide sind Near-Black (#37352f) und gehen auf einem schwarz-weissen
    // Grundriss unter. Als Attribut statt als Utility-Klasse, damit es nicht
    // vom CSS-Build abhaengt.
    $linie = '#4f46e5';   // indigo-600
@endphp

{{-- Linien: immer sichtbar, auch außerhalb des Zeichenmodus.

     KEINE x-for/x-if-Templates hier drin: Innerhalb von <svg> landet ein
     <template> im SVG-Namensraum, wo .content nicht existiert – Alpine kann es
     dann nicht klonen. Deshalb werden ALLE Züge zu einem einzigen d-Attribut
     zusammengesetzt; ein Pfad kann über "M" beliebig viele Teilzüge enthalten. --}}
<svg
    class="pointer-events-none absolute inset-0 h-full w-full"
    viewBox="0 0 1 1"
    preserveAspectRatio="none"
    aria-hidden="true"
>
    <path
        :d="d"
        fill="none"
        stroke="{{ $linie }}"
        stroke-width="5"
        stroke-linejoin="round"
        stroke-linecap="round"
        vector-effect="non-scaling-stroke"
    />

    {{-- Zug, der gerade entsteht: gestrichelt bis zum Abschluss --}}
    <path
        :d="dEntwurf"
        fill="none"
        stroke="{{ $linie }}"
        stroke-width="5"
        stroke-dasharray="6 4"
        stroke-linejoin="round"
        vector-effect="non-scaling-stroke"
        opacity="0.7"
    />
</svg>

{{-- Fangfläche + Griffe: nur im Zeichenmodus --}}
<div
    x-show="$wire.roomMode"
    class="absolute inset-0"
    style="cursor: crosshair; pointer-events: auto; z-index: 20;"
    x-on:pointermove="zeigerBewegt($event)"
    x-on:pointerleave="cursor = null"
    x-on:click="punktSetzen($event)"
    x-on:dblclick.prevent="zugAbschliessen()"
    x-on:contextmenu.prevent="zugAbschliessen()"
>
    {{-- Griff in der Mitte jedes Zugs: verschiebt ihn als Ganzes.
         Die Linie selbst lässt sich dafür nicht anfassen – alle Züge stecken
         in EINEM Pfad-Element und wären dort nicht auseinanderzuhalten. --}}
    <template x-for="(pfad, pi) in paths" :key="'m' + pi">
        <div
            x-on:pointerdown.stop="pfadAnfassen($event, pi)"
            x-on:click.stop
            x-on:contextmenu.prevent.stop="menuOeffnen($event, pi)"
            class="absolute flex h-5 w-5 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded border-2 bg-white shadow-sm"
            style="border-color: {{ $linie }}; cursor: grab;"
            :style="'left:' + (mitte(pfad)[0] * 100) + '%; top:' + (mitte(pfad)[1] * 100) + '%'"
            title="Ziehen verschiebt den Zug · Rechtsklick für Löschen"
        >
            <span style="color: {{ $linie }}">@svg('heroicon-o-arrows-pointing-out', 'w-3 h-3')</span>
        </div>
    </template>

    {{-- Griffe der fertigen Züge: ziehen verschiebt, Alt-Klick löscht --}}
    <template x-for="(pfad, pi) in paths" :key="'g' + pi">
        <template x-for="(pt, ii) in pfad" :key="'g' + pi + '-' + ii">
            <div
                x-on:pointerdown.stop="griffAnfassen($event, pi, ii)"
                x-on:click.stop="$event.altKey && punktLoeschen(pi, ii)"
                class="absolute h-3 w-3 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 bg-white"
                style="border-color: {{ $linie }}; cursor: grab;"
                :style="'left:' + (pt[0] * 100) + '%; top:' + (pt[1] * 100) + '%'"
                :title="'Punkt ' + (ii + 1) + ' – ziehen zum Verschieben, Alt-Klick löscht'"
            ></div>
        </template>
    </template>

    {{-- Menü zum Zug: erscheint am Verschiebe-Griff, ein Klick daneben schließt es --}}
    <div
        x-show="menu"
        x-on:click.stop
        class="absolute -translate-x-1/2 translate-y-2 rounded-lg border border-[var(--ui-border)] bg-white py-1 shadow-lg"
        style="display: none; z-index: 25;"
        :style="menu ? 'left:' + (menu.x * 100) + '%; top:' + (menu.y * 100) + '%' : ''"
    >
        <button
            type="button"
            x-on:click="pfadLoeschen(menu.pi)"
            class="flex w-full items-center gap-2 whitespace-nowrap px-3 py-1.5 text-left text-xs text-[var(--ui-danger)] hover:bg-red-50"
        >
            @svg('heroicon-o-trash', 'w-3.5 h-3.5')
            <span>Zug löschen</span>
        </button>
    </div>

    {{-- Punkte des laufenden Zugs, kleiner und ohne Griff-Funktion --}}
    <template x-for="(pt, ii) in entwurf" :key="'e' + ii">
        <div
            class="pointer-events-none absolute h-2 w-2 -translate-x-1/2 -translate-y-1/2 rounded-full"
            style="background: {{ $linie }}"
            :style="'left:' + (pt[0] * 100) + '%; top:' + (pt[1] * 100) + '%'"
        ></div>
    </template>
</div>
