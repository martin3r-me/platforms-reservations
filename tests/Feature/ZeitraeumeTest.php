<?php

namespace Platform\Reservation\Tests\Feature;

use Platform\Reservation\Support\Zeitraum;
use Platform\Reservation\Tests\TestCase;

/**
 * Die Zeiträume der Auswertungen.
 *
 * Sie standen dreimal im Modul und jedes Mal anders – in den Finanzen fehlte
 * „Dieser Monat" ganz. Jetzt liegen die gemeinsamen hier, und jede Seite legt
 * höchstens eigene dazu.
 *
 * Geprüft wird nur diese Liste, nicht die drei Komponenten: Livewire ist in der
 * Testbasis dieses Moduls nicht installiert (es kommt aus der Wirtsanwendung).
 * Was dort steht, ist ohnehin eine Zeile – `Zeitraum::beschriftungen() + [...]`.
 */
class ZeitraeumeTest extends TestCase
{
    public function test_die_liste_ist_von_kurz_nach_lang_sortiert(): void
    {
        $liste = Zeitraum::beschriftungen();

        // Die Reihenfolge ist die der Leiste. „Letzte Woche" zuerst, weil man
        // am haeufigsten in die juengste Vergangenheit sieht.
        $this->assertSame('last_week', array_key_first($liste));
        $this->assertSame('last_year', array_key_last($liste));
        $this->assertArrayHasKey('month', $liste);
    }

    public function test_jeder_gemeinsame_zeitraum_liefert_eine_spanne(): void
    {
        foreach (array_keys(Zeitraum::beschriftungen()) as $schluessel) {
            [$von, $bis] = Zeitraum::spanne($schluessel);

            $this->assertLessThanOrEqual($bis, $von, $schluessel . ': von liegt hinter bis');
        }
    }

    public function test_unbekanntes_wird_nicht_stillschweigend_ersetzt(): void
    {
        // Null und nicht „dieses Jahr": „Alles" beantwortet jede Seite selbst,
        // und wer sich vertippt, soll es merken.
        $this->assertNull(Zeitraum::spanne('all'));
        $this->assertNull(Zeitraum::spanne('gibt-es-nicht'));
    }
}
