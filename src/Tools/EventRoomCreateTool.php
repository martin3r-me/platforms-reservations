<?php

namespace Platform\Reservation\Tools;

use Illuminate\Support\Facades\Validator;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Models\FloorPlan;

/**
 * Weist einem Termin einen Raum (Tischplan) zu – Grundlage für die Sitzplatzwahl.
 */
class EventRoomCreateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.event-rooms.POST';
    }

    public function getDescription(): string
    {
        return 'POST /reservation/event-rooms - Weist einem Termin einen Raum (Tischplan) zu. REST-Parameter: '
            . 'event_uuid (Pflicht), floor_plan_id (Pflicht), fill_threshold_percent (optional, Default 100), '
            . 'capacity_override (optional), sort_order (optional). Idempotent (Raum je Termin nur einmal).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'event_uuid'             => ['type' => 'string'],
                'floor_plan_id'          => ['type' => 'integer'],
                'fill_threshold_percent' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                'capacity_override'      => ['type' => 'integer', 'minimum' => 1],
                'sort_order'             => ['type' => 'integer'],
            ],
            'required'   => ['event_uuid', 'floor_plan_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $context->team?->id;

            if (!$teamId) {
                return ToolResult::error('Kein Team-Kontext vorhanden.', 'MISSING_TEAM');
            }

            $validator = Validator::make($arguments, [
                'event_uuid'             => 'required|string',
                'floor_plan_id'          => 'required|integer',
                'fill_threshold_percent' => 'nullable|integer|min:1|max:100',
                'capacity_override'      => 'nullable|integer|min:1',
                'sort_order'             => 'nullable|integer',
            ]);

            if ($validator->fails()) {
                return ToolResult::error($validator->errors()->first(), 'VALIDATION_ERROR');
            }

            $event = Event::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->where('uuid', $arguments['event_uuid'])
                ->first();

            if (!$event) {
                return ToolResult::error('Termin nicht gefunden.', 'NOT_FOUND');
            }

            if (!FloorPlan::withoutGlobalScope('team')->where('team_id', $teamId)->where('id', (int) $arguments['floor_plan_id'])->exists()) {
                return ToolResult::error('Tischplan nicht gefunden (oder gehört nicht zum Team).', 'FLOOR_PLAN_NOT_FOUND');
            }

            $room = $event->eventRooms()->firstOrCreate(
                ['floor_plan_id' => (int) $arguments['floor_plan_id']],
                [
                    'fill_threshold_percent' => (int) ($arguments['fill_threshold_percent'] ?? 100),
                    'capacity_override'      => $arguments['capacity_override'] ?? null,
                    'sort_order'             => (int) ($arguments['sort_order'] ?? 0),
                ],
            );

            return ToolResult::success([
                'id'            => $room->id,
                'event_uuid'    => $event->uuid,
                'floor_plan_id' => $room->floor_plan_id,
            ], ['created' => $room->wasRecentlyCreated]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Zuweisen des Raums: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'events', 'rooms', 'create'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['creates'],
            'risk_level'    => 'write',
        ];
    }
}
