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
     Inhalt sichtbar darunter) und derselbe Hover-Ton wie in der Zeile, ohne ihn
     als festen Wert zu wiederholen.

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
            /* Weicher Schatten nach links – die Kante soll als schwebend
               lesbar sein, nicht als Spaltentrenner. */
            box-shadow: -10px 0 10px -10px rgba(0, 0, 0, .18);
        }

        /* Denselben Ton wie die Zeile, aber ÜBER der deckenden Fläche: als
           Bild-Ebene, damit die Hintergrundfarbe darunter erhalten bleibt. */
        tr:hover > .pp-pin {
            background-image: linear-gradient(var(--nx-hover), var(--nx-hover));
        }
    </style>
@endonce
