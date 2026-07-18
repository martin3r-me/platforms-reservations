<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Models\SalesList;
use Platform\Reservation\Models\Venue;

/**
 * Legt mehrere Termine auf einmal an (Status: Entwurf).
 */
class EventBulkCreateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.events.bulk.POST';
    }

    public function getDescription(): string
    {
        return 'POST /reservation/events/bulk - Legt mehrere Termine auf einmal an (Status: draft). '
            . 'REST-Parameter: events (Array von Objekten mit name, date (YYYY-MM-DD), optional description, '
            . 'venue_id, sales_list_id, room_release_mode). Bis zu 500 je Aufruf. Liefert eine Ergebnisliste.';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'events' => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'name'              => ['type' => 'string'],
                            'date'              => ['type' => 'string', 'description' => 'YYYY-MM-DD.'],
                            'description'       => ['type' => 'string'],
                            'venue_id'          => ['type' => 'integer'],
                            'sales_list_id'     => ['type' => 'integer'],
                            'room_release_mode' => ['type' => 'string', 'enum' => ['parallel', 'sequential']],
                        ],
                        'required'   => ['name', 'date'],
                    ],
                ],
            ],
            'required'   => ['events'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $context->team?->id;

            if (!$teamId) {
                return ToolResult::error('Kein Team-Kontext vorhanden.', 'MISSING_TEAM');
            }

            $rows = $arguments['events'] ?? [];
            if (!is_array($rows) || $rows === []) {
                return ToolResult::error('Parameter "events" muss ein nicht-leeres Array sein.', 'VALIDATION_ERROR');
            }
            if (count($rows) > 500) {
                return ToolResult::error('Maximal 500 Termine je Aufruf.', 'TOO_MANY');
            }

            $venueIds = Venue::withoutGlobalScope('team')->where('team_id', $teamId)->pluck('id')->flip();
            $listIds  = SalesList::withoutGlobalScope('team')->where('team_id', $teamId)->pluck('id')->flip();

            $created = [];
            $failed  = [];

            foreach ($rows as $i => $row) {
                $name = trim((string) ($row['name'] ?? ''));
                $date = (string) ($row['date'] ?? '');

                if ($name === '' || !strtotime($date)) {
                    $failed[] = ['index' => $i, 'error' => 'name/date fehlt oder ungültig'];
                    continue;
                }
                if (!empty($row['venue_id']) && !$venueIds->has((int) $row['venue_id'])) {
                    $failed[] = ['index' => $i, 'name' => $name, 'error' => 'venue_id gehört nicht zum Team'];
                    continue;
                }
                if (!empty($row['sales_list_id']) && !$listIds->has((int) $row['sales_list_id'])) {
                    $failed[] = ['index' => $i, 'name' => $name, 'error' => 'sales_list_id gehört nicht zum Team'];
                    continue;
                }

                $event = Event::create([
                    'team_id'           => $teamId,
                    'name'              => $name,
                    'date'              => $date,
                    'description'       => $row['description'] ?? null,
                    'venue_id'          => $row['venue_id'] ?? null,
                    'sales_list_id'     => $row['sales_list_id'] ?? null,
                    'room_release_mode' => $row['room_release_mode'] ?? null,
                    'status'            => Event::STATUS_DRAFT,
                ]);

                $created[] = ['uuid' => $event->uuid, 'name' => $event->name, 'date' => $event->date?->toDateString()];
            }

            return ToolResult::success([
                'created_count' => count($created),
                'failed_count'  => count($failed),
                'created'       => $created,
                'failed'        => $failed,
            ], ['created' => count($created)]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Anlegen der Termine: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'events', 'create', 'bulk'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['creates'],
            'risk_level'    => 'write',
        ];
    }
}
