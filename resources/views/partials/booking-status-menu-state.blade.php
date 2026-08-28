{{-- Alpine-Zustand für das Zeilen-Menü. KEIN eigenes Element: Dieses Partial
     wird INNERHALB des öffnenden <div>-Tags eingebunden und liefert nur dessen
     Attribute. So steht die Logik einmal da und nicht in jeder Liste neu.

     Gehört zu partials/booking-status-menu.blade.php – wer das eine einbindet,
     bindet auch das andere ein.

     Der Zustand sitzt am ganzen Aktionsblock, nicht nur am Menü: Solange es
     offen ist, muss der Block sichtbar bleiben. Sonst blendet ihn das
     group-hover aus, sobald der Zeiger auf dem Weg zum Eintrag die Zeile
     verlässt – das Menü wäre offen und unsichtbar. --}}
x-data="{
    open: false,
    oben: 0,
    rechts: 0,
    auf() {
        const k = this.$refs.kebab.getBoundingClientRect();

        this.rechts = window.innerWidth - k.right;
        this.oben = k.bottom + 6;
        this.open = true;

        /* Nach oben klappen, wenn unten kein Platz ist.

           Das Menue haengt frei am Fenster (position: fixed) - das rettet es
           aus dem Tabellenkasten mit overflow-x-auto, der nach CSS auch
           senkrecht beschneidet. Vor dem unteren FENSTERrand rettet es das
           aber nicht: In den letzten Zeilen liefe es darueber hinaus.

           Die Hoehe steht erst fest, wenn es sichtbar ist - daher $nextTick
           und nicht gleich hier. Das kurze Umsetzen faellt nicht auf, weil
           x-transition ohnehin einblendet. */
        this.$nextTick(() => {
            const m = this.$refs.menu.getBoundingClientRect();
            const platzUnten = window.innerHeight - k.bottom - 12;

            if (m.height > platzUnten) {
                this.oben = Math.max(12, k.top - m.height - 6);
            }
        });
    },
}"
:style="open ? { opacity: 1 } : {}"
@keydown.escape.window="open = false"
{{-- .capture, weil Scroll-Ereignisse nicht aufsteigen: Das Programm scrollt in
     einem inneren Kasten, nicht am Fenster. Ohne das bliebe das Menü stehen,
     während die Zeile darunter wegwandert. --}}
@scroll.window.capture="open = false"
@resize.window="open = false"
