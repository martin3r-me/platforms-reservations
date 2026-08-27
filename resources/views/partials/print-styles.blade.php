{{-- Druckbild für Ansichten, die im Programm stehen und trotzdem auf Papier
     sollen – Küche und Laufzettel.

     Zwei Probleme sind dabei zu lösen, und beide sieht man erst im Ausdruck:

     1. Das Programmgerüst kommt mit aufs Papier: Seitenleiste, Navigation,
        Reiter, Fußleiste. Auf Papier ist davon nichts anklickbar, es nimmt
        nur Platz weg und quetscht den Inhalt in eine schmale Spalte.

     2. Der Ausdruck endet nach der ersten Seite. Das Layout ist auf
        Bildschirmhöhe gebaut – feste Höhen, "overflow: hidden" – und was
        darunter liegt, existiert für den Drucker nicht.

     Gelöst über "visibility" statt "display: none": Der Druckbereich hängt
     tief im Gerüst, und würde man seine Vorfahren ausblenden, verschwände er
     mit ihnen. Unsichtbar geschaltete Vorfahren behalten dagegen ihren
     sichtbaren Inhalt.

     Verwendung: Den druckbaren Teil in <div id="pp-print"> fassen, alles
     darin, was nicht aufs Papier soll, mit class="pp-no-print".

     Inline statt @push: Das Layout kennt keinen Style-Stack. @once, damit die
     Regeln nicht doppelt im Dokument stehen. --}}
@once
    <style>
        @media print {
            /* Bildschirmgerüst aufheben. Ohne das endet der Ausdruck nach der
               ersten Seite (feste Höhen, "overflow: hidden") – und umgekehrt
               erzeugt eine auf Fensterhöhe gerechnete Hülle eine zweite,
               leere Seite, weil sie Platz beansprucht, den niemand füllt. */
            html, body {
                height: auto !important;
                min-height: 0 !important;
                max-height: none !important;
                overflow: visible !important;
                background: #fff !important;
            }
            /* Gezielt nur die Hüllen, die auf Fensterhöhe rechnen. NICHT alle
               Elemente: Ein leeres <span class="h-3 w-3"> ist ein farbiger
               Punkt, dessen ganze Höhe aus der Klasse kommt – mit
               "height: auto" fällt er auf null zusammen und verschwindet
               spurlos aus dem Ausdruck. */
            .h-full, .h-screen, .min-h-screen {
                height: auto !important;
                min-height: 0 !important;
                max-height: none !important;
            }
            body * { overflow: visible !important; }

            /* Alles aus, nur den Druckbereich an. */
            body * { visibility: hidden !important; }
            #pp-print, #pp-print * { visibility: visible !important; }

            /* Fixierte Elemente – Fußleiste, Ambient-Fläche – erscheinen sonst
               auf jeder Seite oder schieben eine leere hinterher. */
            .fixed, [style*="position:fixed"], [style*="position: fixed"] {
                display: none !important;
            }

            #pp-print {
                position: absolute !important;
                top: 0 !important;
                left: 0 !important;
                width: 100% !important;
                max-width: none !important;
                margin: 0 !important;
                /* Der Seitenrand kommt von hier, nicht von @page – siehe unten. */
                padding: 12mm !important;
            }

            /* Ein Abstand am letzten Element kippt den Inhalt sonst auf eine
               weitere, leere Seite. */
            #pp-print > *:last-child { margin-bottom: 0 !important; }

            .pp-no-print, .pp-no-print * { display: none !important; }

            /* Kopfzeile nur auf Papier: Ohne sie stünde nirgends, um welche
               Veranstaltung es geht – die Navigation, die das sonst sagt, ist
               ja gerade weg. */
            .pp-print-only { display: block !important; }

            /* Nichts mitten im Eintrag umbrechen. */
            section, tr, li { break-inside: avoid; page-break-inside: avoid; }
            thead { display: table-header-group; }

            /* Kein Papier für Flächen und Schatten verschwenden. */
            * {
                box-shadow: none !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }

        /* Am Bildschirm hat die Druck-Kopfzeile nichts zu suchen. */
        .pp-print-only { display: none; }

        /* Rand 0, damit der Browser keinen Platz hat, um URL, Datum und
           Seitenzahl in den Rand zu schreiben – die will beim Laufzettel
           niemand. Den sichtbaren Abstand macht stattdessen das Innenmaß von
           #pp-print. */
        @page { margin: 0; }
    </style>
@endonce
