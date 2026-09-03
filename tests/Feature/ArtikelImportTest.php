<?php

namespace Platform\Reservation\Tests\Feature;

use Platform\Reservation\Models\HoldingClass;
use Platform\Reservation\Models\MenuItem;
use Platform\Reservation\Services\MenuItemCsvImporter;
use Platform\Reservation\Tests\TestCase;

/**
 * Der CSV-Import für Artikel – die Spalten, die nach dem ersten Wurf dazukamen.
 *
 * Standzeit, Altersgrenze und Koffein sind später an den Artikel gekommen als
 * der Import. Genau solche Felder fallen still durch: Die Datei enthält sie,
 * niemand liest sie, und der Artikel steht ohne sie im Menü.
 */
class ArtikelImportTest extends TestCase
{
    private MenuItemCsvImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new MenuItemCsvImporter();
    }

    public function test_die_standzeit_wird_ueber_ihren_namen_zugeordnet(): void
    {
        $klasse = HoldingClass::create(['team_id' => 1, 'name' => 'Sollte kalt sein']);

        $ergebnis = $this->importiere(
            "name;preis;standzeit\nSekt;5,90;sollte kalt sein\n"
        );

        $this->assertSame(1, $ergebnis['created']);
        $this->assertSame($klasse->id, MenuItem::first()->holding_class_id);
    }

    public function test_eine_unbekannte_standzeit_wird_gemeldet_und_nicht_angelegt(): void
    {
        $rows = $this->importer->parse("name;preis;standzeit\nSekt;5,90;Gibt es nicht\n", 1)['rows'];

        $this->assertSame(MenuItemCsvImporter::STATUS_WARNING, $rows[0]['status']);
        $this->assertStringContainsString('Gibt es nicht', implode(' ', $rows[0]['messages']));

        // Nicht angelegt: An einer Klasse haengt eine Vorlaufzeit, und eine
        // erfundene waere eine Zusage an die Kueche, die niemand geprueft hat.
        $this->importer->import($rows, 1);

        $this->assertSame(0, HoldingClass::count());
        $this->assertNull(MenuItem::first()->holding_class_id);
    }

    public function test_altersgrenze_und_koffein_kommen_mit(): void
    {
        $this->importiere("name;preis;altersgrenze;koffein;koffein_mg\nMate;3,50;16;ja;20\n");

        $artikel = MenuItem::first();

        // min_age ist als Enum gecastet - der Wert steckt in ->value.
        $this->assertSame(16, $artikel->min_age->value);
        $this->assertTrue((bool) $artikel->is_caffeinated);
        $this->assertSame('20.0', (string) $artikel->caffeine_mg);
    }

    public function test_eine_ungueltige_altersgrenze_wird_verworfen(): void
    {
        // Nur 16 und 18 gibt es. Eine 21 waere eine Grenze, die der Checkout
        // gar nicht abfragen kann - dann lieber gar keine.
        $this->importiere("name;preis;altersgrenze\nSekt;5,90;21\n");

        $this->assertNull(MenuItem::first()->min_age);
    }

    public function test_nur_der_name_ist_pflicht(): void
    {
        $this->importiere("name\nBrezel\n");

        $artikel = MenuItem::first();

        $this->assertSame('Brezel', $artikel->name);
        $this->assertNull($artikel->holding_class_id);
        $this->assertNull($artikel->min_age);
    }

    /** @return array{created: int, skipped: int} */
    private function importiere(string $csv): array
    {
        $rows = $this->importer->parse($csv, 1)['rows'];

        return $this->importer->import($rows, 1);
    }
}
