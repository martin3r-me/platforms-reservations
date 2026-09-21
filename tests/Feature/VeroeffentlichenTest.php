<?php

namespace Platform\Reservation\Tests\Feature;

use Platform\Reservation\Tests\Concerns\ErzeugtTermine;
use Platform\Reservation\Tests\TestCase;

/**
 * Was einem Termin zum Veröffentlichen fehlt.
 *
 * Die Regel steht am Modell, weil Oberfläche und MCP-Werkzeug dieselbe
 * brauchen: Stünde sie in der Blade-Datei, erlaubte das Werkzeug, was der
 * Knopf verbietet - und niemand merkte es, bis ein Termin im Shop steht, den
 * es nicht mehr gibt.
 *
 * Der vergangene Abend kam spät dazu: Veröffentlichen hiess bisher nur "hat
 * eine Pause und einen Ort". Ein abgesagtes Konzert von vorletzter Woche liess
 * sich damit in den Gast-Shop stellen - bestellbar war es nie wieder, der
 * Bestellschluss lag lange davor.
 */
class VeroeffentlichenTest extends TestCase
{
    use ErzeugtTermine;

    public function test_ein_kommender_termin_mit_pause_und_raum_darf_veroeffentlicht_werden(): void
    {
        $termin = $this->termin(1, ['date' => now()->addWeek()->toDateString()]);
        $this->haengeRaumAn($termin, $this->raum('Saal', [6]));

        $this->assertSame([], $termin->fresh()->fehltZumVeroeffentlichen());
    }

    public function test_ein_vergangener_termin_laesst_sich_nicht_veroeffentlichen(): void
    {
        $termin = $this->termin(1, ['date' => now()->subWeek()->toDateString()]);
        $this->haengeRaumAn($termin, $this->raum('Saal', [6]));

        // Pause und Raum stehen - es fehlt nur noch das, was sich nicht
        // nachtragen laesst.
        $this->assertSame(['ein Datum in der Zukunft'], $termin->fresh()->fehltZumVeroeffentlichen());
    }

    public function test_heute_zaehlt_noch_nicht_als_vergangen(): void
    {
        // Der Abend laeuft noch: Wer mittags einen Termin fuer denselben Abend
        // freischaltet, soll das koennen.
        $termin = $this->termin(1, ['date' => now()->toDateString()]);
        $this->haengeRaumAn($termin, $this->raum('Saal', [6]));

        $this->assertSame([], $termin->fresh()->fehltZumVeroeffentlichen());
    }
}
