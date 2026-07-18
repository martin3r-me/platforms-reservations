<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\EventSlot;

/**
 * Löscht einen Pausen-Slot (des aktiven Teams).
 */
class EventSlotDeleteTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.event-slots.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /reservation/event-slots - Löscht einen Pausen-Slot. REST-Parameter: id (Pflicht). '
            . 'Vorhandene Buchungen bleiben bestehen, verlieren aber den Slot-Bezug.';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'ID des zu löschenden Slots.'],
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

            $slot = EventSlot::where('id', (int) ($arguments['id'] ?? 0))
                ->whereHas('event', fn ($q) => $q->withoutGlobalScope('team')->where('team_id', $teamId))
                ->first();

            if (!$slot) {
                return ToolResult::error('Slot nicht gefunden.', 'NOT_FOUND');
            }

            $slot->delete();

            return ToolResult::success(['deleted' => true, 'id' => (int) $arguments['id']]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Löschen des Slots: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'             => 'action',
            'tags'                 => ['reservation', 'events', 'slots', 'delete'],
            'requires_team'        => true,
            'read_only'            => false,
            'side_effects'         => ['deletes'],
            'risk_level'           => 'destructive',
            'confirmation_required'=> true,
        ];
    }
}
