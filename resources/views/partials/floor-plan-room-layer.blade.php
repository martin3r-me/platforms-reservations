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

{{-- Beschriftungen: immer sichtbar, auch außerhalb des Beschriften-Modus.
     Sie beantworten dem Gast später die eigentliche Frage – "wo sitze ich
     eigentlich" –, für die ein Wandumriss allein nicht genügt. --}}
<template x-for="(m, mi) in markers" :key="'m' + mi">
    <div
        x-on:pointerdown.stop="$wire.markerMode && markerAnfassen($event, mi)"
        {{-- Kein Alt-Klick: markerAnfassen ruft preventDefault(), und das
             unterdrückt das nachfolgende click-Ereignis. Rechtsklick ist
             ohnehin dasselbe Muster wie beim Zug daneben. --}}
        x-on:contextmenu.prevent.stop="$wire.markerMode && markerMenuOeffnen($event, mi)"
        class="absolute -translate-x-1/2 -translate-y-1/2 whitespace-nowrap rounded-full border bg-white px-2 py-0.5 text-[11px] font-medium shadow-sm"
        :style="{
            left: (m.x * 100) + '%',
            top: (m.y * 100) + '%',
            borderColor: '{{ $linie }}',
            color: '{{ $linie }}',
            zIndex: 22,
            pointerEvents: $wire.markerMode ? 'auto' : 'none',
            cursor: 'grab'
        }"
        :title="$wire.markerMode ? 'Ziehen verschiebt · Rechtsklick für mehr' : m.label"
        x-text="m.label"
    ></div>
</template>

{{-- Fläche zum Setzen einer Beschriftung --}}
<div
    x-show="$wire.markerMode"
    class="absolute inset-0"
    style="display: none; cursor: copy; pointer-events: auto; z-index: 20;"
    x-on:click="markerMenu ? markerMenu = null : markerSetzen($event)"
></div>

{{-- Auswahl der Beschriftung an der geklickten Stelle --}}
<div
    x-show="neu"
    x-on:click.stop
    x-on:pointerdown.stop
    {{-- pointer-events MUSS hier stehen: Der Wrapper der Ebene hat "none", und
         ohne Freischaltung ist das Menü durchklickbar – der Klick landet auf der
         Fläche darunter und schließt es, statt die Beschriftung zu setzen.

         Die Verschiebung richtet sich nach der Lage: Am Rand würde ein mittig
         ausgerichtetes Menü aus dem Plan ragen, und der Rahmen schneidet ab. --}}
    class="absolute rounded-lg border border-[var(--ui-border)] bg-white p-2 shadow-lg"
    style="display: none; z-index: 26; pointer-events: auto;"
    :style="neu ? {
        left: (neu.x * 100) + '%',
        top: (neu.y * 100) + '%',
        transform: (neu.x < 0.3 ? 'translateX(0)' : neu.x > 0.7 ? 'translateX(-100%)' : 'translateX(-50%)')
            + ' ' + (neu.y > 0.7 ? 'translateY(calc(-100% - 8px))' : 'translateY(8px)')
    } : {}"
>
    <div class="flex flex-wrap gap-1">
        @foreach (['Eingang', 'Bühne', 'Bar', 'Buffet', 'Theke'] as $vorschlag)
            <button type="button"
                x-on:click="markerAnlegen(@js($vorschlag))"
                class="rounded-full border border-[var(--ui-border)] px-2 py-0.5 text-[11px] hover:bg-gray-50">{{ $vorschlag }}</button>
        @endforeach
    </div>
    {{-- Räume unterscheiden sich; die Vorschläge decken den Normalfall ab,
         der Rest wird eingetippt. --}}
    <form x-on:submit.prevent="markerAnlegen(frei)" class="mt-2 flex gap-1">
        <input x-model="frei" type="text" maxlength="40" placeholder="eigener Text"
            class="w-32 rounded border border-[var(--ui-border)] px-2 py-0.5 text-[11px]">
        <button type="submit" class="rounded border border-[var(--ui-border)] px-2 text-[11px] hover:bg-gray-50">OK</button>
    </form>
</div>

{{-- Menü an einer Beschriftung --}}
<div
    x-show="markerMenu"
    x-on:click.stop
    x-on:pointerdown.stop
    class="absolute rounded-lg border border-[var(--ui-border)] bg-white py-1 shadow-lg"
    style="display: none; z-index: 26; pointer-events: auto;"
    :style="markerMenu ? {
        left: (markerMenu.x * 100) + '%',
        top: (markerMenu.y * 100) + '%',
        transform: (markerMenu.x < 0.3 ? 'translateX(0)' : markerMenu.x > 0.7 ? 'translateX(-100%)' : 'translateX(-50%)')
            + ' ' + (markerMenu.y > 0.7 ? 'translateY(calc(-100% - 8px))' : 'translateY(8px)')
    } : {}"
>
    <button type="button"
        x-on:click="oeffnungUmschalten(markerMenu.mi)"
        class="flex w-full items-center gap-2 whitespace-nowrap px-3 py-1.5 text-left text-xs hover:bg-gray-50"
        x-text="markers[markerMenu?.mi]?.gap ? 'Wand wieder schließen' : 'Wand hier öffnen'"
    ></button>
    <button type="button"
        x-on:click="markerLoeschen(markerMenu.mi)"
        class="flex w-full items-center gap-2 whitespace-nowrap px-3 py-1.5 text-left text-xs text-[var(--ui-danger)] hover:bg-red-50"
    >
        @svg('heroicon-o-trash', 'w-3.5 h-3.5')
        <span>Löschen</span>
    </button>
</div>

{{-- Fangfläche + Griffe: nur im Zeichenmodus --}}
<div
    x-show="$wire.roomMode"
    class="absolute inset-0"
    style="cursor: crosshair; pointer-events: auto; z-index: 20;"
    x-on:pointerdown.prevent="zeichnenStart($event)"
    x-on:pointermove="zeichnenBewegt($event)"
    x-on:pointerup="zeichnenEnde($event)"
    x-on:pointercancel="zug = null"
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
            {{-- :style als OBJEKT, nicht als Zeichenkette: Alpine mischt Objekte
                 eigenschaftsweise ein. Eine Zeichenkette ersetzt das ganze
                 style-Attribut und nähme Rahmenfarbe und Zeiger gleich mit. --}}
            :style="{ left: (mitte(pfad)[0] * 100) + '%', top: (mitte(pfad)[1] * 100) + '%' }"
            title="Ziehen verschiebt den Zug · Rechtsklick für Löschen"
        >
            <span style="color: {{ $linie }}">@svg('heroicon-o-arrows-pointing-out', 'w-3 h-3')</span>
        </div>
    </template>

    {{-- Griffe der fertigen Züge: ziehen verschiebt, Alt-Klick löscht --}}
    <template x-for="(pfad, pi) in paths" :key="'g' + pi">
        <template x-for="g in griffe(pfad)" :key="'g' + pi + '-' + g.i">
            {{-- Offenes Ende: dort setzt man an, um weiterzuzeichnen – gefüllt
                 dargestellt. Punkte mitten im Zug bleiben hohl und verschieben sich. --}}
            <div
                x-on:pointerdown.stop="istEnde(pfad, g.i) ? weiterZeichnen($event, g.pt) : griffAnfassen($event, pi, g.i)"
                x-on:click.stop="$event.altKey && punktLoeschen(pi, g.i)"
                class="absolute h-3 w-3 -translate-x-1/2 -translate-y-1/2 rounded-full border-2"
                :style="{
                    left: (g.pt[0] * 100) + '%',
                    top: (g.pt[1] * 100) + '%',
                    borderColor: '{{ $linie }}',
                    background: istEnde(pfad, g.i) ? '{{ $linie }}' : '#fff',
                    cursor: istEnde(pfad, g.i) ? 'crosshair' : 'grab'
                }"
                :title="istEnde(pfad, g.i)
                    ? 'Ende – von hier weiterzeichnen (Alt-Klick löscht den Punkt)'
                    : 'Punkt ' + (g.i + 1) + ' – ziehen zum Verschieben, Alt-Klick löscht'"
            ></div>
        </template>
    </template>

    {{-- Menü zum Zug: erscheint am Verschiebe-Griff, ein Klick daneben schließt es --}}
    <div
        x-show="menu"
        {{-- pointerdown MUSS mit gestoppt werden: Die Fläche zeichnet seit der
             Umstellung auf Ziehen beim Drücken, und zeichnenStart schließt das
             Menü – der Klick auf "Zug löschen" käme sonst nie an. --}}
        x-on:pointerdown.stop
        x-on:click.stop
        class="absolute rounded-lg border border-[var(--ui-border)] bg-white py-1 shadow-lg"
        style="display: none; z-index: 25; pointer-events: auto;"
        :style="menu ? {
            left: (menu.x * 100) + '%',
            top: (menu.y * 100) + '%',
            transform: (menu.x < 0.3 ? 'translateX(0)' : menu.x > 0.7 ? 'translateX(-100%)' : 'translateX(-50%)')
                + ' ' + (menu.y > 0.7 ? 'translateY(calc(-100% - 8px))' : 'translateY(8px)')
        } : {}"
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

    
</div>
