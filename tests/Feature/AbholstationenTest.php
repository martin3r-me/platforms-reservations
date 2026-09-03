<?php

namespace Platform\Reservation\Tests\Feature;

use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\PickupStation;
use Platform\Reservation\Services\FunctionSheetService;
use Platform\Reservation\Services\PickupCapacityService;
use Platform\Reservation\Services\SeatAvailabilityService;
use Platform\Reservation\Tests\Concerns\ErzeugtTermine;
use Platform\Reservation\Tests\TestCase;

/**
 * Das Fundament der Abholstationen (Etappe 1).
 *
 * Geprüft wird das, was später schwer zu reparieren wäre: dass eine Buchung
 * genau einen Zielort hat, dass eine Station in der Platzrechnung NICHT
 * auftaucht, und dass die Kapazität je Pause zählt statt über den Abend.
 */
class AbholstationenTest extends TestCase
{
    use ErzeugtTermine;

    private PickupCapacityService $kapazitaet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kapazitaet = new PickupCapacityService();
    }

    public function test_eine_buchung_hat_entweder_tisch_oder_station(): void
    {
        $termin = $this->termin(1);
        $pause  = $this->pause($termin, 1);
        $plan   = $this->raum('Saal', [4]);

        // Beides: eine Buchung an zwei Orten.
        $this->expectException(\LogicException::class);

        Booking::create([
            'team_id'           => $this->teamId,
            'event_id'          => $termin->id,
            'event_slot_id'     => $pause->id,
            'table_id'          => $this->tisch($plan)->id,
            'pickup_station_id' => $this->station()->id,
            'guest_name'        => 'Testgast',
            'guest_count'       => 2,
            'date'              => '2026-10-01',
            'status'            => Booking::STATUS_PENDING,
        ]);
    }

    public function test_eine_buchung_ohne_zielort_wird_abgewiesen(): void
    {
        $termin = $this->termin(1);
        $pause  = $this->pause($termin, 1);

        $this->expectException(\LogicException::class);

        Booking::create([
            'team_id'       => $this->teamId,
            'event_id'      => $termin->id,
            'event_slot_id' => $pause->id,
            'guest_name'    => 'Testgast',
            'guest_count'   => 2,
            'date'          => '2026-10-01',
            'status'        => Booking::STATUS_PENDING,
        ]);
    }

    public function test_eine_alte_buchung_bleibt_speicherbar_wenn_ihr_tisch_geloescht_wurde(): void
    {
        $termin  = $this->termin(1);
        $plan    = $this->raum('Saal', [4]);
        $buchung = $this->buchung($this->pause($termin, 1), $this->tisch($plan), 2);

        $this->tisch($plan)->delete();

        // nullOnDelete nimmt der Buchung den Tisch. Sie muss sich trotzdem noch
        // stornieren lassen - sonst waere ein geloeschter Tisch eine Buchung,
        // die niemand mehr anfassen kann.
        $buchung->refresh();
        $this->assertNull($buchung->table_id);

        $buchung->status = Booking::STATUS_CANCELLED;
        $buchung->save();

        $this->assertSame(Booking::STATUS_CANCELLED, $buchung->fresh()->status);
    }

    public function test_der_zielort_nennt_die_station_beim_namen(): void
    {
        $termin  = $this->termin(1);
        $station = $this->station('Foyer links');
        $buchung = $this->abholung($this->pause($termin, 1), $station, 3);

        $this->assertSame('station', $buchung->zielort()['art']);
        $this->assertSame('Foyer links', $buchung->zielortLabel());
        $this->assertFalse($buchung->zielortFehlt());
        $this->assertSame('station-' . $station->id, $buchung->zielortSchluessel());
    }

    public function test_eine_station_taucht_in_der_platzrechnung_nicht_auf(): void
    {
        $termin  = $this->termin(1);
        $pause   = $this->pause($termin, 1);
        $plan    = $this->raum('Saal', [4]);
        $tisch   = $this->tisch($plan);
        $station = $this->station();

        $this->buchung($pause, $tisch, 2);
        $this->abholung($pause, $station, 3);

        // Der wichtigste Test dieser Etappe: Die drei Abholer duerfen den Tisch
        // nicht voller machen. Genau das ist der Grund, warum die Station eine
        // eigene Tabelle bekommen hat und kein Schalter am Tisch ist.
        $this->assertSame(2, (new SeatAvailabilityService())->bookedSeatsForTable($tisch, $pause));
    }

    public function test_die_kapazitaet_zaehlt_je_pause_nicht_ueber_den_abend(): void
    {
        $termin  = $this->termin(2);
        $eins    = $this->pause($termin, 1);
        $zwei    = $this->pause($termin, 2);
        $station = $this->station('Foyer', grenze: 10);

        $zuordnung = $this->haengeStationAn($termin, $station, [$eins, $zwei]);

        $this->abholung($eins, $station, 8);

        // 10 in Pause 1 und 10 in Pause 2 sind zusammen 20, nicht 10.
        $this->assertSame(2, $this->kapazitaet->frei($zuordnung, $eins));
        $this->assertSame(10, $this->kapazitaet->frei($zuordnung, $zwei));
    }

    public function test_ohne_obergrenze_wird_nichts_geprueft(): void
    {
        $termin    = $this->termin(1);
        $pause     = $this->pause($termin, 1);
        $station   = $this->station('Foyer');
        $zuordnung = $this->haengeStationAn($termin, $station, [$pause]);

        $this->abholung($pause, $station, 500);

        // null heisst „unbegrenzt", nicht „null Plaetze".
        $this->assertNull($this->kapazitaet->frei($zuordnung, $pause));
        $this->assertTrue($this->kapazitaet->passt($zuordnung, $pause, 400));
    }

    public function test_die_grenze_am_termin_schlaegt_die_der_station(): void
    {
        $termin    = $this->termin(1);
        $pause     = $this->pause($termin, 1);
        $station   = $this->station('Foyer', grenze: 100);
        $zuordnung = $this->haengeStationAn($termin, $station, [$pause], grenze: 20);

        $this->assertSame(20, $zuordnung->grenzeJePause());
        $this->assertSame(20, $this->kapazitaet->frei($zuordnung, $pause));
    }

    public function test_stornierte_abholungen_zaehlen_nicht_mit(): void
    {
        $termin    = $this->termin(1);
        $pause     = $this->pause($termin, 1);
        $station   = $this->station('Foyer', grenze: 10);
        $zuordnung = $this->haengeStationAn($termin, $station, [$pause]);

        $this->abholung($pause, $station, 4);
        $this->abholung($pause, $station, 3, Booking::STATUS_CANCELLED);
        $this->abholung($pause, $station, 2, Booking::STATUS_NO_SHOW);

        // Dieselbe Regel wie am Tisch. Eine zweite liefe frueher oder spaeter
        // auseinander, und dann stuende im Backoffice eine andere Zahl als beim
        // Gast.
        $this->assertSame(4, $this->kapazitaet->belegt($zuordnung, $pause));
    }

    public function test_eine_station_ohne_diese_pause_ist_nicht_buchbar(): void
    {
        $termin    = $this->termin(2);
        $eins      = $this->pause($termin, 1);
        $zwei      = $this->pause($termin, 2);
        $station   = $this->station('Foyer');
        $zuordnung = $this->haengeStationAn($termin, $station, [$eins]);

        $this->assertTrue($this->kapazitaet->buchbar($zuordnung, $eins, 2));

        // Nicht „voll", sondern gar nicht zur Wahl.
        $this->assertFalse($this->kapazitaet->buchbar($zuordnung, $zwei, 2));
    }

    public function test_eine_abgeschaltete_station_ist_nicht_buchbar(): void
    {
        $termin    = $this->termin(1);
        $pause     = $this->pause($termin, 1);
        $station   = $this->station('Foyer');
        $zuordnung = $this->haengeStationAn($termin, $station, [$pause]);

        $station->update(['is_active' => false]);

        $this->assertFalse($this->kapazitaet->buchbar($zuordnung->fresh(['slots', 'station']), $pause, 2));
    }

    public function test_die_station_ueberlebt_das_loeschen_ihres_tischplans(): void
    {
        $plan    = $this->raum('Saal', [4]);
        $station = $this->station('Rang 1 Bar');
        $station->update(['floor_plan_id' => $plan->id, 'x_pct' => 0.5, 'y_pct' => 0.5, 'w_pct' => 0.1, 'h_pct' => 0.1]);

        $this->assertTrue($station->fresh()->hatPosition());

        $plan->delete();

        // Sie gehoert dem Venue, nicht dem Raum: Sie verliert ihre Position,
        // nicht ihre Existenz.
        $station->refresh();
        $this->assertNotNull($station->id);
        $this->assertNull($station->floor_plan_id);
        $this->assertTrue($station->hatPosition());
    }

    public function test_ein_termin_mit_station_darf_veroeffentlicht_werden(): void
    {
        $termin  = $this->termin(1);
        $station = $this->station('Foyer');

        // Ohne Ort fehlt etwas - und der Wortlaut nennt jetzt beide Wege.
        $this->assertSame(['ein Raum oder eine Abholstation'], $termin->fehltZumVeroeffentlichen());

        $this->haengeStationAn($termin, $station, [$this->pause($termin, 1)]);

        // Eine Station allein genuegt: Der Gast bekommt einen Ort, an dem er
        // etwas erhaelt. Ein Saal muss dafuer nicht bestuhlt sein.
        $this->assertSame([], $termin->fresh()->fehltZumVeroeffentlichen());
    }

    public function test_eine_eingeplante_station_laesst_sich_nicht_loeschen(): void
    {
        $termin  = $this->termin(1);
        $station = $this->station('Foyer');

        $this->haengeStationAn($termin, $station, [$this->pause($termin, 1)]);

        // Ohne diesen Schutz naehme die Kaskade die Zuordnung mit, und der
        // Termin verloere lautlos einen Ort, an dem Gaeste bestellt haben.
        $this->expectException(\Platform\Reservation\Exceptions\FloorPlanInUseException::class);

        $station->delete();
    }

    public function test_ein_venue_nimmt_keine_eingeplante_station_mit(): void
    {
        $termin  = $this->termin(1);
        $station = $this->station('Foyer');

        $this->haengeStationAn($termin, $station, [$this->pause($termin, 1)]);

        // Der Blick auf die Raeume beantwortet die Frage nicht: „Foyer links"
        // liegt in keinem.
        $this->expectException(\Platform\Reservation\Exceptions\FloorPlanInUseException::class);

        $station->venue->delete();
    }

    public function test_eine_station_ohne_termin_laesst_sich_loeschen(): void
    {
        $station = $this->station('Foyer');
        $id      = $station->id;

        $station->delete();

        $this->assertNull(PickupStation::find($id));
    }

    public function test_eine_pause_mit_buchungen_bleibt_der_station_erhalten(): void
    {
        $termin = $this->termin(2);
        $eins   = $this->pause($termin, 1);
        $zwei   = $this->pause($termin, 2);

        $station   = $this->station('Foyer');
        $zuordnung = $this->haengeStationAn($termin, $station, [$eins, $zwei]);

        $this->abholung($eins, $station, 2);

        $this->assertTrue($zuordnung->hatBuchungen($eins->id));
        $this->assertFalse($zuordnung->hatBuchungen($zwei->id));

        // Nur die stornierte zaehlt nicht - sie haelt nichts.
        $this->abholung($zwei, $station, 2, Booking::STATUS_CANCELLED);
        $this->assertFalse($zuordnung->hatBuchungen($zwei->id));
    }

    public function test_der_laufzettel_trennt_stationen_voneinander(): void
    {
        $termin = $this->termin(1);
        $pause  = $this->pause($termin, 1);
        $plan   = $this->raum('Saal', [4]);

        $foyer = $this->station('Foyer links');
        $rang  = $this->station('Rang 1 Bar');

        $artikel = $this->artikel();

        // Der Laufzettel laeuft ueber die Positionen - eine Buchung ohne
        // Artikel taucht dort gar nicht auf.
        $this->position($this->abholung($pause, $foyer, 2), $artikel);
        $this->position($this->abholung($pause, $rang, 3), $artikel);
        $this->position($this->buchung($pause, $this->tisch($plan), 2), $artikel);

        $orte = collect((new FunctionSheetService())->build($termin)['pauses'])
            ->flatMap(fn ($p) => $p['runs'])
            ->flatMap(fn ($run) => collect($run['tables'])->pluck('table.label'))
            ->filter()
            ->unique()
            ->values();

        // Mit "table_id ?? 0" landeten beide Stationen in EINEM Topf, samt
        // aller Buchungen mit geloeschtem Tisch - und die Beschriftung kam von
        // der zufaellig ersten. Der Service traegt dann alles an einen Ort, den
        // es so nicht gibt.
        $this->assertTrue($orte->contains('Foyer links'), 'Foyer fehlt: ' . $orte->implode(', '));
        $this->assertTrue($orte->contains('Rang 1 Bar'), 'Rang fehlt: ' . $orte->implode(', '));
        $this->assertTrue($orte->contains('Saal 1'), 'Tisch fehlt: ' . $orte->implode(', '));
    }

    public function test_ohne_position_gibt_es_keine_flaeche(): void
    {
        $station = $this->station('Foyer links');

        // „Foyer links" liegt gar nicht im Saal. Eine Flaeche bei 0/0 mit
        // Groesse 0 waere schlimmer als keine - sie sieht nach einem Fehler aus.
        $this->assertFalse($station->hatPosition());
        $this->assertSame('', $station->surfaceStyle());
    }
}
