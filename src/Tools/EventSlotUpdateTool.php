<?php

namespace Platform\Reservation\Tools;

use Illuminate\Support\Facades\Validator;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\EventSlot;

/**
 * Aktualisiert einen Pausen-Slot (des aktiven Teams).
 */
class EventSlotUpdateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.event-slots.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /reservation/event-slots - Aktualisiert einen Pausen-Slot. REST-Parameter: id (Pflicht); '
            . 'name, time_start (HH:MM), time_end (HH:MM), sort_order (jeweils optional).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'id'         => ['type' => 'integer'],
                'name'       => ['type' => 'string'],
                'time_start' => ['type' => 'string', 'description' => 'HH:MM.'],
                'time_end'   => ['type' => 'string', 'description' => 'HH:MM.'],
                'sort_order' => ['type' => 'integer'],
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

            $validator = Validator::make($arguments, [
                'id'         => 'required|integer',
                'name'       => 'sometimes|string|max:255',
                'time_start' => 'sometimes|date_format:H:i',
                'time_end'   => 'nullable|date_format:H:i',
                'sort_order' => 'sometimes|integer',
            ]);

            if ($validator->fails()) {
                return ToolResult::error($validator->errors()->first(), 'VALIDATION_ERROR');
            }

            $slot = EventSlot::where('id', (int) $arguments['id'])
                ->whereHas('event', fn ($q) => $q->withoutGlobalScope('team')->where('team_id', $teamId))
                ->first();

            if (!$slot) {
                return ToolResult::error('Slot nicht gefunden.', 'NOT_FOUND');
            }

            $slot->update(collect($validator->validated())->only(['name', 'time_start', 'time_end', 'sort_order'])->all());

            return ToolResult::success([
                'id'         => $slot->id,
                'name'       => $slot->name,
                'time_start' => $slot->time_start,
                'time_end'   => $slot->time_end,
            ], ['updated' => true]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Aktualisieren des Slots: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'events', 'slots', 'update'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['updates'],
            'risk_level'    => 'write',
        ];
    }
}
