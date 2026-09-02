<?php

namespace Platform\Reservation\Tests\Feature;

use Platform\Reservation\Models\CheckoutSession;
use Platform\Reservation\Services\LiveCheckoutService;
use Platform\Reservation\Tests\Concerns\ErzeugtTermine;
use Platform\Reservation\Tests\TestCase;

/**
 * Die Sicht auf laufende Bestellwege.
 *
 * Geprüft wird der Dienst, nicht die Route: Was hier schiefgehen kann, ist die
 * Buchführung – eine Meldung, die eine zweite Zeile anlegt statt die erste zu
 * überschreiben, ein Eintrag, der nach dem Schließen des Tabs stehen bleibt,
 * eine fremde Pause, die durchrutscht.
 */
class LaufendeBestellwegeTest extends TestCase
{
    use ErzeugtTermine;

    private LiveCheckoutService $dienst;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dienst = new LiveCheckoutService();
    }

    public function test_eine_meldung_legt_genau_eine_zeile_an(): void
    {
        $termin = $this->termin(1);

        $this->dienst->merken($termin, $this->ref(), ['step' => 'products', 'party_size' => 4]);

        $this->assertSame(1, CheckoutSession::count());
        $this->assertSame((int) $termin->team_id, (int) CheckoutSession::first()->team_id);
    }

    public function test_dieselbe_kennung_ueberschreibt_statt_anzulegen(): void
    {
        $termin = $this->termin(1);
        $ref    = $this->ref();

        $this->dienst->merken($termin, $ref, ['step' => 'products', 'items_count' => 1, 'cart_total' => 9.5]);
        $this->dienst->merken($termin, $ref, ['step' => 'seat', 'items_count' => 3, 'cart_total' => 27.0]);

        $this->assertSame(1, CheckoutSession::count());

        $zeile = CheckoutSession::first();
        $this->assertSame('seat', $zeile->step);
        $this->assertSame(3, $zeile->items_count);
        $this->assertSame('27.00', (string) $zeile->cart_total);
    }

    public function test_eine_fremde_pause_wird_nicht_uebernommen(): void
    {
        $termin  = $this->termin(1);
        $fremder = $this->termin(1);

        $this->dienst->merken($termin, $this->ref(), [
            'step'          => 'products',
            'event_slot_id' => $this->pause($fremder, 1)->id,
        ]);

        $this->assertNull(CheckoutSession::first()->event_slot_id);
    }

    public function test_die_eigene_pause_wird_uebernommen(): void
    {
        $termin = $this->termin(2);
        $pause  = $this->pause($termin, 2);

        $this->dienst->merken($termin, $this->ref(), [
            'step'          => 'products',
            'event_slot_id' => $pause->id,
        ]);

        $this->assertSame($pause->id, CheckoutSession::first()->event_slot_id);
    }

    public function test_ohne_lebenszeichen_faellt_ein_eintrag_aus_der_sicht(): void
    {
        $termin = $this->termin(1);

        $this->dienst->merken($termin, $this->ref(), ['step' => 'products']);
        $this->assertCount(1, $this->dienst->laufende($termin));

        // Vier Minuten Stille - der Herzschlag kommt jede Minute, drei fehlende
        // sind kein Aussetzer mehr.
        CheckoutSession::query()->update([
            'last_seen_at' => now()->subMinutes(CheckoutSession::LEBT_MINUTEN + 1),
        ]);

        $this->assertCount(0, $this->dienst->laufende($termin));

        // Aus der SICHT, nicht aus der Tabelle: Der Gast kann zurückkommen.
        $this->assertSame(1, CheckoutSession::count());
    }

    public function test_aufraeumen_loescht_nur_das_wirklich_alte(): void
    {
        $termin = $this->termin(1);

        $this->dienst->merken($termin, $this->ref(), ['step' => 'products']);
        $this->dienst->merken($termin, $this->ref(), ['step' => 'seat']);

        CheckoutSession::query()->limit(1)->update([
            'last_seen_at' => now()->subMinutes(CheckoutSession::AUFRAEUMEN_NACH_MINUTEN + 1),
        ]);

        $this->assertSame(1, $this->dienst->aufraeumen());
        $this->assertSame(1, CheckoutSession::count());
    }

    public function test_beenden_loescht_den_eintrag(): void
    {
        $termin = $this->termin(1);
        $ref    = $this->ref();

        $this->dienst->merken($termin, $ref, ['step' => 'pay']);
        $this->dienst->beenden($termin, $ref);

        $this->assertSame(0, CheckoutSession::count());
    }

    public function test_ein_fremder_termin_sieht_die_zeile_nicht(): void
    {
        $termin  = $this->termin(1);
        $anderer = $this->termin(1);

        $this->dienst->merken($termin, $this->ref(), ['step' => 'products']);

        $this->assertCount(0, $this->dienst->laufende($anderer));
    }

    public function test_die_zusammenfassung_zaehlt_gaeste_und_warenkorb(): void
    {
        $termin = $this->termin(1);

        $this->dienst->merken($termin, $this->ref(), ['step' => 'products', 'party_size' => 2, 'cart_total' => 18.50]);
        $this->dienst->merken($termin, $this->ref(), ['step' => 'seat', 'party_size' => 5, 'cart_total' => 41.50]);

        $summe = $this->dienst->zusammenfassung($this->dienst->laufende($termin));

        $this->assertSame(2, $summe['anzahl']);
        $this->assertSame(7, $summe['gaeste']);
        $this->assertSame(60.0, $summe['warenkorb']);
    }

    public function test_der_fortschritt_kommt_vom_shop_und_wird_nicht_geraten(): void
    {
        $termin = $this->termin(2);
        $ref    = $this->ref();

        $this->dienst->merken($termin, $ref, ['step' => 'seat', 'step_no' => 3, 'step_count' => 5]);

        $this->assertSame('Schritt 3 von 5', CheckoutSession::first()->fortschritt());

        // Meldet ein aelterer Shop die Zahlen nicht, steht dort nichts - statt
        // einer aus der Pausenzahl geratenen Angabe, die auf dem Bildschirm des
        // Gastes nirgends steht.
        $this->dienst->merken($termin, $ref, ['step' => 'seat']);

        $this->assertNull(CheckoutSession::first()->fortschritt());
    }

    public function test_unsinnige_schrittzahlen_werden_verworfen(): void
    {
        $termin = $this->termin(1);

        $this->dienst->merken($termin, $this->ref(), [
            'step'       => 'seat',
            'step_no'    => 0,
            'step_count' => -4,
        ]);

        $zeile = CheckoutSession::first();

        $this->assertNull($zeile->step_no);
        $this->assertNull($zeile->step_count);
        $this->assertNull($zeile->fortschritt());
    }

    public function test_der_warenkorb_wird_je_pause_gemerkt(): void
    {
        $termin = $this->termin(2);
        $eins   = $this->pause($termin, 1);
        $zwei   = $this->pause($termin, 2);

        $this->dienst->merken($termin, $this->ref(), [
            'step'  => 'products',
            'items' => [
                $eins->id => [7 => 2],
                $zwei->id => [9 => 1],
            ],
        ]);

        $zeile = CheckoutSession::first();

        $this->assertSame([
            (string) $eins->id => ['7' => 2],
            (string) $zwei->id => ['9' => 1],
        ], $zeile->items);
        $this->assertTrue($zeile->hatWarenkorb());
    }

    public function test_ein_warenkorb_zu_einer_fremden_pause_wird_verworfen(): void
    {
        $termin  = $this->termin(1);
        $fremder = $this->termin(1);

        $this->dienst->merken($termin, $this->ref(), [
            'step'  => 'products',
            'items' => [$this->pause($fremder, 1)->id => [7 => 2]],
        ]);

        // Sonst stuende in der Ansicht dieses Termins der Warenkorb eines
        // anderen - bei fremdem Team ein Leck ueber die Beschriftung.
        $this->assertNull(CheckoutSession::first()->items);
        $this->assertFalse(CheckoutSession::first()->hatWarenkorb());
    }

    public function test_krumme_mengen_fallen_aus_dem_warenkorb(): void
    {
        $termin = $this->termin(1);
        $pause  = $this->pause($termin, 1);

        $this->dienst->merken($termin, $this->ref(), [
            'step'  => 'products',
            'items' => [$pause->id => [7 => 0, 8 => -3, 9 => 2, 0 => 5]],
        ]);

        $this->assertSame([(string) $pause->id => ['9' => 2]], CheckoutSession::first()->items);
    }

    public function test_der_warenkorb_ist_nach_oben_begrenzt(): void
    {
        $termin = $this->termin(1);
        $pause  = $this->pause($termin, 1);

        $viel = [];
        for ($i = 1; $i <= LiveCheckoutService::GRENZE_POSITIONEN + 50; $i++) {
            $viel[$i] = 1;
        }

        $this->dienst->merken($termin, $this->ref(), [
            'step'  => 'products',
            'items' => [$pause->id => $viel],
        ]);

        // Die Zeile lebt Minuten - sie soll klein bleiben.
        $this->assertCount(
            LiveCheckoutService::GRENZE_POSITIONEN,
            CheckoutSession::first()->items[(string) $pause->id],
        );
    }

    public function test_der_angeklickte_tisch_wird_je_pause_gemerkt(): void
    {
        $termin = $this->termin(2);
        $eins   = $this->pause($termin, 1);
        $zwei   = $this->pause($termin, 2);

        $this->dienst->merken($termin, $this->ref(), [
            'step'   => 'seat',
            'tables' => [$eins->id => 12, $zwei->id => 34],
        ]);

        $zeile = CheckoutSession::first();

        // Je Pause ein eigener: Wird jede Pause einzeln vergeben, sitzt
        // derselbe Gast spaeter woanders.
        $this->assertSame([(string) $eins->id => 12, (string) $zwei->id => 34], $zeile->tables);

        // Ein Tisch allein reicht fuers Fenster - beim Sitzplatz-Schritt kann
        // er an einer Pause haengen, in der noch nichts im Korb liegt.
        $this->assertTrue($zeile->hatDetails());
        $this->assertFalse($zeile->hatWarenkorb());
    }

    public function test_ein_tisch_an_einer_fremden_pause_wird_verworfen(): void
    {
        $termin  = $this->termin(1);
        $fremder = $this->termin(1);

        $this->dienst->merken($termin, $this->ref(), [
            'step'   => 'seat',
            'tables' => [$this->pause($fremder, 1)->id => 12],
        ]);

        $this->assertNull(CheckoutSession::first()->tables);
    }

    public function test_unbekannte_schritte_zeigen_ihren_rohnamen(): void
    {
        $zeile = new CheckoutSession(['step' => 'products']);
        $this->assertSame('Produkte', $zeile->schrittLabel());

        // Käme im Shop ein Schritt hinzu, den das Modul nicht kennt, soll die
        // Ansicht ihn benennen statt eine Lücke zu zeigen.
        $zeile = new CheckoutSession(['step' => 'where']);
        $this->assertSame('where', $zeile->schrittLabel());
    }

    private function ref(): string
    {
        return (string) \Illuminate\Support\Str::uuid();
    }
}
