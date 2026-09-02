<?php

namespace Platform\Reservation\Tests\Concerns;

use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\CheckoutSetting;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Models\EventRoom;
use Platform\Reservation\Models\EventSlot;
use Platform\Reservation\Models\FloorPlan;
use Platform\Reservation\Models\MenuCategory;
use Platform\Reservation\Models\MenuItem;
use Platform\Reservation\Models\EventStation;
use Platform\Reservation\Models\Order;
use Platform\Reservation\Models\PickupStation;
use Platform\Reservation\Models\SalesList;
use Platform\Reservation\Models\Table;
use Platform\Reservation\Models\Venue;

/**
 * Kleine Bausteine für Termine, Räume und Buchungen.
 *
 * Bewusst ohne Factories: Die Tests hier prüfen Zahlen, und wer eine Zahl prüft,
 * muss jeden Wert sehen, der sie beeinflusst. Eine Factory mit Zufallswerten
 * würde genau das verstecken.
 */
trait ErzeugtTermine
{
    protected int $teamId = 1;

    /** Termin mit n Pausen. */
    protected function termin(int $pausen = 2, array $attribute = []): Event
    {
        $event = Event::create(array_merge([
            'team_id' => $this->teamId,
            'name'    => 'Testkonzert',
            'date'    => '2026-10-01',
            'status'  => Event::STATUS_PUBLISHED,
        ], $attribute));

        for ($i = 1; $i <= $pausen; $i++) {
            EventSlot::create([
                'event_id'   => $event->id,
                'name'       => 'Pause ' . $i,
                'time_start' => sprintf('%02d:00:00', 19 + $i),
                'sort_order' => $i,
            ]);
        }

        return $event->fresh();
    }

    /** Raum (Tischplan) mit einem Tisch je übergebener Platzzahl. */
    protected function raum(string $name, array $plaetze): FloorPlan
    {
        $venue = Venue::create([
            'team_id' => $this->teamId,
            'name'    => $name . '-Haus',
        ]);

        $plan = FloorPlan::create([
            'venue_id' => $venue->id,
            'name'     => $name,
        ]);

        foreach ($plaetze as $nummer => $kapazitaet) {
            Table::create([
                'floor_plan_id' => $plan->id,
                'label'         => $name . ' ' . ($nummer + 1),
                'capacity'      => $kapazitaet,
            ]);
        }

        return $plan->fresh();
    }

    /** Raum an einen Termin hängen (Reihenfolge = Freigabekette). */
    protected function haengeRaumAn(Event $event, FloorPlan $plan, int $reihenfolge = 0, int $schwelle = 100): EventRoom
    {
        return EventRoom::create([
            'event_id'               => $event->id,
            'floor_plan_id'          => $plan->id,
            'sort_order'             => $reihenfolge,
            'fill_threshold_percent' => $schwelle,
        ]);
    }

    protected function bestellung(Event $event): Order
    {
        return Order::create([
            'team_id'  => $this->teamId,
            'event_id' => $event->id,
            'status'   => Order::STATUS_PENDING,
        ]);
    }

    /**
     * Buchung mit ausstehendem Status.
     *
     * "pending" mit Absicht: Der automatische Bon-Druck hängt am Wechsel auf
     * "bestätigt" und würde hier einen Dienst ziehen, der mit der Zählung
     * nichts zu tun hat. Gezählt werden beide gleich – ausgenommen sind nur
     * storniert und No-Show.
     */
    protected function buchung(EventSlot $pause, Table $tisch, int $personen, ?Order $bestellung = null, string $status = Booking::STATUS_PENDING): Booking
    {
        return Booking::create([
            'team_id'       => $this->teamId,
            'order_id'      => $bestellung?->id,
            'event_id'      => $pause->event_id,
            'event_slot_id' => $pause->id,
            'table_id'      => $tisch->id,
            'guest_name'    => 'Testgast',
            'guest_count'   => $personen,
            'date'          => '2026-10-01',
            'time_start'    => $pause->time_start,
            'status'        => $status,
        ]);
    }

    /** Vorgabe des Teams setzen (Termine ohne eigenen Wert folgen ihr). */
    protected function teamVorgabe(string $bindung): CheckoutSetting
    {
        $einstellungen = CheckoutSetting::forTeam($this->teamId);
        $einstellungen->table_binding = $bindung;
        $einstellungen->save();

        return $einstellungen;
    }

    /**
     * Ein freigegebener Artikel in der Standard-Verkaufsliste des Teams.
     *
     * Weniger geht nicht: Ohne Verkaufsliste liefert der CartCalculator eine
     * leere Menge, und jede Bestellung scheitert an INVALID_ITEMS statt an dem,
     * was der Test eigentlich prüft.
     */
    protected function artikel(float $preis = 5.0): MenuItem
    {
        $liste = SalesList::firstOrCreate(
            ['team_id' => $this->teamId, 'is_default' => true],
            ['name' => 'Standard'],
        );

        $kategorie = MenuCategory::firstOrCreate(
            ['team_id' => $this->teamId, 'name' => 'Test'],
        );

        $artikel = MenuItem::create([
            'team_id'         => $this->teamId,
            'category_id'     => $kategorie->id,
            'name'            => 'Testartikel',
            'price'           => $preis,
            'tax_rate'        => 19,
            'available'       => true,
            'approval_status' => MenuItem::APPROVAL_APPROVED,
        ]);

        $liste->menuItems()->attach($artikel->id);

        return $artikel;
    }

    /** Abholstation an einem Venue – ohne Lage im Plan, das ist der Normalfall. */
    protected function station(string $name = 'Foyer links', ?int $grenze = null): PickupStation
    {
        $venue = Venue::firstOrCreate(
            ['team_id' => $this->teamId, 'name' => 'Stationen-Haus'],
        );

        return PickupStation::create([
            'team_id'           => $this->teamId,
            'venue_id'          => $venue->id,
            'name'              => $name,
            'capacity_per_slot' => $grenze,
        ]);
    }

    /**
     * Station an einen Termin haengen – in den angegebenen Pausen.
     *
     * Die Pausen sind Pflicht, wie im Betrieb: „keine Zeile" haette sonst zwei
     * Bedeutungen.
     *
     * @param  array<int, EventSlot>  $pausen
     */
    protected function haengeStationAn(Event $event, PickupStation $station, array $pausen, ?int $grenze = null): EventStation
    {
        $zuordnung = EventStation::create([
            'event_id'          => $event->id,
            'pickup_station_id' => $station->id,
            'capacity_override' => $grenze,
        ]);

        $zuordnung->slots()->sync(collect($pausen)->pluck('id')->all());

        return $zuordnung->fresh(['slots', 'station']);
    }

    /** Buchung an eine Abholstation statt an einen Tisch. */
    protected function abholung(EventSlot $pause, PickupStation $station, int $personen, string $status = Booking::STATUS_PENDING): Booking
    {
        return Booking::create([
            'team_id'           => $this->teamId,
            'event_id'          => $pause->event_id,
            'event_slot_id'     => $pause->id,
            'pickup_station_id' => $station->id,
            'guest_name'        => 'Testgast',
            'guest_count'       => $personen,
            'date'              => '2026-10-01',
            'time_start'        => $pause->time_start,
            'status'            => $status,
        ]);
    }

    protected function pause(Event $event, int $nummer): EventSlot
    {
        return $event->slots()->orderBy('sort_order')->get()[$nummer - 1];
    }

    protected function tisch(FloorPlan $plan, int $nummer = 1): Table
    {
        return $plan->tables()->orderBy('id')->get()[$nummer - 1];
    }
}
