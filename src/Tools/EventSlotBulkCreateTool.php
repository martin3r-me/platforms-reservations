<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\Event;

/**
 * Legt denselben Pausen-Slot bei mehreren Terminen auf einmal an.
 */
class EventSlotBulkCreateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.event-slots.bulk.POST';
    }

    public function getDescription(): string
    {
        return 'POST /reservation/event-slots/bulk - Legt denselben Slot bei mehreren Terminen an. '
            . 'REST-Parameter: event_uuids (Array), name (Pflicht, z.B. "Pause"), time_start (Pflicht, HH:MM), '
            . 'time_end (optional, HH:MM), sort_order (optional).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'event_uuids' => ['type' => 'array', 'items' => ['type' => 'string']],
                'name'        => ['type' => 'string'],
                'time_start'  => ['type' => 'string', 'description' => 'HH:MM.'],
                'time_end'    => ['type' => 'string', 'description' => 'HH:MM (optional).'],
                'sort_order'  => ['type' => 'integer'],
            ],
            'required'   => ['event_uuids', 'name', 'time_start'],
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

            $name  = trim((string) ($arguments['name'] ?? ''));
            $start = (string) ($arguments['time_start'] ?? '');
            if ($name === '' || !preg_match('/^\d{1,2}:\d{2}$/', $start)) {
                return ToolResult::error('name und time_start (HH:MM) sind erforderlich.', 'VALIDATION_ERROR');
            }

            $slotData = [
                'name'       => $name,
                'time_start' => $start,
                'time_end'   => $arguments['time_end'] ?? null,
                'sort_order' => (int) ($arguments['sort_order'] ?? 0),
            ];

            $added    = 0;
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

                $event->slots()->create($slotData);
                $added++;
            }

            return ToolResult::success([
                'added_count'     => $added,
                'not_found_count' => count($notFound),
                'not_found'       => $notFound,
            ], ['created' => $added]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Anlegen der Slots: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'events', 'slots', 'bulk'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['creates'],
            'risk_level'    => 'write',
        ];
    }
}
