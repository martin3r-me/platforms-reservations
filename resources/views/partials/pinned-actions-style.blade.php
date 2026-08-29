{{-- Die Aktionsspalte bleibt am rechten Rand stehen, während der Rest der
     Tabelle darunter wegscrollt.

     Warum überhaupt: Die Buchungsliste hat zehn Spalten und wird breiter als
     ihre Karte – ein einziges „Abgeschlossen" verbreitert die Status-Spalte für
     alle Zeilen. Der Kasten um die Tabelle scrollt dann waagerecht
     (overflow-x-auto, so gedacht), und ausgerechnet die Aktionen lagen jenseits
     des Randes.

     Als Stilblock statt als Tailwind-Klassen, weil zwei Dinge nötig sind, die
     sich dort schlecht ausdrücken lassen: eine DECKENDE Fläche (der
     Zeilen-Hover --nx-hover ist durchscheinend, sonst läge der wegscrollende
     Inhalt sichtbar darunter) und derselbe Hover-Ton IM SELBEN TAKT wie die
     Zeile, ohne ihn als festen Wert zu wiederholen.

     Inline und @once wie bei den Druckstilen: Das Layout kennt keinen
     Style-Stack.

     WICHTIG: position: sticky erzeugt einen eigenen Stapelkontext. Alles, was
     aus dieser Zelle herausragen soll, muss deshalb aus ihr heraus – das
     Zeilen-Menü hängt sich per x-teleport an den Seitenkörper. Ohne das
     verschwände es hinter den Zellen der Zeilen darunter. --}}
@once
    <style>
        .pp-pin {
            position: sticky;
            right: 0;
            /* Deckend: Was darunter wegscrollt, darf nicht durchscheinen. */
            background-color: var(--nx-surface);
        }

        /* Weicher Schatten nach links – die Kante soll als schwebend lesbar
           sein, nicht als Spaltentrenner.

           Als eigene Fläche statt als box-shadow: ein Schatten gehört der
           einzelnen Zelle und endet an ihrer Unterkante, dazwischen liegt die
           Trennlinie der Zeile. Über viele Zeilen wurde daraus eine Kette von
           Segmenten – sichtbar als leichte Unterbrechungen hinter "Gebucht am".
           Diese Fläche ragt oben und unten je einen Pixel hinaus, sodass die
           Kante von Zeile zu Zeile durchläuft. */
        .pp-pin::after {
            content: '';
            position: absolute;
            top: -1px;
            bottom: -1px;
            right: 100%;
            width: 10px;
            background-image: linear-gradient(to left, rgba(0, 0, 0, .10), rgba(0, 0, 0, 0));
            pointer-events: none;
        }

        /* Der Hover-Ton als eigene Ebene über der deckenden Fläche.
           Vorher lag er als background-image darauf und sprang ohne Übergang
           um, während die Zeile ihren Hover in 150 ms einblendet
           (transition-colors in x-nx-table-row). Bei schneller Mausbewegung
           lief die gepinnte Spalte der Zeile sichtbar voraus – zwei Grautöne
           nebeneinander. Ein Farbverlauf ist von "kein Bild" aus nicht
           animierbar, deshalb eine Fläche, deren Deckkraft im selben Takt
           läuft. Deckkraft zeichnet außerdem nur neu zusammen, statt die
           sticky-Ebene komplett neu aufzubauen. */
        .pp-pin::before {
            content: '';
            position: absolute;
            inset: 0;
            background-color: var(--nx-hover);
            opacity: 0;
            transition: opacity 150ms ease;
            pointer-events: none;
        }

        tr:hover > .pp-pin::before {
            opacity: 1;
        }

        /* Der Zellinhalt gehört über die Hover-Fläche, nicht darunter. */
        .pp-pin > * {
            position: relative;
            z-index: 1;
        }
    </style>
@endonce
