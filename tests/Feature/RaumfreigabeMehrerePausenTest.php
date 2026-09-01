<?php

namespace Platform\Reservation\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Services\RoomReleaseService;
use Platform\Reservation\Services\SeatAvailabilityService;
use Platform\Reservation\Tests\Concerns\ErzeugtTermine;
use Platform\Reservation\Tests\TestCase;

/**
 * Was die Tischbindung für MEHRERE RÄUME bedeutet.
 *
 * Die sequenzielle Freigabe ("Raum 2 öffnet, sobald Raum 1 zu X % gefüllt ist")
 * rechnet über SeatAvailabilityService. Sie erbt die Betriebsart damit
 * automatisch – und genau das soll dieser Test festhalten, weil es sonst beim
 * nächsten Umbau als Zufall durchgehen könnte.
 *
 * Siehe docs/PLAN-mehrere-pausen.md, Abschnitt E.
 */
class RaumfreigabeMehrerePausenTest extends TestCase
{
    use ErzeugtTermine;

    protected function freigabe(): RoomReleaseService
    {
        return new RoomReleaseService(new SeatAvailabilityService);
    }

    /** Termin mit zwei Räumen in Reihenfolge; der erste ist nach 4 Plätzen voll. */
    protected function terminMitZweiRaeumen(string $bindung): Event
    {
        $termin = $this->termin(pausen: 2, attribute: [
            'table_binding'     => $bindung,
            'room_release_mode' => Event::RELEASE_SEQUENTIAL,
        ]);

        $this->haengeRaumAn($termin, $this->raum('Terrasse', [4]), reihenfolge: 0, schwelle: 100);
        $this->haengeRaumAn($termin, $this->raum('ROSSINI', [4]), reihenfolge: 1, schwelle: 100);

        return $termin->fresh();
    }

    #[Test]
    public function bei_bindung_an_den_termin_sind_die_offenen_raeume_in_jeder_pause_dieselben(): void
    {
        $termin = $this->terminMitZweiRaeumen(Event::BINDING_EVENT);
        $ersterRaum = $termin->eventRooms()->orderBy('sort_order')->first();

        // Die Terrasse wird in der ERSTEN Pause vollgebucht – in der zweiten
        // bestellt niemand etwas.
        $this->buchung($this->pause($termin, 1), $this->tisch($ersterRaum->floorPlan), 4, $this->bestellung($termin));

        // Der Tisch gehört den Gästen trotzdem den ganzen Abend. Also ist die
        // Terrasse auch in Pause 2 voll, und ROSSINI ist in BEIDEN Pausen offen.
        $this->assertCount(2, $this->freigabe()->openRooms($termin, $this->pause($termin, 1)));
        $this->assertCount(2, $this->freigabe()->openRooms($termin, $this->pause($termin, 2)));
    }

    #[Test]
    public function bei_bindung_an_die_pause_kann_derselbe_raum_in_pause_1_offen_und_in_pause_2_zu_sein(): void
    {
        $termin = $this->terminMitZweiRaeumen(Event::BINDING_SLOT);
        $ersterRaum = $termin->eventRooms()->orderBy('sort_order')->first();

        $this->buchung($this->pause($termin, 1), $this->tisch($ersterRaum->floorPlan), 4, $this->bestellung($termin));

        // Derselbe Bestand: In Pause 1 ist die Terrasse voll, ROSSINI öffnet.
        // In Pause 2 ist die Terrasse leer, ROSSINI bleibt zu. Dieselbe
        // Veranstaltung, zwei verschiedene Raumlisten - der Gast muss das je
        // Pause gezeigt bekommen.
        $this->assertCount(2, $this->freigabe()->openRooms($termin, $this->pause($termin, 1)));
        $this->assertCount(1, $this->freigabe()->openRooms($termin, $this->pause($termin, 2)));
    }

    #[Test]
    public function bei_einer_einzigen_pause_aendert_die_bindung_an_der_freigabe_nichts(): void
    {
        foreach ([Event::BINDING_EVENT, Event::BINDING_SLOT] as $bindung) {
            $termin = $this->termin(pausen: 1, attribute: [
                'table_binding'     => $bindung,
                'room_release_mode' => Event::RELEASE_SEQUENTIAL,
            ]);

            $ersterPlan = $this->raum('Terrasse-' . $bindung, [4]);
            $this->haengeRaumAn($termin, $ersterPlan, reihenfolge: 0, schwelle: 100);
            $this->haengeRaumAn($termin, $this->raum('ROSSINI-' . $bindung, [4]), reihenfolge: 1, schwelle: 100);

            $pause = $this->pause($termin, 1);

            $this->assertCount(1, $this->freigabe()->openRooms($termin->fresh(), $pause), "Bindung: {$bindung}");

            $this->buchung($pause, $this->tisch($ersterPlan), 4, $this->bestellung($termin));

            $this->assertCount(2, $this->freigabe()->openRooms($termin->fresh(), $pause), "Bindung: {$bindung}");
        }
    }
}
