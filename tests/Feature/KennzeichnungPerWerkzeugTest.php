<?php

namespace Platform\Reservation\Tests\Feature;

use Platform\Reservation\Models\Additive;
use Platform\Reservation\Models\Allergen;
use Platform\Reservation\Models\MenuCategory;
use Platform\Reservation\Models\MenuItem;
use Platform\Reservation\Tests\TestCase;
use Platform\Reservation\Tools\Concerns\PflegtKennzeichnung;

/**
 * Allergene und Zusatzstoffe über die Werkzeuge setzen.
 *
 * Sie ließen sich bis zum 03.09.2026 nur beim MASSENANLEGEN mitgeben: Ein
 * einzeln angelegter Artikel bekam nie welche, und eine falsche Kennzeichnung
 * war über die Werkzeuge gar nicht zu korrigieren. Bei einer rechtlich
 * verpflichtenden Angabe ist das die falsche Lücke.
 *
 * Geprüft wird der gemeinsame Zug, nicht die Werkzeuge selbst – die brauchten
 * einen ToolContext, und um den geht es hier nicht.
 */
class KennzeichnungPerWerkzeugTest extends TestCase
{
    use PflegtKennzeichnung;

    private MenuItem $artikel;

    protected function setUp(): void
    {
        parent::setUp();

        Allergen::create(['team_id' => 1, 'code' => 'A', 'name' => 'Glutenhaltiges Getreide']);
        Allergen::create(['team_id' => 1, 'code' => 'G', 'name' => 'Milch']);
        Additive::create(['team_id' => 1, 'code' => '11', 'name' => 'koffeinhaltig']);

        $kategorie = MenuCategory::create(['team_id' => 1, 'name' => 'Getränke']);

        $this->artikel = MenuItem::create([
            'team_id'     => 1,
            'category_id' => $kategorie->id,
            'name'        => 'Cola',
            'price'       => 3.20,
            'tax_rate'    => 19,
        ]);
    }

    public function test_codes_werden_zugeordnet(): void
    {
        $ergebnis = $this->kennzeichnungSetzen($this->artikel, [
            'allergen_codes' => ['A', 'G'],
            'additive_codes' => ['11'],
        ], 1);

        $this->assertSame([], $ergebnis['unbekannt']);
        $this->assertTrue($ergebnis['geaendert']);
        $this->assertCount(2, $this->artikel->allergens()->get());
        $this->assertCount(1, $this->artikel->additives()->get());
    }

    public function test_unbekannte_codes_werden_gemeldet_statt_geschluckt(): void
    {
        $ergebnis = $this->kennzeichnungSetzen($this->artikel, [
            'allergen_codes' => ['A', 'Z'],
        ], 1);

        // Gemeldet, nicht verschwiegen: Eine verlorene Kennzeichnung faellt
        // sonst niemandem auf. Und nicht abgebrochen: Ein Tippfehler soll nicht
        // den ganzen Artikel kosten.
        $this->assertSame(['Z'], $ergebnis['unbekannt']);
        $this->assertCount(1, $this->artikel->allergens()->get());
    }

    public function test_ein_leeres_array_entfernt_alle(): void
    {
        $this->kennzeichnungSetzen($this->artikel, ['allergen_codes' => ['A', 'G']], 1);
        $this->kennzeichnungSetzen($this->artikel, ['allergen_codes' => []], 1);

        $this->assertCount(0, $this->artikel->allergens()->get());
    }

    public function test_ohne_bewegung_keine_meldung(): void
    {
        $this->kennzeichnungSetzen($this->artikel, ['allergen_codes' => ['A']], 1);

        // Dieselbe Kennzeichnung noch einmal: Daran haengt beim Aendern die
        // Freigabe - ein Aufruf, der nichts bewegt, darf sie nicht kosten.
        $ergebnis = $this->kennzeichnungSetzen($this->artikel, ['allergen_codes' => ['A']], 1);

        $this->assertFalse($ergebnis['geaendert']);
    }

    public function test_weglassen_laesst_die_kennzeichnung_unveraendert(): void
    {
        $this->kennzeichnungSetzen($this->artikel, ['allergen_codes' => ['A']], 1);

        // Nur der Zusatzstoff kommt mit - die Allergene bleiben, wie sie sind.
        // Ohne diese Unterscheidung loeschte jede Preisaenderung die Allergene.
        $this->kennzeichnungSetzen($this->artikel, ['additive_codes' => ['11']], 1);

        $this->assertCount(1, $this->artikel->allergens()->get());
        $this->assertCount(1, $this->artikel->additives()->get());
    }
}
