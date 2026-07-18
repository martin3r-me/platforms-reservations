<?php

namespace Platform\Reservation\Tools;

use Illuminate\Support\Facades\Validator;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Models\SalesList;
use Platform\Reservation\Models\Venue;

/**
 * Legt einen Termin für das aktive Team an (Status: Entwurf).
 */
class EventCreateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.events.POST';
    }

    public function getDescription(): string
    {
        return 'POST /reservation/events - Legt einen Termin an (Status: draft). REST-Parameter: '
            . 'name (Pflicht), date (Pflicht, YYYY-MM-DD), description, order_deadline_at (Datum/Zeit), '
            . 'venue_id, sales_list_id, room_release_mode (parallel|sequential).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'name'              => ['type' => 'string'],
                'date'              => ['type' => 'string', 'description' => 'YYYY-MM-DD.'],
                'description'       => ['type' => 'string'],
                'order_deadline_at' => ['type' => 'string', 'description' => 'Bestellschluss (ISO-Datum/Zeit).'],
                'venue_id'          => ['type' => 'integer', 'description' => 'Venue des Teams (optional).'],
                'sales_list_id'     => ['type' => 'integer', 'description' => 'Verkaufsliste des Teams (optional).'],
                'room_release_mode' => ['type' => 'string', 'enum' => ['parallel', 'sequential']],
            ],
            'required'   => ['name', 'date'],
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
                'name'              => 'required|string|max:255',
                'date'              => 'required|date',
                'description'       => 'nullable|string',
                'order_deadline_at' => 'nullable|date',
                'venue_id'          => 'nullable|integer',
                'sales_list_id'     => 'nullable|integer',
                'room_release_mode' => 'nullable|in:parallel,sequential',
            ]);

            if ($validator->fails()) {
                return ToolResult::error($validator->errors()->first(), 'VALIDATION_ERROR');
            }

            if (!empty($arguments['venue_id']) && !$this->ownsVenue($teamId, (int) $arguments['venue_id'])) {
                return ToolResult::error('Venue nicht gefunden (oder gehört nicht zum Team).', 'VENUE_NOT_FOUND');
            }

            if (!empty($arguments['sales_list_id']) && !$this->ownsSalesList($teamId, (int) $arguments['sales_list_id'])) {
                return ToolResult::error('Verkaufsliste nicht gefunden (oder gehört nicht zum Team).', 'SALES_LIST_NOT_FOUND');
            }

            $data              = $validator->validated();
            $data['team_id']   = $teamId;
            $data['status']    = Event::STATUS_DRAFT;

            $event = Event::create($data);

            return ToolResult::success([
                'uuid'   => $event->uuid,
                'name'   => $event->name,
                'date'   => $event->date?->toDateString(),
                'status' => $event->status->value,
            ], ['created' => true]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Anlegen des Termins: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    protected function ownsVenue(int $teamId, int $id): bool
    {
        return Venue::withoutGlobalScope('team')->where('team_id', $teamId)->where('id', $id)->exists();
    }

    protected function ownsSalesList(int $teamId, int $id): bool
    {
        return SalesList::withoutGlobalScope('team')->where('team_id', $teamId)->where('id', $id)->exists();
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'events', 'create'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['creates'],
            'risk_level'    => 'write',
        ];
    }
}
