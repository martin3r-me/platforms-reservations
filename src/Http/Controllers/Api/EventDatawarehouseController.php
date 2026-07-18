<?php

namespace Platform\Reservation\Http\Controllers\Api;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Platform\Core\Http\Controllers\ApiController;
use Platform\Core\Models\Team;
use Platform\Reservation\Enums\EventStatus;
use Platform\Reservation\Models\Event;

/**
 * Datawarehouse-API-Controller für Termine (Veranstaltungen).
 *
 * Stellt – wie helpdesk/planner – eine token-gesicherte Read-API bereit, die vom
 * zentralen Datawarehouse abgeholt wird. Standardmäßig werden alle zukünftigen und
 * nicht geschlossenen Termine geliefert, inkl. Anzahl Pausen sowie den zu buchenden
 * Räumen mit Kapazitäten.
 *
 * Team-Scoping bewusst ohne Auth-Global-Scope (withoutGlobalScope), da im
 * api.auth-Kontext Auth::user()->currentTeam abweichen kann; gefiltert wird –
 * wie im üblichen Muster – optional per team_id (inkl. Kind-Teams).
 */
class EventDatawarehouseController extends ApiController
{
    /**
     * Flexibler Datawarehouse-Endpunkt für Termine.
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

        // Status: standardmäßig "not closed" ausschließen; per status-Parameter überschreibbar.
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } elseif (! $request->boolean('include_closed')) {
            $query->where('status', '!=', EventStatus::Closed->value);
        }
    }

    /**
     * Datawarehouse-freundliches, denormalisiertes Format eines Termins.
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

    /**
     * Health-Check: liefert einen Beispiel-Datensatz für Tests.
     */
    public function health(Request $request)
    {
        try {
            $example = Event::withoutGlobalScope('team')
                ->with([
                    'venue'                => fn ($q) => $q->withoutGlobalScope('team'),
                    'eventRooms.floorPlan' => fn ($q) => $q->withoutGlobalScope('team'),
                    'eventRooms.floorPlan.tables' => fn ($q) => $q->withoutGlobalScope('team')->where('is_active', true),
                ])
                ->withCount(['slots', 'eventRooms'])
                ->whereDate('date', '>=', Carbon::today())
                ->orderBy('date')
                ->first();

            return $this->success([
                'status'    => 'ok',
                'message'   => $example ? 'API ist erreichbar' : 'API ist erreichbar, aber keine zukünftigen Termine vorhanden',
                'example'   => $example ? $this->formatEvent($example) : null,
                'timestamp' => now()->toIso8601String(),
            ], 'Health Check');
        } catch (\Throwable $e) {
            return $this->error('Health Check fehlgeschlagen: ' . $e->getMessage(), null, 500);
        }
    }
}
