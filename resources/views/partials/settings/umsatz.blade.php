{{-- Umsatzabgrenzung: welche Buchungen als Erlös gelten.

     Steht unter Buchhaltung und nicht bei den Terminen, weil die Antwort
     Umsatz, Artikel-Auswertung, Startseite und den DATEV-Stapel zugleich
     betrifft – alle vier lesen CheckoutSetting::umsatzStatus(). --}}
<x-nx-card flush>
    <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
        @svg('heroicon-o-banknotes', 'w-4 h-4 text-[color:var(--nx-muted)]')
        <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Umsatzabgrenzung</h2>
        <span class="ml-auto text-[11px] text-[color:var(--nx-muted)]">gilt für Umsatz, Auswertung und DATEV</span>
    </div>
    <div class="space-y-4 p-5">
        <label class="flex items-start gap-2 text-sm text-[color:var(--nx-text)] cursor-pointer">
            <input wire:model="revenueIncludesNoShow" type="checkbox" class="mt-0.5 rounded-[4px] accent-[var(--nx-accent)]" />
            <span>No-Shows zählen zum Umsatz</span>
        </label>

        <p class="m-0 text-[11px] leading-relaxed text-[color:var(--nx-muted)]">
            Wer im Shop bezahlt und dann nicht kommt, bekommt das Geld in der Regel nicht
            zurück – dann ist es Erlös und gehört in die Buchhaltung. Wird dagegen erst vor
            Ort kassiert oder aus Kulanz immer erstattet, gibt es bei einem No-Show keine
            Einnahme. Bei einer echten Erstattung über den Storno fällt die Buchung ohnehin
            heraus, unabhängig von dieser Einstellung.
        </p>

        {{-- Die Einstellung wirkt rückwirkend auf jede Auswertung. Wer sie
             mitten im Jahr umlegt, sieht danach andere Zahlen für Monate, die
             er längst abgeschlossen hat. Das gehört dazugesagt. --}}
        <x-nx-callout variant="warning" title="Wirkt auf alle Zeiträume">
            Die Umstellung ändert auch vergangene Monate – Umsatz, Artikel-Auswertung und
            der DATEV-Stapel rechnen danach überall gleich. Ist ein Zeitraum in der Kanzlei
            schon gebucht, vorher dort Bescheid sagen.
        </x-nx-callout>

        <p class="m-0 text-[11px] text-[color:var(--nx-muted)]">
            Unabhängig davon zählen <strong>ausstehende</strong> Buchungen nie mit (bestellt,
            aber nicht bezahlt) und <strong>stornierte</strong> ebenso wenig. Für Küche,
            Laufzettel und Platzprüfung bleibt ein No-Show immer draußen – dort geht es um
            Gäste, nicht um Geld.
        </p>
    </div>
</x-nx-card>
