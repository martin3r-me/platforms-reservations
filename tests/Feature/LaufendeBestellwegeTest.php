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
