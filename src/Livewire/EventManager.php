<?php

namespace Platform\Reservation\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\Table;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Models\PickupStation;
use Platform\Reservation\Models\EventRoom;
use Platform\Reservation\Models\EventSlot;
use Platform\Reservation\Models\FloorPlan;
use Platform\Reservation\Models\SalesList;
use Platform\Reservation\Models\Venue;

class EventManager extends Component
{
    use WithFileUploads;

    public bool $showForm = false;
    public ?int $editingEventId = null;

    // Übersichts-Filter (Default: veröffentlichte, kommende Termine)
    public string $statusFilter = 'published'; // draft|published|closed|cancelled|nachzubereiten|all
    public string $timeFilter   = 'upcoming';  // upcoming|past|all

    // Stammdaten
    public string $eventName = '';
    public string $eventDescription = '';
    public string $eventDate = '';
    public string $eventDeadline = '';
    public ?int $eventVenueId = null;
    public ?int $eventSalesListId = null;
    public string $eventReleaseMode = Event::RELEASE_PARALLEL;
    /** Leer = Vorgabe des Teams. */
    public string $eventMaxGuestCount = '';
    /** Tischbindung: '' = Vorgabe des Teams, sonst event|slot. */
    public string $eventTableBinding = '';
    public ?int $eventEventsEventId = null;

    /** @var array<int, array{id: ?int, name: string, time_start: string, time_end: string}> */
    public array $slots = [];

    /** @var array<int, array{id: ?int, floor_plan_id: ?int, fill_threshold_percent: int, capacity_override: ?string, open_mode: string}> */
    public array $rooms = [];

    /**
     * Abholstationen des Termins, je Zeile mit ihren Pausen-POSITIONEN.
     *
     * [['id' => ?int, 'pickup_station_id' => ?int, 'capacity_override' => '',
     *   'slot_indexes' => [0, 1]]]
     */
    public array $stations = [];

    /** @var array<int, int> Tisch-IDs, die für diesen Termin gesperrt sind */
    public array $disabledTableIds = [];

    public $eventImage = null;

    protected function getTeamId(): ?int
    {
        return Auth::user()?->current_team_id;
    }

    #[Computed]
    public function events(): \Illuminate\Database\Eloquent\Collection
    {
        $today = now()->toDateString();

        return Event::forTeam($this->getTeamId())
            ->with(['venue', 'salesList', 'slots'])
            ->withCount(['eventRooms', 'bookings'])
            ->withCount(['bookings as bestaetigte_count' => fn ($q) => $q->where('status', Booking::STATUS_CONFIRMED)])
            ->when($this->statusFilter === 'closed', fn ($q) => $q->where(
                // „Bestellschluss" ist zweierlei: von Hand gesperrt (Status) oder
                // Frist abgelaufen. Nur den Status zu zeigen hieße, den Regelfall
                // zu verstecken – abgelaufen ist der übliche Weg, gesperrt die
                // Ausnahme.
                fn ($q) => $q->where('status', Event::STATUS_CLOSED)
                    ->orWhere(fn ($q) => $q->where('status', Event::STATUS_PUBLISHED)
                        ->whereNotNull('order_deadline_at')
                        ->where('order_deadline_at', '<', now()))
            ))
            ->when($this->statusFilter === 'nachzubereiten', fn ($q) => $q
                // Der Abend ist vorbei, aber es stehen noch bestätigte Buchungen –
                // niemand hat durchgesehen, wer da war und wer nicht. Abgesagte
                // Termine gehören nicht in diese Arbeitsliste.
                ->whereDate('date', '<', $today)
                ->whereIn('status', [Event::STATUS_PUBLISHED, Event::STATUS_CLOSED])
                ->whereHas('bookings', fn ($b) => $b->where('status', Booking::STATUS_CONFIRMED)))
            ->when(!in_array($this->statusFilter, ['all', 'closed', 'nachzubereiten'], true), fn ($q) => $q->where('status', $this->statusFilter))
            // „Veröffentlicht" heißt: Gäste sehen den Termin und können bestellen.
            // Nach dem Abend trifft das nicht mehr zu – deshalb trägt ein
            // vergangener Termin das Badge nicht mehr, und deshalb gehört er auch
            // nicht in diesen Filter. Zu finden bleibt er über Zeit › Vergangen.
            ->when($this->statusFilter === 'published', fn ($q) => $q->whereDate('date', '>=', $today))
            ->when($this->timeFilter === 'upcoming', fn ($q) => $q->whereDate('date', '>=', $today))
            ->when($this->timeFilter === 'past', fn ($q) => $q->whereDate('date', '<', $today))
            ->orderBy('date')
            ->get();
    }

    /**
     * Gibt es überhaupt Termine – unabhängig vom Filter?
     *
     * Der Leer-Zustand hing vorher an der GEFILTERTEN Liste. Ein neu angelegter
     * Termin ist Entwurf, der Standardfilter steht auf "Veröffentlicht": die
     * Liste war leer, damit verschwand die Filterleiste, und der Termin war
     * nicht mehr erreichbar.
     */
    #[Computed]
    public function hasAnyEvents(): bool
    {
        return Event::forTeam($this->getTeamId())->exists();
    }

    #[Computed]
    public function venues(): \Illuminate\Database\Eloquent\Collection
    {
        return Venue::where('team_id', $this->getTeamId())->orderBy('name')->get();
    }

    #[Computed]
    public function salesLists(): \Illuminate\Database\Eloquent\Collection
    {
        return SalesList::forTeam($this->getTeamId())->orderBy('name')->get();
    }

    /**
     * Alle aktiven Tischpläne des Teams (nach Venue gruppiert) – Räume sind
     * nicht an die Venue-Auswahl gekoppelt, das Venue wird beim Speichern
     * automatisch aus dem ersten Raum übernommen, falls keins gewählt ist.
     */
    #[Computed]
    public function availableFloorPlans(): \Illuminate\Database\Eloquent\Collection
    {
        return FloorPlan::with('venue')
            ->whereHas('venue', fn ($q) => $q->where('team_id', $this->getTeamId()))
            ->active()
            ->orderBy('name')
            ->get();
    }

    /**
     * Abholstationen, die dieser Termin anbieten kann.
     *
     * Aktive des Teams, nach Haus sortiert. Abgeschaltete stehen nicht zur
     * Wahl – eine Station, die im Shop nicht erscheint, gehört auch nicht in
     * die Liste eines neuen Termins. Bereits zugeordnete bleiben davon
     * unberührt; sie stehen schon in $stations.
     */
    #[Computed]
    public function availableStations(): \Illuminate\Database\Eloquent\Collection
    {
        return PickupStation::forTeam($this->getTeamId())
            ->active()
            // Nur die des eigenen Hauses, sobald eines feststeht. Ohne diese
            // Grenze liesse sich einem Termin im Haus A das Foyer aus Haus B
            // zuordnen - der Server naehme es an, denn er prueft nur "gehoert
            // zum Termin", und zugeordnet waere es ja. Bei einem einzigen Venue
            // faellt das nie auf; beim zweiten steht die Ware im falschen Haus.
            ->when($this->wirksamesVenueId(), fn ($q, $venueId) => $q->where('venue_id', $venueId))
            ->with('venue')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Das Haus dieses Termins – gewählt oder aus dem ersten Raum abgeleitet.
     *
     * Dieselbe Ableitung wie beim Speichern, damit die Liste im Formular und
     * die Prüfung danach dasselbe meinen. Null heißt „steht noch nicht fest" –
     * dann bleiben alle Stationen wählbar, sonst wäre die Liste beim Anlegen
     * leer, bevor der erste Raum drin ist.
     */
    protected function wirksamesVenueId(): ?int
    {
        if ($this->eventVenueId) {
            return (int) $this->eventVenueId;
        }

        $ersterPlan = $this->rooms[0]['floor_plan_id'] ?? null;

        return $ersterPlan
            ? FloorPlan::find((int) $ersterPlan)?->venue_id
            : null;
    }

    /**
     * Wie viele Plätze für die Prozentrechnung gelten – und woher die Zahl kommt.
     *
     * Fertiger Satzteil statt zweier Werte, weil in der Vorlage keine
     * Zwischenvariable gesetzt werden kann: Die einzeilige PHP-Direktive ist
     * dort unbrauchbar, solange weiter unten ein schliessender Block steht.
     */
    public function plaetzeText(?int $floorPlanId, $override): string
    {
        $eigen = (int) ($override ?: 0);

        if ($eigen > 0) {
            return $eigen . ' Plätzen (eingestellt)';
        }

        $summe = (int) ($this->roomTables->firstWhere('id', $floorPlanId)?->tables->sum('capacity') ?? 0);

        return $summe > 0
            ? $summe . ' Plätzen (Summe der Tische)'
            : 'den Plätzen des Raums';
    }

    /**
     * Auswählbare Tischpläne für EINE Raumzeile.
     *
     * Ohne die eigene Auswahl der anderen Zeilen: Derselbe Plan zweimal am
     * selben Termin geht nicht (eindeutiger Index auf event_id + floor_plan_id),
     * und was nicht geht, soll man auch nicht anklicken können. Die Regel beim
     * Speichern bleibt trotzdem – sie fängt den Fall ab, dass zwei leere Zeilen
     * nacheinander denselben Plan bekommen.
     *
     * @return array<int, array{value: int, label: string}>
     */
    public function floorPlanOptions(int $index): array
    {
        $vergeben = collect($this->rooms)
            ->except($index)
            ->pluck('floor_plan_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        return $this->availableFloorPlans
            ->reject(fn ($p) => in_array((int) $p->id, $vergeben, true))
            ->map(fn ($p) => [
                'value' => $p->id,
                'label' => ($p->venue?->name ? $p->venue->name . ' – ' : '') . $p->name,
            ])
            ->values()
            ->all();
    }

    /** Name eines Tischplans – für den Hinweis „gibt X frei" in der Raumliste. */
    public function planName(?int $floorPlanId): string
    {
        if (! $floorPlanId) {
            return 'den nächsten Raum';
        }

        return $this->availableFloorPlans->firstWhere('id', $floorPlanId)?->name ?? 'den nächsten Raum';
    }

    /** Vorgabe des Teams – als Platzhalter im Formular, damit sichtbar ist, was ohne eigene Zahl gilt. */
    #[Computed]
    public function standardMaxGuestCount(): int
    {
        return \Platform\Reservation\Models\CheckoutSetting::forTeam($this->getTeamId())->maxGuestCount();
    }

    /**
     * Der Schwellwert, wie ihn der erklärende Satz nennen soll.
     *
     * Ein LEERES Feld ist keine Zahl – der Nutzer hat gerade alles gelöscht,
     * und bis er etwas tippt, gilt die Vorgabe. Eine 0 dagegen IST eine Zahl,
     * und der Satz muss sie zeigen.
     *
     * Vorher stand dort `?: 100`, was beides gleich behandelte: Im Feld stand 0,
     * im Satz darunter „Ab 100 %". Zwei Aussagen auf einem Bildschirm, und die
     * erklärende war die falsche. Speichern ließ sich die 0 ohnehin nie – das
     * Formular und alle drei MCP-Werkzeuge verlangen min:1 – der Widerspruch
     * stand nur da, bis jemand speicherte, und ließ an der falschen Stelle
     * zweifeln.
     */
    public function schwelle(int $index): int
    {
        $wert = $this->rooms[$index]['fill_threshold_percent'] ?? null;

        return $wert === null || $wert === '' ? 100 : (int) $wert;
    }

    /** Vorgabe des Teams für die Tischbindung – damit sichtbar ist, was ohne eigene Wahl gilt. */
    #[Computed]
    public function standardTableBinding(): string
    {
        return \Platform\Reservation\Models\CheckoutSetting::forTeam($this->getTeamId())->tableBinding();
    }

    /** Die Vorgabe des Teams als Satzteil, wie er im Formular steht. */
    #[Computed]
    public function standardTableBindingLabel(): string
    {
        return $this->standardTableBinding === Event::BINDING_SLOT
            ? 'Jede Pause wird einzeln vergeben'
            : 'Der Tisch gehört dem Gast den ganzen Abend';
    }

    /**
     * Tische, die mit der gerade gewählten Bindung überbelegt wären.
     *
     * Bewusst als Hinweis unter dem Feld statt als Rückfrage beim Speichern:
     * Die Umstellung löscht und verschiebt nichts, sie ändert nur, wie gerechnet
     * wird. Wer die Zahl im Moment der Entscheidung sieht, entscheidet
     * informiert; eine Rückfrage beim Speichern käme, wenn der Kopf schon
     * weiter ist, und würde weggeklickt.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function ueberbelegteTische(): array
    {
        if (! $this->editingEventId) {
            return [];
        }

        $gewaehlt = $this->eventTableBinding !== '' ? $this->eventTableBinding : $this->standardTableBinding;

        if ($gewaehlt !== Event::BINDING_EVENT) {
            return [];
        }

        $event = Event::with(['slots', 'eventRooms', 'eventStations.slots'])->find($this->editingEventId);

        return $event
            ? app(\Platform\Reservation\Services\SeatAvailabilityService::class)->ueberbelegtBeiTerminbindung($event)
            : [];
    }

    /**
     * Tische der aktuell gewählten Räume (zum Sperren je Termin),
     * nach Tischplan gruppiert.
     */
    #[Computed]
    public function roomTables(): \Illuminate\Database\Eloquent\Collection
    {
        $planIds = collect($this->rooms)->pluck('floor_plan_id')->filter()->unique()->values();

        if ($planIds->isEmpty()) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        return FloorPlan::with(['tables' => fn ($q) => $q->where('is_active', true)->orderBy('label')])
            ->whereIn('id', $planIds)
            ->get();
    }

    /**
     * Veranstaltungen aus dem platforms-events-Modul (nur wenn installiert).
     */
    #[Computed]
    public function linkableEventsEvents(): array
    {
        if (!class_exists(\Platform\Events\Models\Event::class)) {
            return [];
        }

        try {
            return \Platform\Events\Models\Event::query()
                ->where('team_id', $this->getTeamId())
                ->orderByDesc('start_date')
                ->limit(100)
                ->get(['id', 'uuid', 'name', 'start_date'])
                ->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function openForm(?int $id = null): void
    {
        $this->showForm = true;
        $this->editingEventId = $id;
        $this->eventImage = null;
        $this->resetErrorBag();

        if ($id) {
            $event = Event::with(['slots', 'eventRooms', 'eventStations.slots'])->findOrFail($id);
            $this->eventName          = $event->name;
            $this->eventDescription   = $event->description ?? '';
            $this->eventDate          = $event->date->toDateString();
            $this->eventDeadline      = $event->order_deadline_at?->format('Y-m-d\TH:i') ?? '';
            $this->eventVenueId       = $event->venue_id;
            $this->eventSalesListId   = $event->sales_list_id;
            $this->eventReleaseMode   = $event->room_release_mode;
            $this->eventMaxGuestCount = $event->max_guest_count !== null ? (string) $event->max_guest_count : '';
            $this->eventTableBinding  = $event->table_binding ?? '';
            $this->eventEventsEventId = $event->events_event_id;

            $this->slots = $event->slots->map(fn (EventSlot $slot) => [
                'id'         => $slot->id,
                'name'       => $slot->name,
                'time_start' => substr($slot->time_start, 0, 5),
                'time_end'   => $slot->time_end ? substr($slot->time_end, 0, 5) : '',
            ])->toArray();

            $this->rooms = $event->eventRooms->map(fn (EventRoom $room) => [
                'id'                     => $room->id,
                'floor_plan_id'          => $room->floor_plan_id,
                'fill_threshold_percent' => $room->fill_threshold_percent,
                'capacity_override'      => $room->capacity_override !== null ? (string) $room->capacity_override : '',
                'open_mode'              => $room->is_open_override === null ? 'auto' : ($room->is_open_override ? 'open' : 'closed'),
            ])->toArray();

            // Ids der Pausen auf ihre Position abbilden - im Formular wird mit
            // Positionen gearbeitet, siehe addStation().
            $positionJeSlotId = array_flip(array_column($this->slots, 'id'));

            $this->stations = $event->eventStations->map(fn ($zuordnung) => [
                'id'                => $zuordnung->id,
                'pickup_station_id' => $zuordnung->pickup_station_id,
                'capacity_override' => $zuordnung->capacity_override !== null ? (string) $zuordnung->capacity_override : '',
                'slot_indexes'      => $zuordnung->slots
                    ->map(fn ($slot) => $positionJeSlotId[$slot->id] ?? null)
                    ->filter(fn ($p) => $p !== null)
                    ->values()
                    ->all(),
            ])->toArray();

            $this->disabledTableIds = array_map('intval', $event->disabled_table_ids ?? []);
        } else {
            $this->eventName          = '';
            $this->eventDescription   = '';
            $this->eventDate          = '';
            $this->eventDeadline      = '';
            $this->eventVenueId       = null;
            $this->eventSalesListId   = null;
            $this->eventEventsEventId = null;
            $this->eventMaxGuestCount = '';
            $this->eventTableBinding  = '';
            // Standard-Raumfreigabe aus den Einstellungen vorbelegen.
            $this->eventReleaseMode = \Platform\Reservation\Models\CheckoutSetting::forTeam($this->getTeamId())->defaultRoomReleaseMode();
            $this->slots = [['id' => null, 'name' => 'Pause', 'time_start' => '', 'time_end' => '']];
            $this->rooms = [];
            $this->stations = [];
            $this->disabledTableIds = [];
        }
    }

    /* ---------------------------------------------------------------------
     | Abholstationen am Termin
     |
     | Der Zwilling des Raum-Blocks, mit einem Unterschied, der alles Weitere
     | erklaert: Eine Station gilt JE PAUSE. Deshalb steht in jeder Zeile nicht
     | nur die Station, sondern auch, in welchen Pausen sie geoeffnet ist.
     |
     | Gemerkt werden dabei die POSITIONEN der Pausen, nicht ihre Ids. Beim
     | Anlegen eines Termins gibt es die Ids naemlich noch nicht - die Pausen
     | entstehen erst beim Speichern. Ueber die Position laesst sich beides
     | verbinden, und syncStations() loest sie danach auf.
     --------------------------------------------------------------------- */

    public function addStation(): void
    {
        $this->stations[] = [
            'id'                => null,
            'pickup_station_id' => null,
            'capacity_override' => '',
            // Beim Anlegen alle Pausen. Eine Station, die nur in einer Pause
            // oeffnet, ist die Ausnahme - und die haekelt man ab.
            'slot_indexes'      => array_keys($this->slots),
        ];
    }

    public function removeStation(int $index): void
    {
        unset($this->stations[$index]);
        $this->stations = array_values($this->stations);
    }

    public function toggleStationSlot(int $stationIndex, int $slotIndex): void
    {
        $gesetzt = $this->stations[$stationIndex]['slot_indexes'] ?? [];

        $this->stations[$stationIndex]['slot_indexes'] = in_array($slotIndex, $gesetzt, true)
            ? array_values(array_diff($gesetzt, [$slotIndex]))
            : array_values(array_merge($gesetzt, [$slotIndex]));
    }

    public function toggleDisabledTable(int $tableId): void
    {
        if (in_array($tableId, $this->disabledTableIds, true)) {
            $this->disabledTableIds = array_values(array_diff($this->disabledTableIds, [$tableId]));
        } else {
            $this->disabledTableIds[] = $tableId;
        }
    }

    public function addSlot(): void
    {
        $this->slots[] = [
            'id'         => null,
            'name'       => 'Pause ' . (count($this->slots) + 1),
            'time_start' => '',
            'time_end'   => '',
        ];

        // Neue Pause: bei allen Stationen mit angehakt. Der haeufigere Fall ist,
        // dass eine Station den ganzen Abend ausgibt; wer es anders will,
        // nimmt das Haekchen weg. Eine neue Pause, die ueberall stumm fehlt,
        // faellt dagegen erst am Abend auf.
        $neu = count($this->slots) - 1;

        foreach ($this->stations as $i => $station) {
            $this->stations[$i]['slot_indexes'][] = $neu;
        }
    }

    public function removeSlot(int $index): void
    {
        unset($this->slots[$index]);
        $this->slots = array_values($this->slots);

        // Die Stationen merken sich Positionen, und die verschieben sich beim
        // Entfernen. Ohne diese Zeilen zeigte eine Station danach auf die
        // falsche Pause - lautlos, denn es bleibt eine gueltige Zahl.
        foreach ($this->stations as $i => $station) {
            $this->stations[$i]['slot_indexes'] = array_values(array_map(
                fn (int $p) => $p > $index ? $p - 1 : $p,
                array_filter($station['slot_indexes'] ?? [], fn (int $p) => $p !== $index),
            ));
        }
    }

    public function addRoom(): void
    {
        $this->rooms[] = [
            'id'                     => null,
            'floor_plan_id'          => null,
            'fill_threshold_percent' => 100,
            'capacity_override'      => '',
            'open_mode'              => 'auto',
        ];
    }

    /* ---------------------------------------------------------------------
     | Raum aus dem Termin nehmen
     |
     | Ein Raum ohne Buchungen fliegt sofort raus - da gibt es nichts zu fragen.
     | Liegen dort schon Buchungen, wird gefragt, und zwar mit Zahlen: Die
     | Buchungen bleiben zwar bestehen, fallen aber aus der Auslastung heraus,
     | weil sie auf einen Raum zeigen, der nicht mehr zum Termin gehört. Dieselbe
     | Art Loch wie bei einem gelöschten Tisch.
     |
     | Als eigenes Modal und nicht über wire:confirm: Der Browser-Dialog sieht
     | fremd aus und kann vor allem nichts erklären - hier steht die Zahl, und
     | hier steht die Alternative. Denn meistens ist "Geschlossen" gemeint:
     | keine neuen Buchungen, bestehende behalten.
     --------------------------------------------------------------------- */

    public bool $showRoomRemoveConfirm = false;

    /** Index der Zeile, um die es in der Rückfrage geht. */
    public ?int $roomRemoveIndex = null;

    /** Wie viele aktive Buchungen an diesem Raum hängen. */
    public int $roomRemoveBookings = 0;

    public function removeRoom(int $index): void
    {
        $anzahl = $this->raumBuchungen($index);

        if ($anzahl < 1) {
            $this->raumZeileEntfernen($index);

            return;
        }

        $this->roomRemoveIndex    = $index;
        $this->roomRemoveBookings = $anzahl;
        $this->showRoomRemoveConfirm = true;
    }

    /** Trotzdem entfernen. */
    public function removeRoomAndCloseModal(): void
    {
        if ($this->roomRemoveIndex !== null) {
            $this->raumZeileEntfernen($this->roomRemoveIndex);
        }

        $this->closeRoomRemoveModal();
    }

    /** Der übliche Fall: nicht entfernen, sondern schließen. */
    public function closeRoomInsteadAndCloseModal(): void
    {
        if ($this->roomRemoveIndex !== null && isset($this->rooms[$this->roomRemoveIndex])) {
            $this->rooms[$this->roomRemoveIndex]['open_mode'] = 'closed';
        }

        $this->closeRoomRemoveModal();
    }

    public function closeRoomRemoveModal(): void
    {
        $this->showRoomRemoveConfirm = false;
        $this->roomRemoveIndex    = null;
        $this->roomRemoveBookings = 0;
    }

    /**
     * Name des Raums in der Rückfrage.
     *
     * Direkt am Tischplan nachgeschlagen und nicht über planName(): Das liest
     * aus der Auswahlliste des Formulars und hat einen Ersatztext, der zum
     * Prozentsatz gehört („den nächsten Raum") – in einer Überschrift steht das
     * schief.
     */
    public function roomRemoveName(): string
    {
        $planId = $this->roomRemoveIndex !== null
            ? (int) ($this->rooms[$this->roomRemoveIndex]['floor_plan_id'] ?? 0)
            : 0;

        return ($planId ? FloorPlan::find($planId)?->name : null) ?? 'Dieser Raum';
    }

    protected function raumZeileEntfernen(int $index): void
    {
        unset($this->rooms[$index]);
        $this->rooms = array_values($this->rooms);
    }

    /**
     * Aktive Buchungen dieses Termins auf Tischen des Raums.
     *
     * Stornos und No-Shows zählen nicht mit - die halten niemanden auf.
     * Ohne gespeicherten Termin gibt es noch keine Buchungen; eine gerade erst
     * hinzugefügte Zeile hat auch keine.
     */
    protected function raumBuchungen(int $index): int
    {
        $planId = (int) ($this->rooms[$index]['floor_plan_id'] ?? 0);

        if (! $this->editingEventId || ! $planId) {
            return 0;
        }

        return Booking::where('event_id', $this->editingEventId)
            ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW])
            ->whereIn('table_id', Table::where('floor_plan_id', $planId)->pluck('id'))
            ->count();
    }

    /** Raum-Default-Liste als Vorbelegung ziehen, wenn noch keine Liste gewählt. */
    public function updatedRooms($value, $key): void
    {
        if (!str_ends_with((string) $key, 'floor_plan_id') || $this->eventSalesListId || !$value) {
            return;
        }

        $plan = FloorPlan::find((int) $value);
        if ($plan?->default_sales_list_id) {
            $this->eventSalesListId = $plan->default_sales_list_id;
        }
    }

    public function save(): void
    {
        $this->validate([
            'eventName'          => 'required|string|max:255',
            'eventDate'          => 'required|date',
            // Ohne Frist bliebe ein Termin ewig bestellbar – auch nach dem Abend.
            'eventDeadline'      => 'required|date',
            'eventMaxGuestCount' => 'nullable|integer|min:1|max:200',
            'eventTableBinding'  => ['nullable', Rule::in(['', Event::BINDING_EVENT, Event::BINDING_SLOT])],
            'eventVenueId'       => ['nullable', 'integer', Rule::exists('reservation_venues', 'id')->where('team_id', $this->getTeamId())],
            'eventSalesListId'   => ['nullable', 'integer', Rule::exists('reservation_sales_lists', 'id')->where('team_id', $this->getTeamId())],
            'eventReleaseMode'   => 'required|in:parallel,sequential',
            'eventImage'         => 'nullable|image|max:20480',
            // #518: Pausen sind optional – ein Termin ist auch ohne Pausenangabe speicherbar.
            'slots'              => 'nullable|array',
            'slots.*.name'       => 'required|string|max:255',
            'slots.*.time_start' => 'nullable|date_format:H:i',
            'slots.*.time_end'   => 'nullable|date_format:H:i',
            // distinct: Auf event_id + floor_plan_id liegt ein eindeutiger Index.
            // Ohne diese Regel endet ein zweimal gewählter Raum nicht in einer
            // Meldung, sondern in einem Serverfehler beim Speichern.
            'rooms.*.floor_plan_id' => ['required', 'integer', 'distinct', Rule::exists('reservation_floor_plans', 'id')->where('team_id', $this->getTeamId())],
            'rooms.*.fill_threshold_percent' => 'required|integer|min:1|max:100',
            'rooms.*.capacity_override' => 'nullable|integer|min:1',
            // distinct wie beim Raum: Auf event_id + pickup_station_id liegt
            // ein eindeutiger Index; ohne die Regel gaebe es einen Serverfehler
            // statt einer Meldung.
            'stations.*.pickup_station_id' => ['required', 'integer', 'distinct', Rule::exists('reservation_pickup_stations', 'id')->where('team_id', $this->getTeamId())],
            'stations.*.capacity_override' => 'nullable|integer|min:1',
        ], [
            'eventDeadline.required' => 'Jeder Termin braucht einen Bestellschluss.',
            'slots.*.name.required' => 'Jede Pause braucht einen Namen.',
            'rooms.*.floor_plan_id.required' => 'Jeder Raum braucht einen Tischplan.',
            'rooms.*.floor_plan_id.distinct' => 'Dieser Tischplan ist schon als Raum eingetragen.',
            'stations.*.pickup_station_id.required' => 'Jede Zeile braucht eine Abholstation.',
            'stations.*.pickup_station_id.distinct' => 'Diese Abholstation ist schon eingetragen.',
        ]);

        // Eine Station ohne Pause waere ein stiller Zustand: Sie stuende im
        // Termin und erschiene im Shop nirgends. Deshalb ein Fehler und nicht
        // die stillschweigende Annahme „dann eben alle".
        // Und die Station muss zum Haus des Termins gehoeren. Die Liste im
        // Formular zeigt ohnehin nur passende - das hier faengt den Fall ab,
        // dass jemand das Venue wechselt, nachdem er eine Station gewaehlt hat.
        $venueId  = $this->wirksamesVenueId();
        $erlaubte = $venueId
            ? PickupStation::forTeam($this->getTeamId())->where('venue_id', $venueId)->pluck('id')->all()
            : null;

        foreach ($this->stations as $i => $station) {
            if (empty($station['slot_indexes'])) {
                $this->addError("stations.$i.slot_indexes", 'Diese Abholstation braucht mindestens eine Pause.');

                return;
            }

            if ($erlaubte !== null && ! in_array((int) $station['pickup_station_id'], $erlaubte, true)) {
                $this->addError(
                    "stations.$i.pickup_station_id",
                    'Diese Abholstation gehört zu einem anderen Haus als der Termin.'
                );

                return;
            }
        }

        // #518: Wenn beide Zeiten gesetzt sind, muss das Ende nach dem Beginn liegen.
        foreach ($this->slots as $i => $slot) {
            if (! empty($slot['time_start']) && ! empty($slot['time_end']) && $slot['time_end'] <= $slot['time_start']) {
                $this->addError("slots.$i.time_end", 'Das Pausenende muss nach dem Beginn liegen.');

                return;
            }
        }

        // Gesperrte Tische auf die Tische der aktuell gewählten Räume eingrenzen
        // (entfernt verwaiste IDs, wenn ein Raum wieder entfernt wurde).
        $validTableIds = $this->roomTables->flatMap->tables->pluck('id')->all();
        $disabledTableIds = array_values(array_intersect(
            array_map('intval', $this->disabledTableIds),
            $validTableIds
        ));

        $data = [
            'team_id'            => $this->getTeamId(),
            'name'               => $this->eventName,
            'description'        => $this->eventDescription ?: null,
            'date'               => $this->eventDate,
            'order_deadline_at'  => $this->eventDeadline,
            'venue_id'           => $this->eventVenueId,
            'sales_list_id'      => $this->eventSalesListId,
            'room_release_mode'  => $this->eventReleaseMode,
            'max_guest_count'    => $this->eventMaxGuestCount !== '' ? (int) $this->eventMaxGuestCount : null,
            'table_binding'      => $this->eventTableBinding !== '' ? $this->eventTableBinding : null,
            'disabled_table_ids' => $disabledTableIds,
            'events_event_id'    => $this->eventEventsEventId,
            'events_event_uuid'  => $this->resolveEventsEventUuid(),
        ];

        // Venue automatisch aus dem ersten Raum ableiten, falls keins gewählt
        if (!$data['venue_id'] && !empty($this->rooms)) {
            $firstPlanId = $this->rooms[0]['floor_plan_id'] ?? null;
            $data['venue_id'] = $firstPlanId
                ? FloorPlan::find((int) $firstPlanId)?->venue_id
                : null;
        }

        if ($this->editingEventId) {
            $event = Event::findOrFail($this->editingEventId);
            $event->update($data);
        } else {
            $event = Event::create($data);
        }

        $this->syncSlots($event);
        $this->syncRooms($event);

        // NACH den Pausen: syncStations loest die gemerkten Positionen in Ids
        // auf, und die entstehen erst dort.
        if (($fehler = $this->syncStations($event)) !== null) {
            session()->flash('event_error', $fehler);
        }

        if ($this->eventImage) {
            $event->setContextImage($this->eventImage, 'reservation.event.image', $this->getTeamId(), Auth::id());
            $this->eventImage = null;
        }

        // Der gespeicherte Termin muss danach auch sichtbar sein. Fällt er durch
        // den aktiven Filter – ein neuer ist Entwurf, der Filter steht auf
        // "Veröffentlicht" –, würde er kommentarlos verschwinden. Also Filter so
        // weit öffnen, dass er drin ist.
        $this->revealEvent($event);

        $this->showForm = false;
        $this->editingEventId = null;

        unset($this->events, $this->hasAnyEvents);
    }

    /** Filter auf "alles zeigen" stellen (Ausweg aus einer leeren Trefferliste). */
    public function resetFilters(): void
    {
        $this->statusFilter = 'all';
        $this->timeFilter   = 'all';

        unset($this->events);
    }

    /**
     * Status-Filter setzen.
     *
     * „Nachzubereiten" meint ausschließlich Vergangenes. Bliebe die Zeit auf
     * „Kommend" stehen, wäre die Liste zwangsläufig leer – der Nutzer sähe
     * einen leeren Bildschirm und keinen Grund dafür.
     */
    public function setStatusFilter(string $value): void
    {
        $this->statusFilter = $value;

        if ($value === 'nachzubereiten' && $this->timeFilter === 'upcoming') {
            $this->timeFilter = 'all';
        }

        unset($this->events);
    }

    /** Filter so anpassen, dass der übergebene Termin in der Liste erscheint. */
    protected function revealEvent(Event $event): void
    {
        // status ist ein EventStatus-Enum – ohne ->value verglichen wäre es nie
        // gleich, und die Zuweisung an die string-Property würfe einen TypeError.
        $status = $event->status instanceof \BackedEnum
            ? (string) $event->status->value
            : (string) $event->status;

        if ($this->statusFilter !== 'all' && $this->statusFilter !== $status) {
            $this->statusFilter = $status;
        }

        $isPast = $event->istVergangen();

        if ($this->timeFilter === 'upcoming' && $isPast) {
            $this->timeFilter = 'all';
        } elseif ($this->timeFilter === 'past' && ! $isPast) {
            $this->timeFilter = 'all';
        }
    }

    protected function resolveEventsEventUuid(): ?string
    {
        if (!$this->eventEventsEventId || !class_exists(\Platform\Events\Models\Event::class)) {
            return null;
        }

        return \Platform\Events\Models\Event::find($this->eventEventsEventId)?->uuid;
    }

    protected function syncSlots(Event $event): void
    {
        $keptIds = [];

        foreach (array_values($this->slots) as $sortOrder => $slot) {
            $attributes = [
                'name'       => $slot['name'],
                'time_start' => $slot['time_start'] ?: null,
                'time_end'   => $slot['time_end'] ?: null,
                'sort_order' => $sortOrder,
            ];

            if ($slot['id']) {
                $event->slots()->whereKey($slot['id'])->first()?->update($attributes);
                $keptIds[] = $slot['id'];
            } else {
                $keptIds[] = $event->slots()->create($attributes)->id;
            }
        }

        $event->slots()->whereNotIn('id', $keptIds)->delete();
    }

    protected function syncRooms(Event $event): void
    {
        $keptIds = [];

        foreach (array_values($this->rooms) as $sortOrder => $room) {
            $attributes = [
                'floor_plan_id'          => $room['floor_plan_id'],
                'sort_order'             => $sortOrder,
                'fill_threshold_percent' => $room['fill_threshold_percent'],
                'capacity_override'      => $room['capacity_override'] !== '' ? (int) $room['capacity_override'] : null,
                'is_open_override'       => match ($room['open_mode']) {
                    'open'   => true,
                    'closed' => false,
                    default  => null,
                },
            ];

            if ($room['id']) {
                $event->eventRooms()->whereKey($room['id'])->first()?->update($attributes);
                $keptIds[] = $room['id'];
            } else {
                $keptIds[] = $event->eventRooms()->create($attributes)->id;
            }
        }

        $event->eventRooms()->whereNotIn('id', $keptIds)->delete();
    }

    /**
     * Abholstationen des Termins abgleichen – samt ihrer Pausen.
     *
     * Läuft nach syncSlots(), weil die Pausen eines neuen Termins dort erst
     * ihre Ids bekommen. Das Formular arbeitet mit Positionen; hier werden sie
     * aufgelöst.
     *
     * Wegnehmen ist die heikle Richtung: Eine Station oder eine ihrer Pausen,
     * auf die schon Buchungen zeigen, wird NICHT entfernt. Sonst zeigte die
     * Buchung auf einen Ort, den es für sie nicht mehr gibt – dieselbe Art Loch
     * wie bei einem gelöschten Tisch, nur ohne dass jemand es merkt. Der Rest
     * wird gespeichert; gemeldet wird, was stehen blieb.
     *
     * @return string|null Meldung, wenn etwas nicht entfernt werden konnte
     */
    protected function syncStations(Event $event): ?string
    {
        $slotIdJePosition = $event->slots()->orderBy('sort_order')->pluck('id')->all();

        $keptIds  = [];
        $behalten = [];

        foreach (array_values($this->stations) as $sortOrder => $zeile) {
            $attribute = [
                'pickup_station_id' => (int) $zeile['pickup_station_id'],
                'sort_order'        => $sortOrder,
                'capacity_override' => ($zeile['capacity_override'] ?? '') !== '' ? (int) $zeile['capacity_override'] : null,
            ];

            $zuordnung = $zeile['id']
                ? $event->eventStations()->whereKey($zeile['id'])->first()
                : null;

            if ($zuordnung) {
                $zuordnung->update($attribute);
            } else {
                $zuordnung = $event->eventStations()->create($attribute);
            }

            $keptIds[] = $zuordnung->id;

            $gewuenscht = collect($zeile['slot_indexes'] ?? [])
                ->map(fn ($position) => $slotIdJePosition[$position] ?? null)
                ->filter()
                ->map('intval')
                ->all();

            // Eine Pause, auf der Buchungen liegen, bleibt drin - auch wenn das
            // Haekchen weg ist.
            $zuordnung->load('slots');

            foreach ($zuordnung->slots as $bisher) {
                if (! in_array($bisher->id, $gewuenscht, true) && $zuordnung->hatBuchungen($bisher->id)) {
                    $gewuenscht[] = $bisher->id;
                    $behalten[]   = $zuordnung->station?->name . ' / ' . $bisher->displayLabel();
                }
            }

            $zuordnung->slots()->sync($gewuenscht);
        }

        // Ganz entfernte Stationen - dieselbe Regel.
        foreach ($event->eventStations()->whereNotIn('id', $keptIds ?: [0])->with('station')->get() as $weg) {
            if ($weg->hatBuchungen()) {
                $behalten[] = $weg->station?->name;

                continue;
            }

            $weg->delete();
        }

        return $behalten === []
            ? null
            : 'Nicht entfernt, weil dort schon Buchungen liegen: ' . implode(', ', array_filter($behalten))
                . '. Bitte die Buchungen erst stornieren oder verschieben.';
    }

    public function publish(int $id): void
    {
        $event = Event::with('slots')->findOrFail($id);

        // Die Regel steht am Modell, damit Oberfläche und MCP-Werkzeug dieselbe
        // benutzen – siehe Event::fehltZumVeroeffentlichen().
        $fehlt = $event->fehltZumVeroeffentlichen();

        if ($fehlt !== []) {
            session()->flash('event_error', 'Zum Veröffentlichen fehlt dem Termin noch: ' . implode(' und ', $fehlt) . '.');
            return;
        }

        $event->update(['status' => Event::STATUS_PUBLISHED]);
        unset($this->events);
    }

    public function unpublish(int $id): void
    {
        Event::findOrFail($id)->update(['status' => Event::STATUS_DRAFT]);
        unset($this->events);
    }

    /**
     * Ankündigen: Der Termin steht ab jetzt im Shop, bestellen kann noch
     * niemand. Bewusst ohne die Pausen-/Raum-Prüfung des Veröffentlichens –
     * angekündigt wird, sobald das Datum steht, die Pausen kommen später.
     */
    public function announce(int $id): void
    {
        Event::findOrFail($id)->update(['status' => Event::STATUS_ANNOUNCED]);
        unset($this->events);
    }

    public function close(int $id): void
    {
        Event::findOrFail($id)->update(['status' => Event::STATUS_CLOSED]);
        unset($this->events);
    }

    public function cancel(int $id): void
    {
        Event::findOrFail($id)->update(['status' => Event::STATUS_CANCELLED]);
        unset($this->events);
    }

    /**
     * Termin als Entwurf duplizieren – inkl. Slots, Räumen und gesperrten
     * Tischen, aber OHNE Buchungen, Veröffentlichungsstatus, Bild und
     * Events-Modul-Verknüpfung.
     */
    public function duplicate(int $id): void
    {
        $original = Event::with(['slots', 'eventRooms', 'eventStations.slots'])
            ->forTeam($this->getTeamId())
            ->findOrFail($id);

        \Illuminate\Support\Facades\DB::transaction(function () use ($original) {
            // Der Freigabe-Link darf NICHT mitkopiert werden - aus zwei Gründen.
            //
            // Der harmlosere: share_token ist eindeutig, die Kopie lief mit
            // einer Verletzung der Eindeutigkeit auf die Nase.
            //
            // Der ernste: Wäre die Spalte nicht eindeutig, hätte die Kopie
            // denselben Link wie das Original. Wer den alten Link hat - ein
            // Veranstaltungsleiter von letztem Jahr, jemand, an den er
            // weitergereicht wurde -, sähe Küche und Laufzettel des neuen
            // Termins mit. Ein Link, den man einmal ausgegeben hat, soll nicht
            // durch das Kopieren eines Termins neue Türen aufbekommen.
            //
            // Die Kopie startet ohne Freigabe; wer sie braucht, erzeugt sie
            // dort neu.
            $copy = $original->replicate([
                'uuid', 'status', 'image_context_file_id', 'events_event_id', 'events_event_uuid',
                'share_token', 'share_pin_hash', 'share_created_at',
            ]);
            $copy->name = $original->name . ' (Kopie)';
            $copy->status = Event::STATUS_DRAFT;
            $copy->save();

            foreach ($original->slots as $slot) {
                $copy->slots()->create($slot->only(['name', 'time_start', 'time_end', 'sort_order']));
            }

            foreach ($original->eventRooms as $room) {
                $copy->eventRooms()->create($room->only([
                    'floor_plan_id', 'sort_order', 'fill_threshold_percent', 'capacity_override', 'is_open_override',
                ]));
            }

            // Stationen samt Pausen. Die Kopie hat eigene Pausen - zugeordnet
            // wird ueber die POSITION, nicht ueber die Id: Ein Termin mit zwei
            // Pausen, dessen Station nur in der ersten oeffnet, soll in der
            // Kopie genauso aussehen.
            $kopiePausen = $copy->slots()->orderBy('sort_order')->pluck('id')->all();
            $originalPausen = array_flip($original->slots->sortBy('sort_order')->pluck('id')->all());

            foreach ($original->eventStations as $zuordnung) {
                $neu = $copy->eventStations()->create($zuordnung->only([
                    'pickup_station_id', 'sort_order', 'capacity_override',
                ]));

                $neu->slots()->sync(
                    $zuordnung->slots
                        ->map(fn ($slot) => $kopiePausen[$originalPausen[$slot->id] ?? -1] ?? null)
                        ->filter()
                        ->all()
                );
            }
        });

        unset($this->events);
        session()->flash('event_message', 'Termin als Entwurf dupliziert.');
    }

    public function delete(int $id): void
    {
        Event::findOrFail($id)->delete();
        unset($this->events);
    }

    public function render()
    {
        return view('reservation::livewire.event-manager')
            ->layout('platform::layouts.app');
    }
}
