<?php

namespace Platform\Reservation\Tools;

use Illuminate\Support\Facades\Validator;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Models\PickupStation;

/**
 * Ordnet einem Termin eine Abholstation zu – für bestimmte Pausen.
 */
class EventStationCreateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.event-stations.POST';
    }

    public function getDescription(): string
    {
        return 'POST /reservation/event-stations - Ordnet einem Termin eine Abholstation zu. REST-Parameter: '
            . 'event_uuid (Pflicht), pickup_station_id (Pflicht); slot_ids (Array der Pausen - weglassen = ALLE '
            . 'Pausen des Termins), capacity_override, sort_order (optional). '
            . 'Eine Station gilt JE PAUSE: Ohne Pause erscheint sie im Shop nirgends, deshalb ist mindestens eine '
            . 'Pflicht. Die Station muss zum Haus (Venue) des Termins gehoeren. Idempotent (Station je Termin nur '
            . 'einmal); ein zweiter Aufruf aktualisiert Pausen und Obergrenze.';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'event_uuid'        => ['type' => 'string'],
                'pickup_station_id' => ['type' => 'integer'],
                'slot_ids'          => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Pausen, in denen die Station oeffnet. Weglassen = alle Pausen des Termins.'],
                'capacity_override' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Gaeste je Pause fuer DIESEN Termin; schlaegt capacity_per_slot der Station. Weglassen = Vorgabe der Station.'],
                'sort_order'        => ['type' => 'integer'],
            ],
            'required'   => ['event_uuid', 'pickup_station_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $context->team?->id;

            if (! $teamId) {
                return ToolResult::error('Kein Team-Kontext vorhanden.', 'MISSING_TEAM');
            }

            $validator = Validator::make($arguments, [
                'event_uuid'        => 'required|string',
                'pickup_station_id' => 'required|integer',
                'slot_ids'          => 'nullable|array',
                'slot_ids.*'        => 'integer',
                'capacity_override' => 'nullable|integer|min:1|max:9999',
                'sort_order'        => 'nullable|integer',
            ]);

            if ($validator->fails()) {
                return ToolResult::error($validator->errors()->first(), 'VALIDATION_ERROR');
            }

            $event = Event::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->where('uuid', $arguments['event_uuid'])
                ->with('slots')
                ->first();

            if (! $event) {
                return ToolResult::error('Termin nicht gefunden.', 'NOT_FOUND');
            }

            $station = PickupStation::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->find((int) $arguments['pickup_station_id']);

            if (! $station) {
                return ToolResult::error('Abholstation nicht gefunden (oder gehört nicht zum Team).', 'STATION_NOT_FOUND');
            }

            // Dasselbe Haus. Ohne diese Pruefung liesse sich einem Termin im
            // Haus A das Foyer aus Haus B zuordnen - am Abend stuende die Ware
            // im falschen Haus.
            if ($event->venue_id && (int) $station->venue_id !== (int) $event->venue_id) {
                return ToolResult::error(
                    'Die Abholstation gehört zu einem anderen Haus als der Termin.',
                    'STATION_WRONG_VENUE'
                );
            }

            $alle = $event->slots->pluck('id')->map(fn ($id) => (int) $id)->all();

            if ($alle === []) {
                return ToolResult::error('Der Termin hat noch keine Pause – eine Abholstation gilt je Pause.', 'NO_SLOTS');
            }

            // Ohne Angabe alle Pausen. Das ist die haeufigere Absicht, und es
            // ist die sichere: Eine Station ohne Pause erschiene nirgends.
            $slotIds = array_key_exists('slot_ids', $arguments) && $arguments['slot_ids'] !== null
                ? array_values(array_intersect(array_map('intval', (array) $arguments['slot_ids']), $alle))
                : $alle;

            if ($slotIds === []) {
                return ToolResult::error(
                    'Keine gültige Pause übergeben – die genannten Pausen gehören nicht zu diesem Termin.',
                    'NO_VALID_SLOTS'
                );
            }

            $zuordnung = $event->eventStations()->firstOrCreate(
                ['pickup_station_id' => $station->id],
                [
                    'capacity_override' => $arguments['capacity_override'] ?? null,
                    'sort_order'        => (int) ($arguments['sort_order'] ?? 0),
                ],
            );

            if (! $zuordnung->wasRecentlyCreated) {
                $zuordnung->update(array_filter([
                    'capacity_override' => $arguments['capacity_override'] ?? null,
                    'sort_order'        => $arguments['sort_order'] ?? null,
                ], fn ($w) => $w !== null));
            }

            $zuordnung->slots()->sync($slotIds);

            return ToolResult::success([
                'id'                => $zuordnung->id,
                'event_uuid'        => $event->uuid,
                'pickup_station_id' => $station->id,
                'name'              => $station->name,
                'slot_ids'          => $slotIds,
            ], ['created' => $zuordnung->wasRecentlyCreated]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Zuordnen der Abholstation: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'events', 'stations', 'create'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['creates'],
            'risk_level'    => 'write',
        ];
    }
}
