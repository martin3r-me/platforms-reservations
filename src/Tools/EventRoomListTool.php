<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\Event;

/**
 * Listet die Räume (Tischpläne) eines Termins.
 */
class EventRoomListTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.event-rooms.GET';
    }

    public function getDescription(): string
    {
        return 'GET /reservation/event-rooms - Listet die zugewiesenen Räume (Tischpläne) eines Termins. '
            . 'REST-Parameter: event_uuid (Pflicht).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'event_uuid' => ['type' => 'string', 'description' => 'UUID des Termins.'],
            ],
            'required'   => ['event_uuid'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $context->team?->id;

            if (!$teamId) {
                return ToolResult::error('Kein Team-Kontext vorhanden.', 'MISSING_TEAM');
            }

            $event = Event::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->where('uuid', (string) ($arguments['event_uuid'] ?? ''))
                ->with(['eventRooms.floorPlan' => fn ($q) => $q->withoutGlobalScope('team')])
                ->first();

            if (!$event) {
                return ToolResult::error('Termin nicht gefunden.', 'NOT_FOUND');
            }

            $rooms = $event->eventRooms->map(fn ($r) => [
                'id'            => $r->id,
                'floor_plan_id' => $r->floor_plan_id,
                'floor_plan'    => $r->floorPlan?->name,
                'sort_order'    => $r->sort_order,
            ]);

            return ToolResult::success([
                'event_uuid' => $event->uuid,
                'count'      => $rooms->count(),
                'rooms'      => $rooms->all(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Laden der Räume: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['reservation', 'events', 'rooms', 'list'],
            'requires_team' => true,
            'read_only'     => true,
            'idempotent'    => true,
            'risk_level'    => 'safe',
        ];
    }
}
