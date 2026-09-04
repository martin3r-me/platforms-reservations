<?php

namespace Platform\Reservation\Tests\Feature;

use Platform\Reservation\Console\Commands\HaengendeBestellungenAufraeumen;
use Platform\Reservation\Contracts\MollieCredentialResolver;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\Order;
use Platform\Reservation\Models\Payment;
use Platform\Reservation\Services\MolliePaymentService;
use Platform\Reservation\Support\MollieCredentials;
use Platform\Reservation\Tests\Concerns\ErzeugtTermine;
use Platform\Reservation\Tests\TestCase;

/**
 * Das Netz unter dem Bezahlvorgang.
 *
 * Bricht ein Gast bei Mollie ab, bleibt die Buchung „ausstehend" und der Platz
 * belegt. Normalerweise heilt sich das über Mollies Verfalls-Meldung. Bleibt
 * die aus, war der Platz bis heute dauerhaft weg – es gab im ganzen Modul
 * keinen geplanten Lauf, der ihn zurückgeholt hätte.
 */
class HaengendeBestellungenTest extends TestCase
{
    use ErzeugtTermine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(MollieCredentialResolver::class, fn () => new class implements MollieCredentialResolver {
            public function forTeam(int $teamId): ?MollieCredentials
            {
                return null;
            }
        });

        // Nur das Kommando anmelden, nicht den ganzen ServiceProvider - der
        // zieht Routen, Ansichten und die Plattform nach sich, und darum geht
        // es hier nicht (siehe TestCase).
        $this->app->make(\Illuminate\Contracts\Console\Kernel::class)
            ->registerCommand($this->app->make(HaengendeBestellungenAufraeumen::class));
    }

    /**
     * Wartende Bestellung mit Buchung – $alter in Stunden, $zahlung als
     * Mollie-Status (null = gar kein Zahlungssatz).
     */
    private function bestellungMitAlter(int $alter, ?string $zahlung = 'open'): array
    {
        $termin = $this->termin(1);
        $plan   = $this->raum('Saal' . $alter . ($zahlung ?? 'bar'), [4]);
        $this->haengeRaumAn($termin, $plan);

        $bestellung = $this->bestellung($termin);
        $buchung    = $this->buchung($this->pause($termin, 1), $this->tisch($plan), 2, $bestellung);

        // created_at direkt setzen: Der Zeitpunkt ist der ganze Punkt.
        $bestellung->forceFill(['created_at' => now()->subHours($alter)])->save();

        if ($zahlung !== null) {
            Payment::create([
                'order_id'  => $bestellung->id,
                'mollie_id' => 'tr_' . $bestellung->id,
                'amount'    => 19.00,
                'currency'  => 'EUR',
                'status'    => $zahlung,
            ]);
        }

        return [$bestellung->fresh(), $buchung];
    }

    private function auffraeumen(array $optionen = []): void
    {
        $this->artisan('reservation:bestellungen-aufraeumen', $optionen);
    }

    public function test_eine_alte_wartende_bestellung_wird_storniert(): void
    {
        [$bestellung, $buchung] = $this->bestellungMitAlter(30);

        $this->auffraeumen();

        $this->assertSame(Order::STATUS_CANCELLED, $bestellung->fresh()->status);
        // Der eigentliche Zweck: Der Platz ist wieder zu haben.
        $this->assertSame(Booking::STATUS_CANCELLED, $buchung->fresh()->status);
    }

    public function test_eine_frische_bestellung_bleibt_unangetastet(): void
    {
        [$bestellung, $buchung] = $this->bestellungMitAlter(2);

        // Der Gast koennte noch im Bezahlfenster stehen. Ihm den Platz unter
        // den Fuessen wegzuziehen waere schlimmer als ein blockierter Tisch.
        $this->auffraeumen();

        $this->assertSame(Order::STATUS_PENDING, $bestellung->fresh()->status);
        $this->assertSame(Booking::STATUS_PENDING, $buchung->fresh()->status);
    }

    public function test_eine_bestellung_ohne_zahlung_bleibt_unangetastet(): void
    {
        [$bestellung, $buchung] = $this->bestellungMitAlter(72, zahlung: null);

        // Kein Zahlungssatz heisst: Das ist kein abgebrochener Online-Kauf,
        // sondern Barzahlung oder eine Buchung aus dem Backoffice. Die wird vor
        // Ort abgerechnet und darf nicht wegstorniert werden.
        $this->auffraeumen();

        $this->assertSame(Order::STATUS_PENDING, $bestellung->fresh()->status);
        $this->assertSame(Booking::STATUS_PENDING, $buchung->fresh()->status);
    }

    public function test_eine_bezahlte_bestellung_bleibt_unangetastet(): void
    {
        [$bestellung] = $this->bestellungMitAlter(48, zahlung: 'paid');

        // Bezahlt und trotzdem ausstehend heisst: Der Webhook hat den Status
        // noch nicht nachgezogen. Diese Bestellung zu stornieren hiesse, einen
        // zahlenden Gast auszuladen.
        $this->auffraeumen();

        $this->assertSame(Order::STATUS_PENDING, $bestellung->fresh()->status);
    }

    public function test_die_frist_laesst_sich_einstellen(): void
    {
        [$bestellung] = $this->bestellungMitAlter(5);

        $this->auffraeumen(['--stunden' => 3]);

        $this->assertSame(Order::STATUS_CANCELLED, $bestellung->fresh()->status);
    }

    public function test_die_probe_aendert_nichts(): void
    {
        [$bestellung] = $this->bestellungMitAlter(30);

        $this->auffraeumen(['--dry-run' => true]);

        $this->assertSame(Order::STATUS_PENDING, $bestellung->fresh()->status);
    }
}
