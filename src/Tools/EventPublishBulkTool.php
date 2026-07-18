<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\Event;

/**
 * Veröffentlicht (oder verbirgt) mehrere Termine auf einmal.
 */
class EventPublishBulkTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.events.publish.bulk.POST';
    }

    public function getDescription(): string
    {
        return 'POST /reservation/events/publish/bulk - Setzt den Status mehrerer Termine. REST-Parameter: '
            . 'event_uuids (Array), publish (bool, Default true → published; false → draft). '
            . 'Veröffentlichen überspringt Termine ohne Pausen-Slot.';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'event_uuids' => ['type' => 'array', 'items' => ['type' => 'string']],
                'publish'     => ['type' => 'boolean', 'description' => 'true = veröffentlichen, false = Entwurf.'],
            ],
            'required'   => ['event_uuids'],
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

            $publish = (bool) ($arguments['publish'] ?? true);
            $status  = $publish ? Event::STATUS_PUBLISHED : Event::STATUS_DRAFT;

            $changed     = 0;
            $skippedNoSlot = [];
            $notFound    = [];

            foreach ($uuids as $uuid) {
                $event = Event::withoutGlobalScope('team')
                    ->where('team_id', $teamId)
                    ->where('uuid', (string) $uuid)
                    ->withCount('slots')
                    ->first();

                if (!$event) {
                    $notFound[] = (string) $uuid;
                    continue;
                }

                if ($publish && $event->slots_count < 1) {
                    $skippedNoSlot[] = (string) $uuid;
                    continue;
                }

                $event->update(['status' => $status]);
                $changed++;
            }

            return ToolResult::success([
                'changed_count'        => $changed,
                'skipped_no_slot_count'=> count($skippedNoSlot),
                'skipped_no_slot'      => $skippedNoSlot,
                'not_found_count'      => count($notFound),
                'not_found'            => $notFound,
                'status'               => $status,
            ], ['updated' => $changed]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Setzen des Status: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'events', 'publish', 'bulk'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['updates'],
            'risk_level'    => 'write',
        ];
    }
}
