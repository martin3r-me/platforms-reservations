<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Enums\EventStatus;
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
            . 'event_uuids (Array), status (draft|announced|published|closed|cancelled) ODER publish (bool, '
            . 'Default true → published; false → draft). status gewinnt, wenn beides kommt. '
            . 'Veröffentlichen überspringt Termine ohne Pausen-Slot; announced ist der Zustand '
            . '„steht im Shop, Vorbestellung noch nicht offen".';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'event_uuids' => ['type' => 'array', 'items' => ['type' => 'string']],
                'publish'     => ['type' => 'boolean', 'description' => 'true = veröffentlichen, false = Entwurf.'],
                'status'      => ['type' => 'string', 'enum' => ['draft', 'announced', 'published', 'closed', 'cancelled'], 'description' => 'Zielstatus; genauer als publish.'],
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

            // status ist der genauere Weg; publish bleibt für Bestandsaufrufe.
            $status = $arguments['status'] ?? null;

            if ($status !== null && !in_array($status, EventStatus::values(), true)) {
                return ToolResult::error('Unbekannter Status: ' . $status, 'VALIDATION_ERROR');
            }

            $status ??= ((bool) ($arguments['publish'] ?? true)) ? Event::STATUS_PUBLISHED : Event::STATUS_DRAFT;

            // Die Slot-Prüfung schützt das Veröffentlichen, nicht jeden Statuswechsel:
            // ankündigen darf man einen Termin, dessen Pausen noch nicht stehen.
            $publish = $status === Event::STATUS_PUBLISHED;

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
