<?php

namespace Platform\Reservation\Tests\Feature;

use Platform\Reservation\Contracts\MollieCredentialResolver;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Models\Order;
use Platform\Reservation\Models\Payment;
use Platform\Reservation\Services\EventCancellationService;
use Platform\Reservation\Support\MollieCredentials;
use Platform\Reservation\Tests\Concerns\ErzeugtTermine;
use Platform\Reservation\Tests\TestCase;

/**
 * Absage eines Termins: alle Bestellungen stornieren und erstatten.
 *
 * Bis zum 04.09.2026 setzte eine Absage nur den Status des Termins. Die
 * Buchungen blieben „bestätigt", die Gäste standen weiter auf dem Laufzettel,
 * und das Geld lag beim Haus.
 *
 * Ohne Mollie-Zugangsdaten löst refundOrder() keine echte Erstattung aus – für
 * diese Tests ist das richtig so: Geprüft wird, WAS storniert wird und was
 * unangetastet bleibt. Dass die Erstattung selbst gegen Doppelbuchung
 * gesichert ist, entscheidet refundOrder() über refunded_at.
 */
class TerminAbsageErstattungTest extends TestCase
{
    use ErzeugtTermine;

    private EventCancellationService $dienst;
    private Event $termin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(MollieCredentialResolver::class, fn () => new class implements MollieCredentialResolver {
            public function forTeam(int $teamId): ?MollieCredentials
            {
                return null;
            }
        });

        $this->dienst = app(EventCancellationService::class);
        $this->termin = $this->termin(1);

        $plan = $this->raum('Saal', [4, 4, 4, 4]);
        $this->haengeRaumAn($this->termin, $plan);
        $this->plan = $plan;
    }

    private $plan;

    /** Bestellung mit einer Buchung und einer Position zu 10 €. */
    private function bestellungMit(string $orderStatus, int $tischNr, string $zahlung = 'paid'): array
    {
        $bestellung = $this->bestellung($this->termin);
        $bestellung->update(['status' => $orderStatus]);

        $buchung = $this->buchung(
            $this->pause($this->termin, 1),
            $this->tisch($this->plan, $tischNr),
            2,
            $bestellung,
            Booking::STATUS_CONFIRMED,
        );
        $this->position($buchung, $this->artikel(10.0), 1);

        Payment::create([
            'order_id'  => $bestellung->id,
            'mollie_id' => 'tr_' . $bestellung->id,
            'amount'    => 10.00,
            'currency'  => 'EUR',
            'status'    => $zahlung,
        ]);

        return [$bestellung->fresh(), $buchung];
    }

    public function test_die_vorschau_nennt_zahl_und_summe(): void
    {
        $this->bestellungMit(Order::STATUS_CONFIRMED, 1);
        $this->bestellungMit(Order::STATUS_CONFIRMED, 2);

        $vorschau = $this->dienst->vorschau($this->termin);

        // Beides, denn "2 Bestellungen" ist etwas anderes als "2 Bestellungen
        // ueber 20 Euro". Wer bestaetigt, soll wissen worueber.
        $this->assertSame(2, $vorschau['anzahl']);
        $this->assertSame(20.0, $vorschau['summe']);
    }

    public function test_bestaetigte_bestellungen_werden_storniert(): void
    {
        [$bestellung, $buchung] = $this->bestellungMit(Order::STATUS_CONFIRMED, 1);

        $ergebnis = $this->dienst->alleStornieren($this->termin);

        $this->assertSame(1, $ergebnis['storniert']);
        $this->assertSame([], $ergebnis['fehler']);
        $this->assertSame(Order::STATUS_CANCELLED, $bestellung->fresh()->status);
        // Der Platz ist wieder zu haben - darum geht es bei einer Absage.
        $this->assertSame(Booking::STATUS_CANCELLED, $buchung->fresh()->status);
    }

    public function test_ein_angefragtes_storno_wird_mit_erledigt(): void
    {
        [$bestellung] = $this->bestellungMit(Order::STATUS_CANCELLATION_REQUESTED, 1);

        $this->dienst->alleStornieren($this->termin);

        // Wer schon storniert haben wollte, soll nicht wegen der Absage
        // haengen bleiben.
        $this->assertSame(Order::STATUS_CANCELLED, $bestellung->fresh()->status);
    }

    public function test_eine_bereits_stornierte_bleibt_unberuehrt_und_zaehlt_nicht(): void
    {
        [$bestellung] = $this->bestellungMit(Order::STATUS_CANCELLED, 1);

        $vorschau = $this->dienst->vorschau($this->termin);
        $ergebnis = $this->dienst->alleStornieren($this->termin);

        // Sonst stuende in der Rueckfrage eine Summe, die gar nicht mehr
        // zurueckgeht - und jemand erstattet zweimal.
        $this->assertSame(0, $vorschau['anzahl']);
        $this->assertSame(0, $ergebnis['storniert']);
    }

    public function test_unbezahlte_bestellungen_zaehlen_getrennt(): void
    {
        $this->bestellungMit(Order::STATUS_CONFIRMED, 1);
        $this->bestellungMit(Order::STATUS_PENDING, 2, zahlung: 'open');

        $vorschau = $this->dienst->vorschau($this->termin);

        // Bei der wartenden wurde nichts abgebucht: Sie gehoert nicht in die
        // Summe, aber der Mensch soll wissen, dass es sie gibt.
        $this->assertSame(1, $vorschau['anzahl']);
        $this->assertSame(10.0, $vorschau['summe']);
        $this->assertSame(1, $vorschau['offen']);
    }

    public function test_bestellungen_anderer_termine_bleiben_unberuehrt(): void
    {
        [$eigene] = $this->bestellungMit(Order::STATUS_CONFIRMED, 1);

        $andererTermin = $this->termin(1, ['name' => 'Anderer Abend']);
        $fremde = $this->bestellung($andererTermin);
        $fremde->update(['status' => Order::STATUS_CONFIRMED]);

        $this->dienst->alleStornieren($this->termin);

        $this->assertSame(Order::STATUS_CANCELLED, $eigene->fresh()->status);
        $this->assertSame(Order::STATUS_CONFIRMED, $fremde->fresh()->status);
    }

    public function test_ein_zweiter_lauf_storniert_nichts_mehr(): void
    {
        $this->bestellungMit(Order::STATUS_CONFIRMED, 1);

        $erster  = $this->dienst->alleStornieren($this->termin);
        $zweiter = $this->dienst->alleStornieren($this->termin);

        // Zweimal auf den Knopf zu kommen darf nicht zweimal Geld bewegen.
        $this->assertSame(1, $erster['storniert']);
        $this->assertSame(0, $zweiter['storniert']);
    }
}
