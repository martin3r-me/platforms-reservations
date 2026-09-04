<?php

namespace Platform\Reservation\Tests\Feature;

use Platform\Reservation\Contracts\MollieCredentialResolver;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\Order;
use Platform\Reservation\Models\Payment;
use Platform\Reservation\Services\OrderCancellationService;
use Platform\Reservation\Support\MollieCredentials;
use Platform\Reservation\Tests\Concerns\ErzeugtTermine;
use Platform\Reservation\Tests\TestCase;

/**
 * Geld, das zurückgeht, ohne dass PausePlus es ausgelöst hat.
 *
 * Jemand erstattet im Mollie-Dashboard, oder die Bank bucht zurück. Das fällt
 * sonst durch jedes Raster: Eine erstattete Zahlung bleibt bei Mollie auf
 * „paid", zurück geht sie nur über amountRefunded. Die Buchung bliebe
 * bestätigt, der Gast auf dem Laufzettel, der Betrag im Umsatz – obwohl das
 * Geld weg ist.
 */
class ErstattungAusMollieTest extends TestCase
{
    use ErzeugtTermine;

    private OrderCancellationService $dienst;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(MollieCredentialResolver::class, fn () => new class implements MollieCredentialResolver {
            public function forTeam(int $teamId): ?MollieCredentials
            {
                return null;
            }
        });

        $this->dienst = app(OrderCancellationService::class);
    }

    /** @return array{0: Order, 1: Payment, 2: Booking} */
    private function bezahlteBestellung(float $betrag = 24.0): array
    {
        $termin = $this->termin(1);
        $plan   = $this->raum('Saal', [4]);
        $this->haengeRaumAn($termin, $plan);

        $bestellung = $this->bestellung($termin);
        $bestellung->update(['status' => Order::STATUS_CONFIRMED]);

        $buchung = $this->buchung(
            $this->pause($termin, 1),
            $this->tisch($plan),
            2,
            $bestellung,
            Booking::STATUS_CONFIRMED,
        );

        $zahlung = Payment::create([
            'order_id'  => $bestellung->id,
            'mollie_id' => 'tr_test',
            'amount'    => $betrag,
            'currency'  => 'EUR',
            'status'    => 'paid',
            'paid_at'   => now(),
        ]);

        return [$bestellung->fresh(), $zahlung->fresh(), $buchung];
    }

    public function test_ohne_rueckfluss_passiert_nichts(): void
    {
        [$bestellung, $zahlung] = $this->bezahlteBestellung();

        $this->assertSame('keine', $this->dienst->erstattungAusMollie($bestellung, $zahlung, 0.0, 0.0));
        $this->assertSame(Order::STATUS_CONFIRMED, $bestellung->fresh()->status);
    }

    public function test_volle_erstattung_storniert_und_gibt_den_platz_frei(): void
    {
        [$bestellung, $zahlung, $buchung] = $this->bezahlteBestellung(24.0);

        $ergebnis = $this->dienst->erstattungAusMollie($bestellung, $zahlung, 24.0, 0.0);

        $this->assertSame('voll', $ergebnis);
        $this->assertSame(Order::STATUS_CANCELLED, $bestellung->fresh()->status);
        $this->assertSame(Booking::STATUS_CANCELLED, $buchung->fresh()->status);
        $this->assertSame('refunded', $zahlung->fresh()->status);
        $this->assertNotNull($zahlung->fresh()->refunded_at);
    }

    public function test_eine_rueckbelastung_wird_als_solche_festgehalten(): void
    {
        [$bestellung, $zahlung, $buchung] = $this->bezahlteBestellung(24.0);

        $ergebnis = $this->dienst->erstattungAusMollie($bestellung, $zahlung, 0.0, 24.0);

        // Eigener Status: Eine Rueckbuchung der Bank ist etwas anderes als eine
        // Erstattung durch das Haus - beim Nachrechnen will man das trennen.
        $this->assertSame('rueckbelastung', $ergebnis);
        $this->assertSame('charged_back', $zahlung->fresh()->status);
        $this->assertSame(Booking::STATUS_CANCELLED, $buchung->fresh()->status);
    }

    public function test_eine_teilerstattung_storniert_nicht(): void
    {
        [$bestellung, $zahlung, $buchung] = $this->bezahlteBestellung(24.0);

        $ergebnis = $this->dienst->erstattungAusMollie($bestellung, $zahlung, 8.0, 0.0);

        // Welche Position gemeint war, weiss niemand. Wer 8 von 24 Euro
        // zurueckbekommt, soll nicht vor einem leeren Tisch stehen.
        $this->assertSame('teilweise', $ergebnis);
        $this->assertSame(Order::STATUS_CONFIRMED, $bestellung->fresh()->status);
        $this->assertSame(Booking::STATUS_CONFIRMED, $buchung->fresh()->status);
        // Aber festgehalten, damit im Backoffice jemand hinsehen kann.
        $this->assertSame('8.00', (string) $zahlung->fresh()->refunded_amount);
    }

    public function test_eine_teilerstattung_sperrt_den_rest_nicht(): void
    {
        [$bestellung, $zahlung] = $this->bezahlteBestellung(24.0);

        $this->dienst->erstattungAusMollie($bestellung, $zahlung, 8.0, 0.0);

        // refunded_at ist die Sperre gegen eine zweite Erstattung. Nach einer
        // TEILerstattung muss der Rest noch erstattbar bleiben.
        $this->assertNull($zahlung->fresh()->refunded_at);
    }

    public function test_centbruchteile_gelten_als_voll(): void
    {
        [$bestellung, $zahlung] = $this->bezahlteBestellung(24.0);

        // Mollie rechnet in Strings; ein Vergleich auf Gleichheit ginge
        // irgendwann schief.
        $ergebnis = $this->dienst->erstattungAusMollie($bestellung, $zahlung, 23.999, 0.0);

        $this->assertSame('voll', $ergebnis);
    }

    public function test_ein_zweiter_lauf_bewegt_nichts_mehr(): void
    {
        [$bestellung, $zahlung] = $this->bezahlteBestellung(24.0);

        $this->dienst->erstattungAusMollie($bestellung, $zahlung, 24.0, 0.0);
        $zweiter = $this->dienst->erstattungAusMollie($bestellung->fresh(), $zahlung->fresh(), 24.0, 0.0);

        // Mollie schickt denselben Webhook mehrfach. Storniert bleibt
        // storniert, und eine zweite Mail geht nicht raus.
        $this->assertSame('voll', $zweiter);
        $this->assertSame(Order::STATUS_CANCELLED, $bestellung->fresh()->status);
    }
}
