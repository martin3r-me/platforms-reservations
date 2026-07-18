<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\Event;

/**
 * Veröffentlicht oder verbirgt einen Termin (Status published|draft).
 */
class EventPublishTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.events.publish.POST';
    }

    public function getDescription(): string
    {
        return 'POST /reservation/events/publish - Setzt den Termin-Status. REST-Parameter: uuid (Pflicht), '
            . 'publish (bool, Default true → published; false → draft). Veröffentlichen erfordert mindestens einen Pausen-Slot.';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'uuid'    => ['type' => 'string', 'description' => 'UUID des Termins.'],
                'publish' => ['type' => 'boolean', 'description' => 'true = veröffentlichen, false = zurück auf Entwurf.'],
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
                ->withCount('slots')
                ->first();

            if (!$event) {
                return ToolResult::error('Termin nicht gefunden.', 'NOT_FOUND');
            }

            $publish = (bool) ($arguments['publish'] ?? true);

            if ($publish && $event->slots_count < 1) {
                return ToolResult::error('Zum Veröffentlichen wird mindestens ein Pausen-Slot benötigt.', 'NO_SLOTS');
            }

            $event->update(['status' => $publish ? Event::STATUS_PUBLISHED : Event::STATUS_DRAFT]);

            return ToolResult::success([
                'uuid'   => $event->uuid,
                'status' => $event->status->value,
            ], ['updated' => true]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Setzen des Termin-Status: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'events', 'publish'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['updates'],
            'risk_level'    => 'write',
        ];
    }
}
