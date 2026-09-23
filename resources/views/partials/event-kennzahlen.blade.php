{{-- Kennzahlen unter den Reitern eines Termins.

     Kacheln aus dem UI-Kit, nicht selbst gebaut: Dieselben Kacheln stehen in
     der Artikel-Statistik. Zwei eigene Fassungen liefen schon einmal
     auseinander - "Buchungen" ein Raster ueber die Breite, "Kueche" eine
     schmale Zeile links.

     Der Hinweis gehoert IN die Kachel, nicht darunter. Als eigene Zeile machte
     er den Kopf genau dort hoeher, wo er ihn trug, und der Reiterwechsel
     sprang wieder. In der Kachel traegt ihn die Zahl, auf die er sich bezieht -
     und eine Kachel wird sowieso einzeln gelesen.

     $tiles : [[Label, Wert, Hinweis|null], ...]
--}}
<x-nx-stat-grid>
    @foreach ($tiles as $tile)
        <x-nx-stat :label="$tile[0]" :value="(string) $tile[1]" :hint="$tile[2] ?? null" />
    @endforeach
</x-nx-stat-grid>
