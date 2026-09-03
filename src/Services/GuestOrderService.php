<?php

namespace Platform\Reservation\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Platform\Reservation\Exceptions\GuestOrderException;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Models\Order;
use Platform\Reservation\Models\EventStation;
use Platform\Reservation\Models\Table;
use Platform\Reservation\Services\RoomReleaseService;

/**
 * Autoritative Erstellung einer Gast-Bestellung (Order + N Slot-Buchungen) aus
 * einem API-Payload. Preise/Steuer kommen aus der DB (nie aus dem Request),
 * Artikel werden auf die Verkaufsliste des Events beschränkt, Mengen begrenzt,
 * Plätze je Pause geprüft. Genutzt von der Gast-API; teilt die Kalkulation
 * (CartCalculator) mit dem In-App-Wizard.
 */
class GuestOrderService
{
    public function __construct(
        protected CartCalculator $calc,
        protected SeatAvailabilityService $seats,
        protected MolliePaymentService $payments,
        protected RoomReleaseService $freigabe,
        protected PickupCapacityService $pickup,
    ) {
    }

    /**
     * @param array{first_name?:string,last_name?:string,company?:?string,email:?string,phone:?string,count:int,notes:?string,billing?:array} $guest
     * @param array<int, array{slot_id:int, table_id:int, items:array<int,int>}> $slotOrders
     * @return array{order: Order, checkout_url: ?string}
     *
     * @throws GuestOrderException
     */
    public function place(Event $event, array $guest, array $slotOrders, bool $ageConfirmed, ?string $redirectUrl = null): array
    {
        if (!$event->isOrderable()) {
            throw new GuestOrderException('Der Bestellschluss für diesen Termin ist erreicht.', 'ORDER_CLOSED');
        }

        $order = $this->store($event, $guest, $slotOrders, $ageConfirmed);

        $checkoutUrl = null;
        if ($order->total_amount > 0 && $this->payments->isEnabledForTeam($event->team_id)) {
            $checkoutUrl = $this->payments->createForOrder($order, $redirectUrl);
        }

        return ['order' => $order, 'checkout_url' => $checkoutUrl];
    }

    /**
     * Buchung durch das Backoffice: telefonisch, am Schalter, nachträglich.
     *
     * Denselben schreibenden Kern wie der Gast-Weg – Artikel gegen die
     * Verkaufsliste des Termins, Tisch muss zum Termin gehören, Plätze werden
     * geprüft, Preise aus der Datenbank eingefroren. Zwei Unterschiede, und
     * beide sind Absicht:
     *
     *   - KEIN Bestellschluss. Genau deshalb ruft jemand an; der Shop ist dann
     *     schon zu. Wer intern bucht, übernimmt die Verantwortung dafür.
     *   - Sofort bestätigt und ohne Zahlungslink. Bezahlt wird vor Ort, es
     *     entsteht keine offene Mollie-Zahlung. Bestätigt heißt: zählt in Küche,
     *     Laufzettel, Platzprüfung und Umsatz.
     *
     * @param array{first_name?:string,last_name?:string,company?:?string,email:?string,phone:?string,count:int,notes:?string} $guest
     * @param array<int, array{slot_id:int, table_id:int, items:array<int,int>}> $slotOrders
     *
     * @throws GuestOrderException
     */
    public function placeForStaff(Event $event, array $guest, array $slotOrders): Order
    {
        // Altersbestätigung: Wer am Telefon sitzt, fragt und verantwortet es.
        // Eine Häkchen-Abfrage im Backoffice wäre Theater.
        return $this->store(
            $event,
            $guest,
            $slotOrders,
            true,
            Order::STATUS_CONFIRMED,
            Booking::STATUS_CONFIRMED,
            'onsite',
            false,   // Raumfreigabe gilt für den Gast, nicht fürs Backoffice
        );
    }

    /**
     * Liegt der Tisch in einem Raum, der für diese Pause freigegeben ist?
     *
     * Räume ohne Bezug zum Tisch interessieren nicht: Gehört der Tisch zu
     * keinem der Räume des Termins, greift ohnehin schon TABLE_NOT_IN_EVENT.
     */
    protected function raumIstOffen(Event $event, $slot, Table $table): bool
    {
        $offene = $this->freigabe->openRooms($event, $slot);

        return $offene->contains(fn ($raum) => (int) $raum->floor_plan_id === (int) $table->floor_plan_id);
    }

    /**
     * Der schreibende Kern: prüfen, einfrieren, speichern.
     *
     * Bewusst herausgelöst, damit Gast-Weg und Backoffice DIESELBEN Prüfungen
     * nehmen. Zwei Fassungen davon wären die Art Fehler, die man erst merkt,
     * wenn die Zahlen auseinanderlaufen.
     *
     * @param array<int, array{slot_id:int, table_id:int, items:array<int,int>}> $slotOrders
     *
     * @throws GuestOrderException
     */
    protected function store(
        Event $event,
        array $guest,
        array $slotOrders,
        bool $ageConfirmed,
        string $orderStatus = Order::STATUS_PENDING,
        string $bookingStatus = Booking::STATUS_PENDING,
        ?string $paymentMethod = null,
        bool $pruefeFreigabe = true,
    ): Order {
        // Der Backoffice-Weg kommt aus einer Livewire-Komponente und hat die
        // Beziehungen nicht zwingend geladen.
        $event->loadMissing(['slots', 'eventRooms']);

        if (empty($slotOrders)) {
            throw new GuestOrderException('Es wurde keine Pause mit Produkten übermittelt.', 'EMPTY_ORDER');
        }

        $allowedFloorPlanIds = $event->eventRooms->pluck('floor_plan_id')->all();

        // Weiche Tisch-Kapazität (Großgruppen auf leere Tische) je Team-Setting.
        $checkout     = \Platform\Reservation\Models\CheckoutSetting::forTeam((int) $event->team_id);
        $softCapacity = $checkout->softTableCapacity();
        $maxGroup     = $checkout->maxGroupEmptyTable();

        // Vorbereiten & validieren je Pause (ohne zu schreiben).
        // [ ['slot'=>EventSlot, 'lines'=>Collection, 'table'=>Table ODER 'station'=>EventStation], ... ]
        $prepared = [];
        $allLines = collect();

        foreach ($slotOrders as $slotOrder) {
            $slot = $event->slots->firstWhere('id', (int) ($slotOrder['slot_id'] ?? 0));
            if (!$slot) {
                throw new GuestOrderException('Unbekannte Pause im Auftrag.', 'SLOT_NOT_FOUND');
            }

            $lines = $this->calc->lines((array) ($slotOrder['items'] ?? []), $event);
            if ($lines->isEmpty()) {
                throw new GuestOrderException('Für eine Pause wurden keine gültigen Produkte übermittelt.', 'INVALID_ITEMS');
            }

            // Genau EIN Zielort je Pause: ein Tisch oder eine Abholstation.
            // Dieselbe Regel wie am Booking-Model, nur hier als fachlicher
            // Fehler statt als Ausnahme - hier kommt sie aus einer Anfrage.
            $tableId   = (int) ($slotOrder['table_id'] ?? 0);
            $stationId = (int) ($slotOrder['station_id'] ?? 0);

            if ($tableId > 0 && $stationId > 0) {
                throw new GuestOrderException('Für eine Pause wurden Tisch und Abholstation zugleich übermittelt.', 'PLACE_AMBIGUOUS');
            }

            if ($tableId < 1 && $stationId < 1) {
                throw new GuestOrderException('Für eine Pause fehlt der Ort: ein Tisch oder eine Abholstation.', 'PLACE_REQUIRED');
            }

            // Belegung, Raumfreigabe und Stationskapazität werden hier NICHT
            // geprüft - alle drei hängen davon ab, was andere Gäste in
            // derselben Sekunde tun. Sie stehen in der Transaktion weiter unten.

            if ($stationId > 0) {
                // Die Station muss zum TERMIN und zur PAUSE gehören. Ohne diese
                // Prüfung wäre die Stations-Id aus der Anfrage ein IDOR -
                // dieselbe Strenge, mit der der Tisch gegen die Tischpläne des
                // Termins geprüft wird.
                $zuordnung = EventStation::where('event_id', $event->id)
                    ->where('pickup_station_id', $stationId)
                    ->with(['slots', 'station'])
                    ->first();

                if (! $zuordnung || ! $zuordnung->station?->is_active) {
                    throw new GuestOrderException('Die gewählte Abholstation gehört nicht zu diesem Termin.', 'STATION_NOT_IN_EVENT');
                }

                if (! $zuordnung->offenIn($slot->id)) {
                    throw new GuestOrderException('Die gewählte Abholstation ist in dieser Pause nicht geöffnet.', 'STATION_NOT_IN_SLOT');
                }

                $prepared[] = ['slot' => $slot, 'station' => $zuordnung, 'lines' => $lines];
                $allLines    = $allLines->merge($lines);

                continue;
            }

            $table = Table::withoutGlobalScope('team')->find($tableId);
            if (!$table || !in_array($table->floor_plan_id, $allowedFloorPlanIds, true)) {
                throw new GuestOrderException('Der gewählte Tisch gehört nicht zu diesem Termin.', 'TABLE_NOT_IN_EVENT');
            }

            $prepared[] = ['slot' => $slot, 'table' => $table, 'lines' => $lines];
            $allLines    = $allLines->merge($lines);
        }

        if ($this->calc->containsAgeRestricted($allLines) && !$ageConfirmed) {
            throw new GuestOrderException('Die Bestellung enthält alkoholische Getränke – Altersbestätigung erforderlich.', 'AGE_REQUIRED');
        }

        $order = DB::transaction(function () use ($event, $guest, $prepared, $allLines, $orderStatus, $bookingStatus, $paymentMethod, $pruefeFreigabe, $softCapacity, $maxGroup) {
            // ERSTE Anweisung der Transaktion, und das ist kein Zufall:
            //
            // Die Tische werden gesperrt, BEVOR irgendetwas gelesen wird. Eine
            // sperrende Abfrage liest immer den zuletzt festgeschriebenen
            // Stand; eine gewöhnliche legt in MySQL dagegen den Schnappschuss
            // der Transaktion fest. Stünde vor dieser Zeile ein einfaches
            // SELECT, arbeitete die Platzprüfung danach mit einem Stand von
            // VOR dem Warten auf die Sperre - und wäre damit genau so blind
            // wie vorher.
            $this->tischeSperren($prepared);

            $this->platzPruefen($event, $prepared, (int) $guest['count'], $pruefeFreigabe, $softCapacity, $maxGroup);

            $billing = (array) ($guest['billing'] ?? []);

            $order = Order::create([
                'team_id'         => $event->team_id,
                'event_id'        => $event->id,
                'status'          => $orderStatus,
                'first_name'      => $guest['first_name'] ?? null,
                'last_name'       => $guest['last_name'] ?? null,
                'company'         => ($guest['company'] ?? null) ?: null,
                'email'           => ($guest['email'] ?? null) ?: null,
                'phone'           => ($guest['phone'] ?? null) ?: null,
                'billing_street'  => ($billing['street'] ?? null) ?: null,
                'billing_zip'     => ($billing['zip'] ?? null) ?: null,
                'billing_city'    => ($billing['city'] ?? null) ?: null,
                'billing_country' => ($billing['country'] ?? null) ?: null,
            ]);

            // Denormalisierter Anzeigename für Küche/Laufzettel/Mails.
            $displayName = $order->customerName();

            $ageAt = $this->calc->containsAgeRestricted($allLines) ? now() : null;

            foreach ($prepared as $p) {
                $booking = Booking::create([
                    'order_id'               => $order->id,
                    'team_id'                => $event->team_id,
                    'event_id'               => $event->id,
                    'event_slot_id'          => $p['slot']->id,
                    'table_id'               => isset($p['table']) ? $p['table']->id : null,
                    'pickup_station_id'      => isset($p['station']) ? $p['station']->pickup_station_id : null,
                    // Ort einfrieren, wie Preise eingefroren werden. Wird der
                    // Tisch später gelöscht, bleibt wenigstens sein Name an der
                    // Buchung - sonst steht auf Bon und Laufzettel gar nichts.
                    'place_kind'             => isset($p['table']) ? 'table' : 'station',
                    'place_label'            => isset($p['table']) ? $p['table']->label : $p['station']->station?->name,
                    'guest_name'             => $displayName,
                    'guest_email'            => ($guest['email'] ?? null) ?: null,
                    'guest_phone'            => ($guest['phone'] ?? null) ?: null,
                    'guest_count'            => (int) $guest['count'],
                    'notes'                  => $guest['notes'] ?: null,
                    'date'                   => $event->date->toDateString(),
                    'time_start'             => $p['slot']->time_start,
                    'time_end'               => $p['slot']->time_end,
                    'status'                 => $bookingStatus,
                    'payment_method'         => $paymentMethod,
                    'age_check_confirmed_at' => $ageAt,
                    'legal_accepted_at'      => now(),
                ]);

                foreach ($this->calc->frozenItemAttributes($p['lines']) as $attributes) {
                    $booking->items()->create($attributes);
                }
            }

            return $order;
        });

        // Scope-sicher nachladen (API-Kontext ist authentifiziert → Global Scope aktiv).
        $order->load(['bookings' => fn ($q) => $q->withoutGlobalScope('team')->with('items')]);

        return $order;
    }

    /**
     * Die betroffenen Tische für die Dauer der Transaktion sperren.
     *
     * Der Tisch ist die Einheit, um die zwei Gäste streiten können, also wird
     * er zum Engpass gemacht: Wer als Zweiter kommt, wartet, liest danach die
     * echte Belegung und bekommt sein TABLE_FULL. Gesperrt wird die
     * TISCH-Zeile, nicht die Buchungen - die es ja noch nicht gibt. Genau
     * deshalb hilft eine Sperre auf den vorhandenen Buchungen nicht: Das
     * Problem ist die Zeile, die beide gleich anlegen wollen.
     *
     * Nach Id sortiert, und das ist wichtig: Zwei Bestellungen über dieselben
     * zwei Tische in verschiedener Reihenfolge könnten sich sonst gegenseitig
     * blockieren. Eine feste Reihenfolge schließt das aus.
     *
     * Nicht gesperrt wird der ganze Termin. Das wäre einfacher und würde einen
     * Vorverkaufsansturm zu einer Warteschlange machen - für ein Problem, das
     * nur zwischen Gästen AM SELBEN TISCH auftreten kann.
     *
     * @param  array<int, array{slot: mixed, table: Table, lines: Collection}>  $prepared
     */
    protected function tischeSperren(array $prepared): void
    {
        // Nur Tische. Eine Abholstation wird bewusst NICHT gesperrt - ihre
        // Obergrenze ist eine Bremse gegen Ueberlast, keine Zusage ueber
        // vorhandene Ware (Entscheidung 28.08.2026, siehe PickupCapacityService).
        $ids = collect($prepared)
            ->filter(fn ($p) => isset($p['table']))
            ->map(fn ($p) => (int) $p['table']->id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($ids === []) {
            return;
        }

        Table::withoutGlobalScope('team')
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * Platz und Raumfreigabe prüfen – innerhalb der Transaktion, nach der Sperre.
     *
     * Beides stand früher in der Vorbereitung, also VOR der Transaktion. Damit
     * lag zwischen „ist noch Platz?" und „Buchung anlegen" ein Zeitfenster, in
     * dem ein zweiter Gast dasselbe tun konnte. Beide sahen freie Plätze, beide
     * schrieben - und am Abend standen mehr Gäste am Tisch, als daran passen.
     * Auffallen würde das niemandem am Bildschirm; die Zahlen sind hinterher
     * stimmig, nur der Tisch ist zu klein.
     *
     * Die Fehlermeldungen bleiben dieselben. Dass sie jetzt aus einer
     * Transaktion kommen, ist folgenlos: Eine Ausnahme rollt sie vollständig
     * zurück, es bleibt weder eine halbe Bestellung noch eine Buchung stehen.
     *
     * Was diese Sperre NICHT abdeckt: die sequentielle Raumfreigabe. Sie liest
     * die Belegung des vorherigen Raums, also fremde Tische. Zwei gleichzeitige
     * Bestellungen können dort zu unterschiedlichen Antworten kommen - dann
     * öffnet der nächste Raum eine Buchung später. Das ist eine Verzögerung,
     * kein überbelegter Tisch, und den ganzen Termin dafür zu sperren wäre
     * teurer als der Fehler.
     *
     * @param  array<int, array{slot: mixed, table: Table, lines: Collection}>  $prepared
     *
     * @throws GuestOrderException
     */
    protected function platzPruefen(
        Event $event,
        array $prepared,
        int $partySize,
        bool $pruefeFreigabe,
        bool $softCapacity,
        ?int $maxGroup,
    ): void {
        foreach ($prepared as $p) {
            if (isset($p['station'])) {
                // Keine Raumfreigabe: Eine Abholstation folgt ihr nicht, auch
                // wenn sie im Tischplan eines Raums gezeichnet ist
                // (Entscheidung 03.09.2026). Sie ist offen, sobald der Termin
                // sie in dieser Pause fuehrt - das steht schon fest.
                if (! $this->pickup->passt($p['station'], $p['slot'], $partySize)) {
                    throw new GuestOrderException('Die Abholstation ist in dieser Pause ausgebucht.', 'STATION_FULL');
                }

                continue;
            }

            // Raumfreigabe: Bei sequentieller Reihenfolge ist Raum 2 erst offen,
            // wenn Raum 1 voll genug ist. Das stand bisher nur im VA-Dashboard -
            // der Bestellweg fragte gar nicht, und der Shop nahm Buchungen in
            // Räumen an, die das Haus als geschlossen sah. Zwei Bildschirme,
            // zwei Wahrheiten.
            //
            // Nur der GAST-Weg prüft das. Wer telefonisch bucht, entscheidet
            // selbst - das ist der Sinn eines Backoffice-Wegs.
            if ($pruefeFreigabe && ! $this->raumIstOffen($event, $p['slot'], $p['table'])) {
                throw new GuestOrderException(
                    'Dieser Raum ist für die gewählte Pause noch nicht freigegeben.',
                    'ROOM_CLOSED'
                );
            }

            if (! $this->seats->canSeat($p['table'], $p['slot'], $partySize, $softCapacity, $maxGroup)) {
                throw new GuestOrderException('Ein gewählter Tisch hat nicht genügend freie Plätze.', 'TABLE_FULL');
            }
        }
    }
}
