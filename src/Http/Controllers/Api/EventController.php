<?php

namespace Platform\Reservation\Http\Controllers\Api;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Platform\Core\Http\Controllers\ApiController;
use Platform\Core\Models\Team;
use Platform\Reservation\Enums\EventStatus;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Models\MenuItem;
use Platform\Reservation\Models\SalesList;
use Platform\Reservation\Services\SeatAvailabilityService;

/**
 * Token-gesicherte Read-API für Termine (Veranstaltungen).
 *
 * Gleiches Grundmuster wie helpdesk/planner (ApiController + Passport api.auth),
 * jedoch ein fachlicher Events-Endpunkt (kein Datawarehouse-Feed). Standardmäßig
 * werden alle zukünftigen und nicht geschlossenen Termine geliefert, inkl. Anzahl
 * Pausen sowie den zu buchenden Räumen mit Kapazitäten.
 *
 * Team-Scoping bewusst ohne Auth-Global-Scope (withoutGlobalScope), da im
 * api.auth-Kontext Auth::user()->currentTeam abweichen kann; gefiltert wird
 * optional per team_id (inkl. Kind-Teams).
 */
class EventController extends ApiController
{
    /**
     * GET /events – Liste der Termine (paginiert, gefiltert).
     */
    public function index(Request $request)
    {
        $query = Event::withoutGlobalScope('team');

        $this->applyFilters($query, $request);

        // ===== SORTING =====
        $sortBy  = $request->get('sort_by', 'date');
        $sortDir = $request->get('sort_dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $allowedSortColumns = ['id', 'date', 'created_at', 'updated_at', 'order_deadline_at', 'name'];
        $query->orderBy(in_array($sortBy, $allowedSortColumns, true) ? $sortBy : 'date', $sortDir);

        // ===== EAGER LOADS (scope-sicher) + COUNTS =====
        $query->with([
            'venue'                => fn ($q) => $q->withoutGlobalScope('team'),
            'eventRooms.floorPlan' => fn ($q) => $q->withoutGlobalScope('team'),
            'eventRooms.floorPlan.tables' => fn ($q) => $q->withoutGlobalScope('team')->where('is_active', true),
        ])->withCount([
            'slots',
            'eventRooms',
        ]);

        // ===== PAGINATION =====
        $perPage = min((int) $request->get('per_page', 100), 1000);
        $events  = $query->paginate($perPage);

        $formatted = $events->map(fn (Event $event) => $this->formatEvent($event));

        return $this->paginated(
            $events->setCollection($formatted),
            'Termine erfolgreich geladen'
        );
    }

    /**
     * GET /events/{event}/products – buchbare Artikel eines Termins.
     *
     * Die Artikel ergeben sich aus der Verkaufsliste des Termins (bzw. dem
     * Team-Default) und sind für alle Pausen des Termins identisch – daher ist
     * keine Slot-ID nötig. {event} kann UUID oder numerische ID sein.
     */
    public function products(Request $request, string $event)
    {
        $model = $this->resolveEvent($event);

        if (! $model) {
            return $this->notFound('Termin nicht gefunden.');
        }

        $salesList = $this->resolveSalesList($model);
        $products  = $this->visibleItems($salesList)->map(fn (MenuItem $item) => $this->formatProduct($item));

        return $this->success([
            'event' => [
                'id'   => $model->id,
                'uuid' => $model->uuid,
                'name' => $model->name,
                'date' => $model->date?->format('Y-m-d'),
            ],
            'sales_list'    => $salesList?->name,
            'products_count' => $products->count(),
            'products'      => $products->values()->all(),
        ], 'Artikel erfolgreich geladen');
    }

    /**
     * GET /events/{event}/floor-plan – Tischplan(e) und Verfügbarkeit je Pause.
     *
     * Liefert die buchbaren Räume des Termins mit Tischplan und Tischen sowie die
     * freien Plätze JE PAUSE (Slot) und Tisch. Optional filterbar per room
     * (event_room_id oder floor_plan_id) und slot (Slot-ID).
     * {event} = UUID oder numerische ID.
     */
    public function floorPlan(Request $request, string $event)
    {
        $model = $this->resolveEvent($event);

        if (! $model) {
            return $this->notFound('Termin nicht gefunden.');
        }

        $model->loadMissing([
            'slots',
            'eventRooms.floorPlan'        => fn ($q) => $q->withoutGlobalScope('team'),
            'eventRooms.floorPlan.tables' => fn ($q) => $q->withoutGlobalScope('team')->where('is_active', true),
        ]);

        $roomFilter = $request->filled('room') ? (int) $request->room : null;
        $slotFilter = $request->filled('slot') ? (int) $request->slot : null;

        $slots = $model->slots
            ->when($slotFilter, fn ($c) => $c->where('id', $slotFilter))
            ->sortBy('sort_order')
            ->values();

        $seats = app(SeatAvailabilityService::class);

        $rooms = $model->eventRooms
            ->when($roomFilter, fn ($c) => $c->filter(
                fn ($room) => $room->id === $roomFilter || $room->floor_plan_id === $roomFilter
            ))
            ->map(fn ($room) => $this->formatRoomAvailability($room, $model, $slots, $seats))
            ->filter()
            ->values();

        return $this->success([
            'event' => [
                'id'   => $model->id,
                'uuid' => $model->uuid,
                'name' => $model->name,
                'date' => $model->date?->format('Y-m-d'),
            ],
            'slots' => $slots->map(fn ($s) => [
                'id'         => $s->id,
                'name'       => $s->name,
                'time_start' => $s->time_start,
                'time_end'   => $s->time_end,
            ])->values()->all(),
            'rooms' => $rooms->all(),
        ], 'Tischplan/Verfügbarkeit geladen');
    }

    /**
     * Ein Raum mit Tischplan, Tischen und Verfügbarkeit je Pause.
     */
    protected function formatRoomAvailability($room, Event $event, $slots, SeatAvailabilityService $seats): ?array
    {
        $floorPlan = $room->floorPlan;

        if (! $floorPlan) {
            return null;
        }

        $tables = $floorPlan->tables;

        // Restplätze je Pause und Tisch.
        $availability = [];
        foreach ($slots as $slot) {
            $bookedByTable = $seats->bookedSeatsByTable($floorPlan, $slot);
            foreach ($tables as $table) {
                $availability[$slot->id][$table->id] = $event->isTableDisabled($table->id)
                    ? 0
                    : max(0, (int) $table->capacity - (int) $bookedByTable->get($table->id, 0));
            }
        }

        return [
            'event_room_id' => $room->id,
            'floor_plan_id' => $room->floor_plan_id,
            'name'          => $floorPlan->name,
            'capacity'      => $room->capacity_override ?? (int) $tables->sum('capacity'),
            'floor_plan'    => [
                'background_url'      => $floorPlan->backgroundUrl(),
                'background_rotation' => $floorPlan->background_rotation,
                'aspect'             => $floorPlan->displayAspect(),
            ],
            'tables' => $tables->map(fn ($t) => [
                'id'          => $t->id,
                'label'       => $t->label,
                'capacity'    => (int) $t->capacity,
                'shape'       => $t->shape,
                'x_pct'       => (float) $t->x_pct,
                'y_pct'       => (float) $t->y_pct,
                'w_pct'       => (float) $t->w_pct,
                'h_pct'       => (float) $t->h_pct,
                'is_disabled' => $event->isTableDisabled($t->id),
            ])->values()->all(),
            'availability' => $availability, // { slot_id: { table_id: remaining } }
        ];
    }

    /**
     * Termin scope-sicher per UUID oder numerischer ID auflösen.
     */
    protected function resolveEvent(string $key): ?Event
    {
        $query = Event::withoutGlobalScope('team');

        return ctype_digit($key)
            ? $query->find((int) $key)
            : $query->where('uuid', $key)->first();
    }

    /**
     * Verkaufsliste des Termins (explizit oder Team-Default), scope-sicher.
     */
    protected function resolveSalesList(Event $event): ?SalesList
    {
        return $event->sales_list_id
            ? SalesList::withoutGlobalScope('team')->find($event->sales_list_id)
            : SalesList::withoutGlobalScope('team')
                ->where('team_id', $event->team_id)
                ->where('is_default', true)
                ->first();
    }

    /**
     * Gast-sichtbare Artikel (freigegeben + verfügbar) der Verkaufsliste.
     */
    protected function visibleItems(?SalesList $salesList)
    {
        if (! $salesList) {
            return collect();
        }

        return $salesList->menuItems()
            ->withoutGlobalScope('team')
            ->where('approval_status', MenuItem::APPROVAL_APPROVED)
            ->where('available', true)
            ->with(['allergens', 'additives', 'category', 'holdingClass', 'imageFile.variants'])
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Denormalisiertes Format eines Artikels.
     */
    protected function formatProduct(MenuItem $item): array
    {
        return [
            'id'               => $item->id,
            'name'             => $item->name,
            'description'      => $item->description,
            'portion_size'     => $item->portion_size,
            'price'            => (float) $item->price,
            'tax_rate'         => (float) $item->tax_rate,
            'category'         => $item->category?->name,
            'category_id'      => $item->category_id,
            'holding_class'    => $item->holdingClass?->name,
            'holding_class_id' => $item->holding_class_id,
            'is_vegetarian'    => $item->is_vegetarian,
            'is_vegan'         => $item->is_vegan,
            'is_alcoholic'     => $item->is_alcoholic,
            'allergens'        => $item->allergens->pluck('code')->values(),
            'additives'        => $item->additives->pluck('code')->values(),
            'image_url'        => $item->image_context_file_id ? $item->imageUrl('medium_1_1') : null,
            'sort_order'       => $item->sort_order,
        ];
    }

    /**
     * Wendet die Filter auf die Query an.
     */
    protected function applyFilters($query, Request $request): void
    {
        // Team-Filter (inkl. Kind-Teams, Default: true wenn team_id gesetzt)
        if ($request->filled('team_id')) {
            $teamId          = (int) $request->team_id;
            $includeChildren = $request->has('include_child_teams')
                ? $request->boolean('include_child_teams')
                : true;

            if ($includeChildren && ($team = Team::find($teamId))) {
                $query->whereIn('team_id', $team->getAllTeamIdsIncludingChildren());
            } else {
                $query->where('team_id', $teamId);
            }
        }

        // Zeitraum: standardmäßig nur zukünftige (Datum >= heute).
        $upcoming = $request->has('upcoming') ? $request->boolean('upcoming') : true;
        if ($upcoming) {
            $query->whereDate('date', '>=', Carbon::today());
        }
        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }

        // Status: standardmäßig geschlossene ("closed") ausschließen; per status-Parameter überschreibbar.
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } elseif (! $request->boolean('include_closed')) {
            $query->where('status', '!=', EventStatus::Closed->value);
        }
    }

    /**
     * Denormalisiertes Format eines Termins inkl. Pausen und buchbarer Räume.
     */
    protected function formatEvent(Event $event): array
    {
        $rooms = $event->eventRooms->map(function ($room) {
            $capacity = $room->capacity_override
                ?? (int) ($room->floorPlan?->tables->sum('capacity') ?? 0);

            return [
                'event_room_id' => $room->id,
                'floor_plan_id' => $room->floor_plan_id,
                'name'          => $room->floorPlan?->name,
                'capacity'      => (int) $capacity,
                'tables_count'  => $room->floorPlan?->tables->count() ?? 0,
                'sort_order'    => $room->sort_order,
            ];
        })->values();

        return [
            'id'                => $event->id,
            'uuid'              => $event->uuid,
            'name'              => $event->name,
            'date'              => $event->date?->format('Y-m-d'),
            'status'            => $event->status->value,
            'is_closed'         => $event->status === EventStatus::Closed,
            'orderable'         => $event->isOrderable(),
            'order_deadline_at' => $event->order_deadline_at?->toIso8601String(),
            'team_id'           => $event->team_id,
            'venue'             => $event->venue?->name,
            'pauses_count'      => $event->slots_count,
            'rooms_count'       => $event->event_rooms_count,
            'total_capacity'    => (int) $rooms->sum('capacity'),
            'rooms'             => $rooms->all(),
            'created_at'        => $event->created_at?->toIso8601String(),
            'updated_at'        => $event->updated_at?->toIso8601String(),
        ];
    }
}
