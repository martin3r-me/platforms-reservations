<?php

namespace Platform\Reservation\Tools;

use Illuminate\Support\Facades\Validator;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\Event;

/**
 * Nimmt eine Abholstation aus einem Termin – nicht aber unter den Buchungen weg.
 */
class EventStationDeleteTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.event-stations.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /reservation/event-stations - Nimmt eine Abholstation aus einem Termin. REST-Parameter: '
            . 'event_uuid (Pflicht), pickup_station_id (Pflicht), force (bool, Default false). '
            . 'Liegen auf der Station schon Buchungen dieses Termins, wird sie NICHT entfernt - die Buchungen '
            . 'zeigten danach auf einen Ort, den es fuer sie nicht mehr gibt. Mit force=true trotzdem. Die '
            . 'Station selbst bleibt in jedem Fall bestehen; sie gehoert dem Haus.';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'event_uuid'        => ['type' => 'string'],
                'pickup_station_id' => ['type' => 'integer'],
                'force'             => ['type' => 'boolean', 'description' => 'Auch entfernen, wenn Buchungen darauf zeigen.'],
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
                'force'             => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return ToolResult::error($validator->errors()->first(), 'VALIDATION_ERROR');
            }

            $event = Event::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->where('uuid', $arguments['event_uuid'])
                ->first();

            if (! $event) {
                return ToolResult::error('Termin nicht gefunden.', 'NOT_FOUND');
            }

            $zuordnung = $event->eventStations()
                ->where('pickup_station_id', (int) $arguments['pickup_station_id'])
                ->with('station')
                ->first();

            if (! $zuordnung) {
                return ToolResult::error('Diese Abholstation ist dem Termin nicht zugeordnet.', 'NOT_ASSIGNED');
            }

            if (! ($arguments['force'] ?? false) && $zuordnung->hatBuchungen()) {
                return ToolResult::error(
                    'Auf „' . $zuordnung->station?->name . '" liegen Buchungen dieses Termins. '
                    . 'Erst stornieren oder verschieben – oder force=true, wenn die Buchungen ihren Ort verlieren dürfen.',
                    'STATION_HAS_BOOKINGS'
                );
            }

            $name = $zuordnung->station?->name;
            $zuordnung->delete();

            return ToolResult::success([
                'event_uuid'        => $event->uuid,
                'pickup_station_id' => (int) $arguments['pickup_station_id'],
                'name'              => $name,
            ], ['deleted' => true]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Entfernen der Abholstation: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'              => 'action',
            'tags'                  => ['reservation', 'events', 'stations', 'delete'],
            'requires_team'         => true,
            'read_only'             => false,
            'side_effects'          => ['deletes'],
            'risk_level'            => 'destructive',
            'confirmation_required' => true,
        ];
    }
}
