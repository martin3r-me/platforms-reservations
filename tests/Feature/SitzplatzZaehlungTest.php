<?php

namespace Platform\Reservation\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Services\SeatAvailabilityService;
use Platform\Reservation\Tests\Concerns\ErzeugtTermine;
use Platform\Reservation\Tests\TestCase;

/**
 * Die Kapazitätsrechnung über mehrere Pausen.
 *
 * Warum ausgerechnet hier die erste eingecheckte Testbasis des Moduls entsteht:
 * Ein Denkfehler an dieser Stelle meldet sich nicht. Er zeigt sich als ein
 * Tisch, der zu früh voll ist – und den sieht niemand, weil niemand weiß, dass
 * dort noch etwas hätte gebucht werden können.
 *
 * Siehe docs-intern/PLAN-mehrere-pausen.md, Abschnitte D und J.
 */
class SitzplatzZaehlungTest extends TestCase
{
    use ErzeugtTermine;

    protected function dienst(): SeatAvailabilityService
    {
        return new SeatAvailabilityService;
    }

    #[Test]
    public function eine_partei_in_beiden_pausen_belegt_den_tisch_nur_einmal(): void
    {
        $termin = $this->termin(pausen: 2, attribute: ['table_binding' => Event::BINDING_EVENT]);
        $plan   = $this->raum('Terrasse', [4]);
        $tisch  = $this->tisch($plan);

        // Ein Gast, zwei Personen, bestellt in beiden Pausen – EINE Bestellung.
        $bestellung = $this->bestellung($termin);
        $this->buchung($this->pause($termin, 1), $tisch, 2, $bestellung);
        $this->buchung($this->pause($termin, 2), $tisch, 2, $bestellung);

        // Summiert wären es 4 – der Gast hätte sich mit seiner eigenen zweiten
        // Bestellung den Tisch vollgemacht.
        $this->assertSame(2, $this->dienst()->bookedSeatsForTable($tisch, $this->pause($termin, 1)));
        $this->assertSame(2, $this->dienst()->remainingSeats($tisch, $this->pause($termin, 2)));
    }

    #[Test]
    public function zwei_parteien_in_verschiedenen_pausen_belegen_den_tisch_zusammen(): void
    {
        $termin = $this->termin(pausen: 2, attribute: ['table_binding' => Event::BINDING_EVENT]);
        $plan   = $this->raum('Terrasse', [4]);
        $tisch  = $this->tisch($plan);

        $this->buchung($this->pause($termin, 1), $tisch, 2, $this->bestellung($termin));
        $this->buchung($this->pause($termin, 2), $tisch, 2, $this->bestellung($termin));

        // Zwei verschiedene Parteien: Der Tisch gehört an diesem Abend beiden,
        // also ist er voll.
        $this->assertSame(4, $this->dienst()->bookedSeatsForTable($tisch, $this->pause($termin, 1)));
        $this->assertFalse($this->dienst()->canSeat($tisch, $this->pause($termin, 2), 1));
    }

    #[Test]
    public function bei_bindung_an_die_pause_zaehlt_jede_pause_fuer_sich(): void
    {
        $termin = $this->termin(pausen: 2, attribute: ['table_binding' => Event::BINDING_SLOT]);
        $plan   = $this->raum('Terrasse', [4]);
        $tisch  = $this->tisch($plan);

        $this->buchung($this->pause($termin, 1), $tisch, 2, $this->bestellung($termin));
        $this->buchung($this->pause($termin, 2), $tisch, 2, $this->bestellung($termin));

        // Derselbe Bestand, andere Betriebsart: Der Saal wird zwischen den
        // Pausen geräumt, also sind in jeder Pause zwei Plätze frei.
        $this->assertSame(2, $this->dienst()->bookedSeatsForTable($tisch, $this->pause($termin, 1)));
        $this->assertSame(2, $this->dienst()->remainingSeats($tisch, $this->pause($termin, 2)));
    }

    #[Test]
    public function die_groesste_sitzung_einer_partei_entscheidet(): void
    {
        $termin = $this->termin(pausen: 2, attribute: ['table_binding' => Event::BINDING_EVENT]);
        $plan   = $this->raum('Terrasse', [6]);
        $tisch  = $this->tisch($plan);

        // Zu viert in der ersten Pause, zu zweit in der zweiten. Der Tisch muss
        // die größere Sitzung tragen – über den Shop entsteht das nicht, über
        // die manuelle Buchung schon.
        $bestellung = $this->bestellung($termin);
        $this->buchung($this->pause($termin, 1), $tisch, 4, $bestellung);
        $this->buchung($this->pause($termin, 2), $tisch, 2, $bestellung);

        $this->assertSame(4, $this->dienst()->bookedSeatsForTable($tisch, $this->pause($termin, 2)));
    }

    #[Test]
    public function eine_buchung_ohne_bestellung_ist_ihre_eigene_partei(): void
    {
        $termin = $this->termin(pausen: 2, attribute: ['table_binding' => Event::BINDING_EVENT]);
        $plan   = $this->raum('Terrasse', [4]);
        $tisch  = $this->tisch($plan);

        // bookings.order_id ist nullable – Altbestand von vor der Order-Klammer.
        // Ohne Bestellung gibt es nichts, was zwei Buchungen verbindet; sie als
        // eine Partei zu lesen würde Plätze verschenken, die belegt sind.
        $this->buchung($this->pause($termin, 1), $tisch, 2);
        $this->buchung($this->pause($termin, 2), $tisch, 2);

        $this->assertSame(4, $this->dienst()->bookedSeatsForTable($tisch, $this->pause($termin, 1)));
    }

    #[Test]
    public function stornierte_buchungen_und_no_shows_zaehlen_nicht(): void
    {
        $termin = $this->termin(pausen: 2, attribute: ['table_binding' => Event::BINDING_EVENT]);
        $plan   = $this->raum('Terrasse', [4]);
        $tisch  = $this->tisch($plan);

        $this->buchung($this->pause($termin, 1), $tisch, 2, null, Booking::STATUS_CANCELLED);
        $this->buchung($this->pause($termin, 2), $tisch, 2, null, Booking::STATUS_NO_SHOW);

        $this->assertSame(0, $this->dienst()->bookedSeatsForTable($tisch, $this->pause($termin, 1)));
    }

    #[Test]
    public function bei_einer_einzigen_pause_rechnen_beide_betriebsarten_gleich(): void
    {
        // Der Test, der den Betrieb absichert: Alle Termine bei Culinaria haben
        // genau eine Pause. Ändert sich hier eine Zahl, ändert sich sie live.
        foreach ([Event::BINDING_EVENT, Event::BINDING_SLOT] as $bindung) {
            $termin = $this->termin(pausen: 1, attribute: ['table_binding' => $bindung]);
            $plan   = $this->raum('Terrasse-' . $bindung, [6]);
            $tisch  = $this->tisch($plan);
            $pause  = $this->pause($termin, 1);

            // Zwei Parteien am selben Tisch in derselben Pause: getrennt gezählt.
            $this->buchung($pause, $tisch, 2, $this->bestellung($termin));

            // Und zwei Buchungen DERSELBEN Partei in derselben Pause: innerhalb
            // einer Pause wird summiert, nicht zusammengefasst. Sonst würde die
            // Parteien-Regel dem einpausigen Normalfall Plätze schenken.
            $eine = $this->bestellung($termin);
            $this->buchung($pause, $tisch, 1, $eine);
            $this->buchung($pause, $tisch, 1, $eine);

            $this->assertSame(4, $this->dienst()->bookedSeatsForTable($tisch, $pause), "Bindung: {$bindung}");
        }
    }

    #[Test]
    public function der_termin_schlaegt_die_vorgabe_des_teams(): void
    {
        $this->teamVorgabe(Event::BINDING_SLOT);

        $ohneEigenen = $this->termin(pausen: 2);
        $mitEigenem  = $this->termin(pausen: 2, attribute: ['table_binding' => Event::BINDING_EVENT]);

        $this->assertSame(Event::BINDING_SLOT, $ohneEigenen->tableBinding());
        $this->assertSame(Event::BINDING_EVENT, $mitEigenem->tableBinding());
    }

    #[Test]
    public function ohne_jede_angabe_gilt_die_strengere_lesart(): void
    {
        // Weder am Termin noch am Team etwas hinterlegt: Der Tisch gehört dem
        // Gast den ganzen Abend. Die Lesart, die nie einen Platz zu viel verkauft.
        $this->assertSame(Event::BINDING_EVENT, $this->termin(pausen: 2)->tableBinding());
    }

    #[Test]
    public function belegt_und_bestellt_gehen_bei_bindung_an_den_termin_auseinander(): void
    {
        $termin = $this->termin(pausen: 2, attribute: ['table_binding' => Event::BINDING_EVENT]);
        $plan   = $this->raum('Terrasse', [4]);
        $tisch  = $this->tisch($plan);

        // Bestellt wird nur in der ERSTEN Pause.
        $this->buchung($this->pause($termin, 1), $tisch, 2, $this->bestellung($termin));

        $zweite = $this->pause($termin, 2);

        // Der Tisch ist in Pause 2 belegt - die Gäste halten ihn vom Abend.
        $this->assertSame(2, $this->dienst()->bookedSeatsForTable($tisch, $zweite));

        // Aber bestellt hat dort niemand. Wer für diese Zahl kocht, kocht für
        // Leute, die nichts bestellt haben.
        $this->assertSame(0, (int) $this->dienst()->orderedSeatsByTable($plan, $zweite)->get($tisch->id, 0));
    }

    #[Test]
    public function die_warnung_nennt_die_tische_die_beim_umstellen_ueberbelegt_waeren(): void
    {
        // Der Termin läuft je Pause: Partei A sitzt in Pause 1 am Tisch,
        // Partei B in Pause 2 am selben Tisch. Getrennt gezählt passt beides.
        $termin = $this->termin(pausen: 2, attribute: ['table_binding' => Event::BINDING_SLOT]);
        $plan   = $this->raum('Terrasse', [2, 4]);
        $this->haengeRaumAn($termin, $plan);

        $this->buchung($this->pause($termin, 1), $this->tisch($plan, 1), 2, $this->bestellung($termin));
        $this->buchung($this->pause($termin, 2), $this->tisch($plan, 1), 2, $this->bestellung($termin));

        // Am zweiten Tisch sitzt nur eine Partei – der bleibt unauffällig.
        $this->buchung($this->pause($termin, 1), $this->tisch($plan, 2), 2, $this->bestellung($termin));

        $this->assertSame(
            ['Terrasse 1'],
            $this->dienst()->ueberbelegtBeiTerminbindung($termin->fresh())
        );
    }

    #[Test]
    public function bei_einer_einzigen_pause_gibt_es_beim_umstellen_nichts_zu_warnen(): void
    {
        $termin = $this->termin(pausen: 1, attribute: ['table_binding' => Event::BINDING_SLOT]);
        $plan   = $this->raum('Terrasse', [2]);
        $this->haengeRaumAn($termin, $plan);

        $this->buchung($this->pause($termin, 1), $this->tisch($plan), 2, $this->bestellung($termin));

        $this->assertSame([], $this->dienst()->ueberbelegtBeiTerminbindung($termin->fresh()));
    }

    #[Test]
    public function ein_teilbelegter_tisch_bleibt_fuer_grossgruppen_gesperrt(): void
    {
        $termin = $this->termin(pausen: 2, attribute: ['table_binding' => Event::BINDING_EVENT]);
        $plan   = $this->raum('Terrasse', [4]);
        $tisch  = $this->tisch($plan);

        // Weiche Kapazität lässt Großgruppen auf LEERE Tische. Bei Bindung an
        // den Termin heißt leer: in keiner Pause belegt. Wer in Pause 2 sitzt,
        // macht den Tisch auch für die Großgruppe in Pause 1 unbrauchbar.
        $this->buchung($this->pause($termin, 2), $tisch, 2, $this->bestellung($termin));

        $this->assertFalse(
            $this->dienst()->canSeat($tisch, $this->pause($termin, 1), 6, softCapacity: true, maxGroupEmptyTable: 10)
        );
    }
}
