<?php

namespace Platform\Reservation\Tests\Feature;

use Platform\Reservation\Exceptions\GuestOrderException;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\Order;
use Platform\Reservation\Contracts\MollieCredentialResolver;
use Platform\Reservation\Services\GuestOrderService;
use Platform\Reservation\Services\SettingsMollieCredentialResolver;
use Platform\Reservation\Tests\Concerns\ErzeugtTermine;
use Platform\Reservation\Tests\TestCase;

/**
 * Die Platzvergabe beim Anlegen einer Bestellung.
 *
 * Anlass ist eine Lücke, die im Betrieb nie auffallen würde: Die Prüfung „ist
 * an diesem Tisch noch Platz?" stand VOR der Transaktion, das Schreiben darin.
 * Zwischen beidem lag ein Zeitfenster, in dem ein zweiter Gast dasselbe tun
 * konnte – beide sahen freie Plätze, beide schrieben. Hinterher sind alle
 * Zahlen stimmig, nur der Tisch ist zu klein.
 *
 * Die Gleichzeitigkeit selbst lässt sich hier nicht nachstellen (SQLite im
 * Arbeitsspeicher, ein Prozess). Was sich prüfen lässt, ist das, was beim
 * Verschieben der Prüfung kaputtgehen konnte: dass sie überhaupt noch greift,
 * und dass ein Fehlschlag aus der Transaktion NICHTS zurücklässt.
 */
class PlatzvergabeTest extends TestCase
{
    use ErzeugtTermine;

    private GuestOrderService $dienst;

    protected function setUp(): void
    {
        parent::setUp();

        // Der Dienst zieht den MolliePaymentService, und der braucht einen
        // Resolver. Gebunden wird der sonst vom ServiceProvider, den diese
        // Testbasis bewusst nicht registriert. Aufgerufen wird Mollie hier nie -
        // der Backoffice-Weg legt keine Zahlung an -, das Binding ist nur nötig,
        // damit sich der Dienst überhaupt bauen lässt.
        $this->app->bind(MollieCredentialResolver::class, SettingsMollieCredentialResolver::class);

        $this->dienst = app(GuestOrderService::class);
    }

    public function test_eine_gruppe_passt_an_einen_freien_tisch(): void
    {
        [$termin, $pause, $tisch, $artikel] = $this->aufbau(kapazitaet: 4);

        $this->dienst->placeForStaff($termin, $this->gast(4), [[
            'slot_id'  => $pause->id,
            'table_id' => $tisch->id,
            'items'    => [$artikel->id => 1],
        ]]);

        $this->assertSame(1, Booking::count());
        $this->assertSame(4, (int) Booking::first()->guest_count);
    }

    public function test_der_tisch_nimmt_nicht_mehr_als_er_hat(): void
    {
        [$termin, $pause, $tisch, $artikel] = $this->aufbau(kapazitaet: 4);

        $this->dienst->placeForStaff($termin, $this->gast(3), [[
            'slot_id'  => $pause->id,
            'table_id' => $tisch->id,
            'items'    => [$artikel->id => 1],
        ]]);

        $this->erwarteFehler('TABLE_FULL', fn () => $this->dienst->placeForStaff($termin, $this->gast(2), [[
            'slot_id'  => $pause->id,
            'table_id' => $tisch->id,
            'items'    => [$artikel->id => 1],
        ]]));
    }

    public function test_ein_abgelehnter_platz_hinterlaesst_keine_halbe_bestellung(): void
    {
        [$termin, $pause, $tisch, $artikel] = $this->aufbau(kapazitaet: 2);

        $this->dienst->placeForStaff($termin, $this->gast(2), [[
            'slot_id'  => $pause->id,
            'table_id' => $tisch->id,
            'items'    => [$artikel->id => 1],
        ]]);

        $this->erwarteFehler('TABLE_FULL', fn () => $this->dienst->placeForStaff($termin, $this->gast(1), [[
            'slot_id'  => $pause->id,
            'table_id' => $tisch->id,
            'items'    => [$artikel->id => 1],
        ]]));

        // Der Kern der Umstellung: Die Prüfung liegt jetzt INNERHALB der
        // Transaktion. Eine Ausnahme von dort muss sie vollständig zurückrollen -
        // sonst bliebe bei jedem „Tisch voll" eine Bestellung ohne Buchung
        // stehen, und der Posteingang füllte sich mit Geistern.
        $this->assertSame(1, Order::count());
        $this->assertSame(1, Booking::count());
    }

    public function test_die_zweite_pause_kippt_die_erste_mit(): void
    {
        $termin = $this->termin(2);
        $plan   = $this->raum('Saal', [4, 2]);
        $this->haengeRaumAn($termin, $plan);
        $artikel = $this->artikel();

        $eins = $this->tisch($plan, 1);
        $zwei = $this->tisch($plan, 2);

        // Der zweite Tisch ist voll, der erste hätte Platz.
        $this->dienst->placeForStaff($termin, $this->gast(2), [[
            'slot_id'  => $this->pause($termin, 2)->id,
            'table_id' => $zwei->id,
            'items'    => [$artikel->id => 1],
        ]]);

        $vorher = Booking::count();

        // Eine Bestellung über BEIDE Pausen: Die erste ginge, die zweite nicht.
        $this->erwarteFehler('TABLE_FULL', fn () => $this->dienst->placeForStaff($termin, $this->gast(2), [
            ['slot_id' => $this->pause($termin, 1)->id, 'table_id' => $eins->id, 'items' => [$artikel->id => 1]],
            ['slot_id' => $this->pause($termin, 2)->id, 'table_id' => $zwei->id, 'items' => [$artikel->id => 1]],
        ]));

        // Entweder ganz oder gar nicht. Eine Bestellung, die nur die halbe
        // Pausenfolge bekommt, wäre schlimmer als eine abgelehnte: Der Gast
        // hätte bezahlt und säße in der zweiten Pause nirgends.
        $this->assertSame($vorher, Booking::count());
    }

    /** @return array{0: \Platform\Reservation\Models\Event, 1: \Platform\Reservation\Models\EventSlot, 2: \Platform\Reservation\Models\Table, 3: \Platform\Reservation\Models\MenuItem} */
    private function aufbau(int $kapazitaet): array
    {
        $termin = $this->termin(1);
        $plan   = $this->raum('Saal', [$kapazitaet]);
        $this->haengeRaumAn($termin, $plan);

        return [$termin, $this->pause($termin, 1), $this->tisch($plan), $this->artikel()];
    }

    private function gast(int $personen): array
    {
        return [
            'first_name' => 'Test',
            'last_name'  => 'Gast',
            'email'      => null,
            'phone'      => null,
            'count'      => $personen,
            'notes'      => null,
        ];
    }

    private function erwarteFehler(string $code, callable $tun): void
    {
        try {
            $tun();
        } catch (GuestOrderException $e) {
            $this->assertSame($code, $e->errorCode);

            return;
        }

        $this->fail('Erwartet wurde ' . $code . ', es kam kein Fehler.');
    }
}
