<?php

namespace Platform\Reservation\Tests\Feature;

use Platform\Reservation\Models\CheckoutSession;
use Platform\Reservation\Models\CheckoutStat;
use Platform\Reservation\Services\CheckoutStatsService;
use Platform\Reservation\Services\LiveCheckoutService;
use Platform\Reservation\Tests\Concerns\ErzeugtTermine;
use Platform\Reservation\Tests\TestCase;

/**
 * Aus einem beendeten Bestellweg wird eine Statistikzeile.
 *
 * Die heikle Stelle ist der Übergang: Genau dann, wenn die Live-Zeile
 * verschwindet, muss die Statistikzeile da sein – und zwar genau eine. Geht
 * hier etwas daneben, fehlt der Vorgang für immer; nachrechnen lässt er sich
 * nicht mehr, die Quelle ist ja gelöscht.
 */
class BestellwegAuswertungTest extends TestCase
{
    use ErzeugtTermine;

    private LiveCheckoutService $live;
    private CheckoutStatsService $auswertung;

    protected function setUp(): void
    {
        parent::setUp();

        $this->live       = new LiveCheckoutService();
        $this->auswertung = new CheckoutStatsService();
    }

    public function test_eine_bestellung_hinterlaesst_genau_eine_zeile(): void
    {
        $termin = $this->termin(1);
        $ref    = $this->ref();

        $this->live->merken($termin, $ref, [
            'step' => 'pay', 'step_no' => 4, 'step_count' => 4,
            'party_size' => 3, 'items_count' => 5, 'cart_total' => 42.50,
        ]);

        $this->live->beenden($termin, $ref);

        $this->assertSame(0, CheckoutSession::count());
        $this->assertSame(1, CheckoutStat::count());

        $zeile = CheckoutStat::first();

        $this->assertSame(CheckoutStat::AUSGANG_BESTELLT, $zeile->outcome);
        $this->assertSame('pay', $zeile->last_step);
        $this->assertSame(3, $zeile->party_size);
        $this->assertSame('42.50', (string) $zeile->cart_total);

        // Das Termindatum wird MITGESCHRIEBEN - es soll den Termin ueberleben.
        $this->assertSame('2026-10-01', $zeile->event_date->toDateString());
    }

    public function test_ein_abbruch_wird_beim_aufraeumen_verbucht(): void
    {
        $termin = $this->termin(1);

        $this->live->merken($termin, $this->ref(), ['step' => 'seat', 'party_size' => 2]);

        CheckoutSession::query()->update([
            'last_seen_at' => now()->subMinutes(CheckoutSession::AUFRAEUMEN_NACH_MINUTEN + 1),
        ]);

        $this->assertSame(1, $this->live->aufraeumen());
        $this->assertSame(0, CheckoutSession::count());

        $zeile = CheckoutStat::first();

        $this->assertSame(CheckoutStat::AUSGANG_ABGEBROCHEN, $zeile->outcome);
        $this->assertSame('seat', $zeile->last_step);
    }

    public function test_der_abbruch_traegt_das_datum_des_letzten_lebenszeichens(): void
    {
        $termin = $this->termin(1);
        $still  = now()->subDays(40);

        $this->live->merken($termin, $this->ref(), ['step' => 'seat']);

        CheckoutSession::query()->update(['last_seen_at' => $still]);

        $this->live->aufraeumen();

        // NICHT der Zeitpunkt des Aufraeumens: Das laeuft per Los beim
        // Schreiben und kann auf einer ruhigen Seite lange auf sich warten
        // lassen. Stuende hier "jetzt", landete ein Abbruch von vor sechs
        // Wochen in der Auswertung fuer diesen Monat.
        $this->assertSame(
            $still->toDateTimeString(),
            CheckoutStat::first()->ended_at->toDateTimeString(),
        );
    }

    public function test_was_noch_laeuft_wird_nicht_verbucht(): void
    {
        $termin = $this->termin(1);

        $this->live->merken($termin, $this->ref(), ['step' => 'products']);
        $this->live->aufraeumen();

        // Sonst zaehlte ein Gast als Abbrecher, waehrend er noch bestellt.
        $this->assertSame(0, CheckoutStat::count());
        $this->assertSame(1, CheckoutSession::count());
    }

    public function test_die_quote_rechnet_bestellt_gegen_alles(): void
    {
        $termin = $this->termin(1);

        $this->beende($termin, 'pay', CheckoutStat::AUSGANG_BESTELLT);
        $this->beende($termin, 'seat', CheckoutStat::AUSGANG_ABGEBROCHEN);
        $this->beende($termin, 'seat', CheckoutStat::AUSGANG_ABGEBROCHEN);
        $this->beende($termin, 'guest', CheckoutStat::AUSGANG_ABGEBROCHEN);

        $summe = $this->auswertung->zusammenfassung($this->teamId, '2026-01-01', '2026-12-31');

        $this->assertSame(4, $summe['gesamt']);
        $this->assertSame(1, $summe['bestellt']);
        $this->assertSame(3, $summe['abgebrochen']);
        $this->assertSame(25.0, $summe['quote']);
    }

    public function test_abbrueche_stehen_in_der_reihenfolge_des_bestellwegs(): void
    {
        $termin = $this->termin(1);

        $this->beende($termin, 'guest', CheckoutStat::AUSGANG_ABGEBROCHEN);
        $this->beende($termin, 'products', CheckoutStat::AUSGANG_ABGEBROCHEN);
        $this->beende($termin, 'seat', CheckoutStat::AUSGANG_ABGEBROCHEN);
        $this->beende($termin, 'seat', CheckoutStat::AUSGANG_ABGEBROCHEN);

        // Bestellte gehoeren nicht in die Abbruch-Verteilung.
        $this->beende($termin, 'pay', CheckoutStat::AUSGANG_BESTELLT);

        $schritte = $this->auswertung->abbruecheJeSchritt($this->teamId, '2026-01-01', '2026-12-31');

        $this->assertSame(['products', 'seat', 'guest'], array_column($schritte, 'step'));
        $this->assertSame([1, 2, 1], array_column($schritte, 'anzahl'));

        // Anteil AN ALLEN ABBRUECHEN, nicht an denen, die den Schritt erreicht
        // haben - diese zweite Zahl gaebe es hier nicht (siehe Dienst).
        $this->assertSame(50.0, $schritte[1]['anteil']);
    }

    public function test_termine_stehen_nach_der_zahl_der_abbrueche(): void
    {
        $viele = $this->termin(1, ['name' => 'Viele Abbrüche']);
        $wenig = $this->termin(1, ['name' => 'Wenige']);

        foreach (range(1, 3) as $i) {
            $this->beende($viele, 'seat', CheckoutStat::AUSGANG_ABGEBROCHEN);
        }

        // Ein Termin mit genau einem Vorgang, und der ist ein Abbruch: 0 %
        // Quote. Nach Quote sortiert stuende er oben, obwohl daran nichts zu
        // sehen ist.
        $this->beende($wenig, 'seat', CheckoutStat::AUSGANG_ABGEBROCHEN);

        $termine = $this->auswertung->termine($this->teamId, '2026-01-01', '2026-12-31');

        $this->assertSame('Viele Abbrüche', $termine[0]['name']);
        $this->assertSame(3, $termine[0]['abgebrochen']);
    }

    public function test_ein_geloeschter_termin_nimmt_die_zahlen_nicht_mit(): void
    {
        $termin = $this->termin(1, ['name' => 'Wird gelöscht']);

        $this->beende($termin, 'seat', CheckoutStat::AUSGANG_ABGEBROCHEN);

        $termin->delete();

        // Der Vorgang hat stattgefunden. Ein Cascade wuerde die Vergangenheit
        // umschreiben, sobald jemand einen alten Termin aufraeumt.
        $this->assertSame(1, CheckoutStat::count());
        $this->assertNull(CheckoutStat::first()->event_id);

        $termine = $this->auswertung->termine($this->teamId, '2026-01-01', '2026-12-31');

        $this->assertSame('Gelöschter Termin', $termine[0]['name']);
    }

    /** Einen Bestellweg anlegen und sofort mit einem Ausgang beenden. */
    private function beende($termin, string $schritt, string $ausgang): void
    {
        $ref = $this->ref();

        $this->live->merken($termin, $ref, ['step' => $schritt, 'cart_total' => 10.0]);

        if ($ausgang === CheckoutStat::AUSGANG_BESTELLT) {
            $this->live->beenden($termin, $ref);

            return;
        }

        CheckoutSession::where('checkout_ref', $ref)->update([
            'last_seen_at' => now()->subMinutes(CheckoutSession::AUFRAEUMEN_NACH_MINUTEN + 1),
        ]);

        $this->live->aufraeumen();
    }

    private function ref(): string
    {
        return (string) \Illuminate\Support\Str::uuid();
    }
}
