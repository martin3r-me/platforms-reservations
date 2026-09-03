<?php

namespace Platform\Reservation\Tools;

use Illuminate\Support\Facades\Validator;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\Event;

/**
 * Die Abholstationen eines Termins – samt der Pausen, in denen sie öffnen.
 */
class EventStationListTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.event-stations.GET';
    }

    public function getDescription(): string
    {
        return 'GET /reservation/event-stations - Listet die Abholstationen eines Termins mit den PAUSEN, in denen '
            . 'sie geoeffnet sind, und der geltenden Obergrenze (capacity_override schlaegt capacity_per_slot der '
            . 'Station; null = unbegrenzt). Eine Station gilt JE PAUSE - zwei Pausen, Station nur in der ersten, '
            . 'ist der Normalfall und kein Sonderfall. REST-Parameter: event_uuid (Pflicht).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => ['event_uuid' => ['type' => 'string']],
            'required'   => ['event_uuid'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $context->team?->id;

            if (! $teamId) {
                return ToolResult::error('Kein Team-Kontext vorhanden.', 'MISSING_TEAM');
            }

            $validator = Validator::make($arguments, ['event_uuid' => 'required|string']);

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

            $zuordnungen = $event->eventStations()->with(['station', 'slots'])->get();

            return ToolResult::success([
                'event_uuid' => $event->uuid,
                'count'      => $zuordnungen->count(),
                'stations'   => $zuordnungen->map(fn ($z) => [
                    'id'                => $z->id,
                    'pickup_station_id' => $z->pickup_station_id,
                    'name'              => $z->station?->name,
                    'is_active'         => (bool) ($z->station?->is_active),
                    'capacity_override' => $z->capacity_override,
                    // Die geltende Zahl - eigene schlaegt die der Station.
                    'grenze_je_pause'   => $z->grenzeJePause(),
                    'sort_order'        => (int) $z->sort_order,
                    'slots'             => $z->slots->map(fn ($s) => [
                        'id'   => $s->id,
                        'name' => $s->name,
                    ])->values()->all(),
                ])->values()->all(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Laden der Abholstationen: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'data',
            'tags'          => ['reservation', 'events', 'stations', 'list'],
            'requires_team' => true,
            'read_only'     => true,
            'risk_level'    => 'safe',
        ];
    }
}
