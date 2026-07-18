<?php

namespace Platform\Reservation\Tools;

use Illuminate\Support\Facades\Validator;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\Event;

/**
 * Legt einen Pausen-Slot für einen Termin des aktiven Teams an.
 */
class EventSlotCreateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.event-slots.POST';
    }

    public function getDescription(): string
    {
        return 'POST /reservation/event-slots - Legt einen Pausen-Slot an. REST-Parameter: event_uuid (Pflicht), '
            . 'name (Pflicht, z.B. "Pause 1"), time_start (Pflicht, HH:MM), time_end (HH:MM), sort_order (int).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'event_uuid' => ['type' => 'string'],
                'name'       => ['type' => 'string'],
                'time_start' => ['type' => 'string', 'description' => 'HH:MM, z.B. "20:15".'],
                'time_end'   => ['type' => 'string', 'description' => 'HH:MM (optional).'],
                'sort_order' => ['type' => 'integer'],
            ],
            'required'   => ['event_uuid', 'name', 'time_start'],
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
                'event_uuid' => 'required|string',
                'name'       => 'required|string|max:255',
                'time_start' => 'required|date_format:H:i',
                'time_end'   => 'nullable|date_format:H:i',
                'sort_order' => 'nullable|integer',
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

            $slot = $event->slots()->create([
                'name'       => $arguments['name'],
                'time_start' => $arguments['time_start'],
                'time_end'   => $arguments['time_end'] ?? null,
                'sort_order' => (int) ($arguments['sort_order'] ?? 0),
            ]);

            return ToolResult::success([
                'id'         => $slot->id,
                'name'       => $slot->name,
                'time_start' => $slot->time_start,
                'time_end'   => $slot->time_end,
            ], ['created' => true]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Anlegen des Slots: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'events', 'slots', 'create'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['creates'],
            'risk_level'    => 'write',
        ];
    }
}
