<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Models\FloorPlan;

/**
 * Weist EINEN Raum (Tischplan) mehreren Terminen auf einmal zu.
 */
class EventRoomBulkCreateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.event-rooms.bulk.POST';
    }

    public function getDescription(): string
    {
        return 'POST /reservation/event-rooms/bulk - Weist einen Tischplan mehreren Terminen als Raum zu. '
            . 'REST-Parameter: event_uuids (Array), floor_plan_id (Pflicht), fill_threshold_percent (optional, '
            . 'Default 100), capacity_override, sort_order. Idempotent je Termin.';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'event_uuids'            => ['type' => 'array', 'items' => ['type' => 'string']],
                'floor_plan_id'          => ['type' => 'integer'],
                'fill_threshold_percent' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                'capacity_override'      => ['type' => 'integer', 'minimum' => 1],
                'sort_order'             => ['type' => 'integer'],
            ],
            'required'   => ['event_uuids', 'floor_plan_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $context->team?->id;

            if (!$teamId) {
                return ToolResult::error('Kein Team-Kontext vorhanden.', 'MISSING_TEAM');
            }

            $uuids = $arguments['event_uuids'] ?? [];
            if (!is_array($uuids) || $uuids === []) {
                return ToolResult::error('Parameter "event_uuids" muss ein nicht-leeres Array sein.', 'VALIDATION_ERROR');
            }

            $floorPlanId = (int) ($arguments['floor_plan_id'] ?? 0);
            if (!FloorPlan::withoutGlobalScope('team')->where('team_id', $teamId)->where('id', $floorPlanId)->exists()) {
                return ToolResult::error('Tischplan nicht gefunden (oder gehört nicht zum Team).', 'FLOOR_PLAN_NOT_FOUND');
            }

            $attributes = [
                'fill_threshold_percent' => (int) ($arguments['fill_threshold_percent'] ?? 100),
                'capacity_override'      => $arguments['capacity_override'] ?? null,
                'sort_order'             => (int) ($arguments['sort_order'] ?? 0),
            ];

            $assigned = 0;
            $notFound = [];

            foreach ($uuids as $uuid) {
                $event = Event::withoutGlobalScope('team')
                    ->where('team_id', $teamId)
                    ->where('uuid', (string) $uuid)
                    ->first();

                if (!$event) {
                    $notFound[] = (string) $uuid;
                    continue;
                }

                $event->eventRooms()->firstOrCreate(['floor_plan_id' => $floorPlanId], $attributes);
                $assigned++;
            }

            return ToolResult::success([
                'assigned_count'  => $assigned,
                'not_found_count' => count($notFound),
                'not_found'       => $notFound,
            ], ['updated' => $assigned]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Zuweisen der Räume: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'events', 'rooms', 'bulk'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['creates'],
            'risk_level'    => 'write',
        ];
    }
}
