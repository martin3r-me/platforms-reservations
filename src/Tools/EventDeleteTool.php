<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\Event;

/**
 * Löscht einen Termin des aktiven Teams (per UUID).
 */
class EventDeleteTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.events.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /reservation/events - Löscht einen Termin. REST-Parameter: uuid (Pflicht). '
            . 'Vorhandene Buchungen bleiben bestehen, verlieren aber den Termin-Bezug.';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'uuid' => ['type' => 'string', 'description' => 'UUID des zu löschenden Termins.'],
            ],
            'required'   => ['uuid'],
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
                ->where('uuid', (string) ($arguments['uuid'] ?? ''))
                ->first();

            if (!$event) {
                return ToolResult::error('Termin nicht gefunden.', 'NOT_FOUND');
            }

            $uuid = $event->uuid;
            $event->delete();

            return ToolResult::success(['deleted' => true, 'uuid' => $uuid]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Löschen des Termins: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'             => 'action',
            'tags'                 => ['reservation', 'events', 'delete'],
            'requires_team'        => true,
            'read_only'            => false,
            'side_effects'         => ['deletes'],
            'risk_level'           => 'destructive',
            'confirmation_required'=> true,
        ];
    }
}
