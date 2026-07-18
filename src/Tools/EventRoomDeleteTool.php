<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\EventRoom;

/**
 * Entfernt die Raum-Zuordnung eines Termins.
 */
class EventRoomDeleteTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.event-rooms.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /reservation/event-rooms - Entfernt die Raum-Zuordnung eines Termins. '
            . 'REST-Parameter: id (Pflicht).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'ID der Raum-Zuordnung.'],
            ],
            'required'   => ['id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $context->team?->id;

            if (!$teamId) {
                return ToolResult::error('Kein Team-Kontext vorhanden.', 'MISSING_TEAM');
            }

            $room = EventRoom::where('id', (int) ($arguments['id'] ?? 0))
                ->whereHas('event', fn ($q) => $q->withoutGlobalScope('team')->where('team_id', $teamId))
                ->first();

            if (!$room) {
                return ToolResult::error('Raum-Zuordnung nicht gefunden.', 'NOT_FOUND');
            }

            $room->delete();

            return ToolResult::success(['deleted' => true, 'id' => (int) $arguments['id']]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Entfernen der Raum-Zuordnung: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'             => 'action',
            'tags'                 => ['reservation', 'events', 'rooms', 'delete'],
            'requires_team'        => true,
            'read_only'            => false,
            'side_effects'         => ['deletes'],
            'risk_level'           => 'destructive',
            'confirmation_required'=> true,
        ];
    }
}
