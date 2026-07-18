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
 * Aktualisiert einen Termin des aktiven Teams (per UUID).
 */
class EventUpdateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.events.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /reservation/events - Aktualisiert einen Termin. REST-Parameter: uuid (Pflicht); '
            . 'name, date, description, order_deadline_at, venue_id, sales_list_id, room_release_mode (optional). '
            . 'Status wird über publish/unpublish gesetzt.';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'uuid'              => ['type' => 'string', 'description' => 'UUID des Termins.'],
                'name'              => ['type' => 'string'],
                'date'              => ['type' => 'string'],
                'description'       => ['type' => 'string'],
                'order_deadline_at' => ['type' => 'string'],
                'venue_id'          => ['type' => 'integer'],
                'sales_list_id'     => ['type' => 'integer'],
                'room_release_mode' => ['type' => 'string', 'enum' => ['parallel', 'sequential']],
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

            $validator = Validator::make($arguments, [
                'uuid'              => 'required|string',
                'name'              => 'sometimes|string|max:255',
                'date'              => 'sometimes|date',
                'description'       => 'nullable|string',
                'order_deadline_at' => 'nullable|date',
                'venue_id'          => 'nullable|integer',
                'sales_list_id'     => 'nullable|integer',
                'room_release_mode' => 'sometimes|in:parallel,sequential',
            ]);

            if ($validator->fails()) {
                return ToolResult::error($validator->errors()->first(), 'VALIDATION_ERROR');
            }

            $event = Event::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->where('uuid', $arguments['uuid'])
                ->first();

            if (!$event) {
                return ToolResult::error('Termin nicht gefunden.', 'NOT_FOUND');
            }

            if (!empty($arguments['venue_id'])
                && !Venue::withoutGlobalScope('team')->where('team_id', $teamId)->where('id', (int) $arguments['venue_id'])->exists()) {
                return ToolResult::error('Venue nicht gefunden (oder gehört nicht zum Team).', 'VENUE_NOT_FOUND');
            }

            if (!empty($arguments['sales_list_id'])
                && !SalesList::withoutGlobalScope('team')->where('team_id', $teamId)->where('id', (int) $arguments['sales_list_id'])->exists()) {
                return ToolResult::error('Verkaufsliste nicht gefunden (oder gehört nicht zum Team).', 'SALES_LIST_NOT_FOUND');
            }

            $event->update(collect($validator->validated())->only([
                'name', 'date', 'description', 'order_deadline_at', 'venue_id', 'sales_list_id', 'room_release_mode',
            ])->all());

            return ToolResult::success([
                'uuid'   => $event->uuid,
                'name'   => $event->name,
                'date'   => $event->date?->toDateString(),
                'status' => $event->status->value,
            ], ['updated' => true]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Aktualisieren des Termins: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'events', 'update'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['updates'],
            'risk_level'    => 'write',
        ];
    }
}
