{{-- Produktbild gross ansehen - dieselbe Geste wie im Gast-Shop.

     Das Vorschaubild in der Liste ist 48 Pixel gross. Ob darauf wirklich das
     richtige Gericht liegt und ob der Anschnitt sitzt, sieht man darin nicht.
     Und genau das ist die Frage, die vor einer Freigabe gestellt wird.

     Alles per <style> und nicht per Utility-Klassen: Das CSS entsteht im Build
     der Host-App, ein Modul-Bump tauscht nur PHP. Eine Klasse, die hier neu
     waere, gaebe es nach dem Ausrollen nicht - und die Lightbox laege
     unsichtbar irgendwo auf der Seite. Dasselbe Muster wie beim Puls im
     Terminkopf.

     Erwartet einen Alpine-Bereich mit:  x-data="{ bild: { offen: false, src: '', name: '' } }"
     Oeffnen:  @click="bild = { offen: true, src: '…', name: '…' }"
--}}
@once
    <style>
        .pp-lightbox {
            position: fixed;
            inset: 0;
            z-index: 70;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: rgba(0, 0, 0, .72);
        }

        .pp-lightbox-bild {
            max-height: 85vh;
            max-height: 85dvh;
            max-width: 100%;
            width: auto;
            border-radius: 12px;
            object-fit: contain;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, .6);
            display: block;
        }

        .pp-lightbox-name {
            margin: 12px 0 0;
            text-align: center;
            font-size: 13px;
            color: #fff;
        }

        .pp-lightbox-zu {
            position: absolute;
            top: -14px;
            right: -14px;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 9999px;
            background: #fff;
            color: #111;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .35);
        }

        /* Die Lupe sagt, dass das Bild anklickbar ist. Ohne sie probiert es
           niemand aus - ein Vorschaubild sieht sonst nach Dekoration aus. */
        .pp-bild-knopf {
            position: relative;
            display: block;
            padding: 0;
            border: 0;
            background: none;
            cursor: zoom-in;
            border-radius: 8px;
            overflow: hidden;
        }

        .pp-bild-lupe {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            background: rgba(0, 0, 0, .35);
            opacity: 0;
            transition: opacity .15s ease;
        }

        .pp-bild-knopf:hover .pp-bild-lupe,
        .pp-bild-knopf:focus-visible .pp-bild-lupe {
            opacity: 1;
        }

        @media (prefers-reduced-motion: reduce) {
            .pp-bild-lupe { transition: none; }
        }
    </style>
@endonce

<template x-teleport="body">
    <div x-show="bild.offen"
         x-cloak
         x-transition.opacity
         role="dialog"
         aria-modal="true"
         class="pp-lightbox"
         style="display: none;"
         @keydown.escape.window="bild.offen = false"
         @click="bild.offen = false">
        <div style="position: relative;" @click.stop>
            <img :src="bild.src" :alt="bild.name" class="pp-lightbox-bild">

            <button type="button"
                    class="pp-lightbox-zu"
                    @click="bild.offen = false"
                    aria-label="Schliessen">
                @svg('heroicon-o-x-mark', 'w-5 h-5')
            </button>

            <p x-show="bild.name" x-text="bild.name" class="pp-lightbox-name"></p>
        </div>
    </div>
</template>
