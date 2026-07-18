<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\Event;

/**
 * Listet die Pausen-Slots eines Termins.
 */
class EventSlotListTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.event-slots.GET';
    }

    public function getDescription(): string
    {
        return 'GET /reservation/event-slots - Listet die Pausen-Slots eines Termins. '
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
                ->with('slots')
                ->first();

            if (!$event) {
                return ToolResult::error('Termin nicht gefunden.', 'NOT_FOUND');
            }

            $slots = $event->slots->map(fn ($slot) => [
                'id'         => $slot->id,
                'name'       => $slot->name,
                'time_start' => $slot->time_start,
                'time_end'   => $slot->time_end,
                'sort_order' => $slot->sort_order,
            ]);

            return ToolResult::success([
                'event_uuid' => $event->uuid,
                'count'      => $slots->count(),
                'slots'      => $slots->all(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Laden der Slots: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['reservation', 'events', 'slots', 'list'],
            'requires_team' => true,
            'read_only'     => true,
            'idempotent'    => true,
            'risk_level'    => 'safe',
        ];
    }
}
