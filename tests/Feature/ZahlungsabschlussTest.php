<?php

namespace Platform\Reservation\Tests\Feature;

use Platform\Reservation\Contracts\MollieCredentialResolver;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\Order;
use Platform\Reservation\Models\Payment;
use Platform\Reservation\Services\MolliePaymentService;
use Platform\Reservation\Tests\Concerns\ErzeugtTermine;
use Platform\Reservation\Tests\TestCase;

/**
 * Der Zustandswechsel einer Bestellung passiert GENAU EINMAL.
 *
 * Zwei Wege führen dorthin und kommen fast gleichzeitig an: der Mollie-Webhook
 * und die Rückkehrseite, auf der der Gast landet und die pollt. Vorher las
 * beides den Status ungesperrt – beide sahen „ausstehend", beide bestätigten,
 * beide schickten eine Bestellbestätigung. Der Gast bekam zwei Mails.
 *
 * Geprüft wird zustandUebernehmen() und nicht syncFromMollie(): Letzteres holt
 * den Status beim echten Mollie ab. Der Wechsel selbst ist das, woran der
 * Fehler hing.
 */
class ZahlungsabschlussTest extends TestCase
{
    use ErzeugtTermine;

    private MolliePaymentService $dienst;

    protected function setUp(): void
    {
        parent::setUp();

        // Der Zugangsdaten-Löser wird für den geprüften Weg nicht gebraucht,
        // der Konstruktor verlangt ihn aber.
        $this->dienst = new MolliePaymentService(
            new class implements MollieCredentialResolver {
                public function forTeam(int $teamId): ?\Platform\Reservation\Support\MollieCredentials
                {
                    return null;
                }
            }
        );
    }

    /** Bestellung mit einer wartenden Buchung auf einem Tisch. */
    private function wartendeBestellung(): array
    {
        $termin = $this->termin(1);
        $plan   = $this->raum('Saal', [4]);
        $this->haengeRaumAn($termin, $plan);

        $bestellung = $this->bestellung($termin);
        $buchung    = $this->buchung($this->pause($termin, 1), $this->tisch($plan), 2, $bestellung);

        Payment::create([
            'order_id'  => $bestellung->id,
            'mollie_id' => 'tr_test',
            'amount'    => 19.00,
            'currency'  => 'EUR',
            'status'    => 'open',
        ]);

        return [$bestellung->fresh(), $buchung];
    }

    public function test_bezahlt_bestaetigt_bestellung_und_buchung(): void
    {
        [$bestellung, $buchung] = $this->wartendeBestellung();

        $bestaetigt = $this->dienst->zustandUebernehmen($bestellung, bezahlt: true, fehlgeschlagen: false, method: 'ideal');

        $this->assertTrue($bestaetigt);
        $this->assertSame(Order::STATUS_CONFIRMED, $bestellung->fresh()->status);
        $this->assertSame(Booking::STATUS_CONFIRMED, $buchung->fresh()->status);
        $this->assertSame('ideal', $buchung->fresh()->payment_method);
    }

    public function test_der_zweite_aufruf_bestaetigt_nicht_noch_einmal(): void
    {
        [$bestellung] = $this->wartendeBestellung();

        $erster  = $this->dienst->zustandUebernehmen($bestellung, bezahlt: true, fehlgeschlagen: false);
        $zweiter = $this->dienst->zustandUebernehmen($bestellung, bezahlt: true, fehlgeschlagen: false);

        // Genau daran haengt die Mail: Nur wer TRUE bekommt, verschickt sie.
        // Vorher sagten beide Aufrufe ja, und der Gast bekam zwei.
        $this->assertTrue($erster);
        $this->assertFalse($zweiter);
    }

    public function test_fehlschlag_storniert_und_gibt_den_platz_frei(): void
    {
        [$bestellung, $buchung] = $this->wartendeBestellung();

        $bestaetigt = $this->dienst->zustandUebernehmen($bestellung, bezahlt: false, fehlgeschlagen: true);

        // Storniert schickt keine Bestellbestaetigung.
        $this->assertFalse($bestaetigt);
        $this->assertSame(Order::STATUS_CANCELLED, $bestellung->fresh()->status);
        $this->assertSame(Booking::STATUS_CANCELLED, $buchung->fresh()->status);
    }

    public function test_offen_laesst_alles_stehen(): void
    {
        [$bestellung, $buchung] = $this->wartendeBestellung();

        // Weder bezahlt noch fehlgeschlagen: Die Rueckkehrseite pollt weiter.
        $this->dienst->zustandUebernehmen($bestellung, bezahlt: false, fehlgeschlagen: false);

        $this->assertSame(Order::STATUS_PENDING, $bestellung->fresh()->status);
        $this->assertSame(Booking::STATUS_PENDING, $buchung->fresh()->status);
    }

    public function test_eine_bereits_stornierte_bestellung_wird_nicht_nachtraeglich_bestaetigt(): void
    {
        [$bestellung, $buchung] = $this->wartendeBestellung();
        $this->dienst->zustandUebernehmen($bestellung, bezahlt: false, fehlgeschlagen: true);

        // Kommt danach doch noch eine Zahlungsmeldung, darf sie den Platz nicht
        // wieder an sich reissen - er kann inzwischen anderweitig vergeben sein.
        $bestaetigt = $this->dienst->zustandUebernehmen($bestellung, bezahlt: true, fehlgeschlagen: false);

        $this->assertFalse($bestaetigt);
        $this->assertSame(Order::STATUS_CANCELLED, $bestellung->fresh()->status);
        $this->assertSame(Booking::STATUS_CANCELLED, $buchung->fresh()->status);
    }
}
