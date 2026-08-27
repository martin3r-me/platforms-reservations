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
            /* Bildschirmgerüst aufheben, sonst endet der Ausdruck nach
               der ersten Seite. */
            html, body {
                height: auto !important;
                overflow: visible !important;
                background: #fff !important;
            }
            body * { overflow: visible !important; }

            /* Alles aus, nur den Druckbereich an. */
            body * { visibility: hidden !important; }
            #pp-print, #pp-print * { visibility: visible !important; }

            #pp-print {
                position: absolute !important;
                top: 0 !important;
                left: 0 !important;
                width: 100% !important;
                max-width: none !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .pp-no-print, .pp-no-print * { display: none !important; }

            /* Kopfzeile nur auf Papier: Ohne sie stünde nirgends, um
               welche Veranstaltung es geht – die Navigation, die das
               sonst sagt, ist ja gerade weg. */
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

        @page { margin: 12mm; }
    </style>
@endonce
