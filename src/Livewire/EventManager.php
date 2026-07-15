<?php

namespace Platform\Reservation\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Platform\Reservation\Models\Event;
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

    // Stammdaten
    public string $eventName = '';
    public string $eventDescription = '';
    public string $eventDate = '';
    public string $eventDeadline = '';
    public ?int $eventVenueId = null;
    public ?int $eventSalesListId = null;
    public string $eventReleaseMode = Event::RELEASE_PARALLEL;
    public ?int $eventEventsEventId = null;

    /** @var array<int, array{id: ?int, name: string, time_start: string, time_end: string}> */
    public array $slots = [];

    /** @var array<int, array{id: ?int, floor_plan_id: ?int, fill_threshold_percent: int, capacity_override: ?string, open_mode: string}> */
    public array $rooms = [];

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
        return Event::forTeam($this->getTeamId())
            ->with(['venue', 'salesList', 'slots'])
            ->withCount(['eventRooms', 'bookings'])
            ->orderBy('date')
            ->get();
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
            $event = Event::with(['slots', 'eventRooms'])->findOrFail($id);
            $this->eventName          = $event->name;
            $this->eventDescription   = $event->description ?? '';
            $this->eventDate          = $event->date->toDateString();
            $this->eventDeadline      = $event->order_deadline_at?->format('Y-m-d\TH:i') ?? '';
            $this->eventVenueId       = $event->venue_id;
            $this->eventSalesListId   = $event->sales_list_id;
            $this->eventReleaseMode   = $event->room_release_mode;
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

            $this->disabledTableIds = array_map('intval', $event->disabled_table_ids ?? []);
        } else {
            $this->eventName          = '';
            $this->eventDescription   = '';
            $this->eventDate          = '';
            $this->eventDeadline      = '';
            $this->eventVenueId       = null;
            $this->eventSalesListId   = null;
            $this->eventEventsEventId = null;
            // Standard-Raumfreigabe aus den Einstellungen vorbelegen.
            $this->eventReleaseMode = \Platform\Reservation\Models\CheckoutSetting::forTeam($this->getTeamId())->defaultRoomReleaseMode();
            $this->slots = [['id' => null, 'name' => 'Pause', 'time_start' => '', 'time_end' => '']];
            $this->rooms = [];
            $this->disabledTableIds = [];
        }
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
    }

    public function removeSlot(int $index): void
    {
        unset($this->slots[$index]);
        $this->slots = array_values($this->slots);
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

    public function removeRoom(int $index): void
    {
        unset($this->rooms[$index]);
        $this->rooms = array_values($this->rooms);
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
            'eventVenueId'       => 'nullable|integer|exists:reservation_venues,id',
            'eventSalesListId'   => 'nullable|integer|exists:reservation_sales_lists,id',
            'eventReleaseMode'   => 'required|in:parallel,sequential',
            'eventImage'         => 'nullable|image|max:20480',
            'slots'              => 'required|array|min:1',
            'slots.*.name'       => 'required|string|max:255',
            'slots.*.time_start' => 'required|date_format:H:i',
            'slots.*.time_end'   => 'nullable|date_format:H:i',
            'rooms.*.floor_plan_id' => 'required|integer|exists:reservation_floor_plans,id',
            'rooms.*.fill_threshold_percent' => 'required|integer|min:1|max:100',
            'rooms.*.capacity_override' => 'nullable|integer|min:1',
        ], [
            'slots.required' => 'Mindestens ein Pausen-Slot ist erforderlich.',
            'slots.*.time_start.required' => 'Jeder Slot braucht eine Beginn-Uhrzeit.',
            'rooms.*.floor_plan_id.required' => 'Jeder Raum braucht einen Tischplan.',
        ]);

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
            'order_deadline_at'  => $this->eventDeadline ?: null,
            'venue_id'           => $this->eventVenueId,
            'sales_list_id'      => $this->eventSalesListId,
            'room_release_mode'  => $this->eventReleaseMode,
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

        if ($this->eventImage) {
            $event->setContextImage($this->eventImage, 'reservation.event.image', $this->getTeamId(), Auth::id());
            $this->eventImage = null;
        }

        $this->showForm = false;
        $this->editingEventId = null;
        unset($this->events);
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
                'time_start' => $slot['time_start'],
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

    public function publish(int $id): void
    {
        $event = Event::with('slots')->findOrFail($id);

        if ($event->slots->isEmpty() || !$event->eventRooms()->exists()) {
            session()->flash('event_error', 'Zum Veröffentlichen braucht der Termin mindestens einen Pausen-Slot und einen Raum.');
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

    public function close(int $id): void
    {
        Event::findOrFail($id)->update(['status' => Event::STATUS_CLOSED]);
        unset($this->events);
    }

    /**
     * Termin als Entwurf duplizieren – inkl. Slots, Räumen und gesperrten
     * Tischen, aber OHNE Buchungen, Veröffentlichungsstatus, Bild und
     * Events-Modul-Verknüpfung.
     */
    public function duplicate(int $id): void
    {
        $original = Event::with(['slots', 'eventRooms'])
            ->forTeam($this->getTeamId())
            ->findOrFail($id);

        \Illuminate\Support\Facades\DB::transaction(function () use ($original) {
            $copy = $original->replicate([
                'uuid', 'status', 'image_context_file_id', 'events_event_id', 'events_event_uuid',
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
