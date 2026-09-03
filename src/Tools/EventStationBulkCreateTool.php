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
 * Abholstationen mehreren Terminen zuordnen – das Werkzeug für eine Saison.
 *
 * Der Grund, warum es dieses Werkzeug gibt: Die 54 Termine einer Saison sind
 * über die Tools entstanden. Eine Station, die man 54-mal von Hand zuordnen
 * muss, wird nicht benutzt.
 *
 * Vorbild ist EventRoomBulkCreateTool – auch fachlich: Es entfernt nichts, auf
 * dem Buchungen liegen, außer mit `force`. Hier gilt das eine Stufe feiner,
 * nämlich auch je PAUSE: Wer einer Station eine Pause wegnimmt, in der schon
 * bestellt wurde, ließe diese Buchungen auf einen Ort zeigen, den es für sie
 * nicht mehr gibt.
 */
class EventStationBulkCreateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.event-stations.bulk.POST';
    }

    public function getDescription(): string
    {
        return 'POST /reservation/event-stations/bulk - Ordnet Abholstationen mehreren Terminen zu. '
            . 'REST-Parameter: event_uuids (Array, Pflicht); pickup_station_id (einzeln) ODER pickup_station_ids '
            . '(Array, Reihenfolge = sort_order); capacity_override, replace (bool, Default false), force (bool, '
            . 'Default false). '
            . 'Die Pausen werden je Termin auf ALLE gesetzt - eine Station ohne Pause erschiene nirgends, und '
            . 'welche Pausen ein Termin hat, weiss nur er selbst. Ohne replace additiv und idempotent. MIT '
            . 'replace=true hat jeder Termin danach GENAU die angegebenen Stationen. '
            . 'Stationen, auf denen der Termin Buchungen hat, werden NICHT entfernt - mit force=true doch. '
            . 'Stationen aus einem anderen Haus als der Termin werden uebersprungen und gemeldet.';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'event_uuids'        => ['type' => 'array', 'items' => ['type' => 'string']],
                'pickup_station_id'  => ['type' => 'integer'],
                'pickup_station_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                'capacity_override'  => ['type' => 'integer', 'minimum' => 1],
                'replace'            => ['type' => 'boolean'],
                'force'              => ['type' => 'boolean'],
            ],
            'required'   => ['event_uuids'],
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
                'event_uuids'          => 'required|array|min:1',
                'event_uuids.*'        => 'string',
                'pickup_station_id'    => 'nullable|integer',
                'pickup_station_ids'   => 'nullable|array',
                'pickup_station_ids.*' => 'integer',
                'capacity_override'    => 'nullable|integer|min:1|max:9999',
                'replace'              => 'nullable|boolean',
                'force'                => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return ToolResult::error($validator->errors()->first(), 'VALIDATION_ERROR');
            }

            $stationIds = array_values(array_unique(array_map('intval', (array) (
                $arguments['pickup_station_ids'] ?? array_filter([$arguments['pickup_station_id'] ?? null])
            ))));

            $replace = (bool) ($arguments['replace'] ?? false);
            $force   = (bool) ($arguments['force'] ?? false);

            // Leere Liste MIT replace ist die einzige Art, alle Stationen zu
            // entfernen - ohne replace waere sie sinnlos und vermutlich ein
            // Versehen.
            if ($stationIds === [] && ! $replace) {
                return ToolResult::error('Keine Abholstation angegeben.', 'VALIDATION_ERROR');
            }

            $stationen = PickupStation::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->whereIn('id', $stationIds)
                ->get()
                ->keyBy('id');

            if (count($stationen) !== count($stationIds)) {
                return ToolResult::error('Mindestens eine Abholstation gehört nicht zum Team.', 'STATION_NOT_FOUND');
            }

            $events = Event::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->whereIn('uuid', $arguments['event_uuids'])
                ->with(['slots', 'eventStations.station'])
                ->get();

            $zugeordnet   = 0;
            $entfernt     = 0;
            $ohnePausen   = [];
            $falschesHaus = [];
            $behalten     = [];
            $unbekannt    = array_values(array_diff($arguments['event_uuids'], $events->pluck('uuid')->all()));

            foreach ($events as $event) {
                $alleSlots = $event->slots->pluck('id')->map(fn ($id) => (int) $id)->all();

                if ($alleSlots === []) {
                    $ohnePausen[] = $event->uuid;

                    continue;
                }

                $gewollt = [];

                foreach ($stationIds as $sortOrder => $stationId) {
                    $station = $stationen[$stationId];

                    if ($event->venue_id && (int) $station->venue_id !== (int) $event->venue_id) {
                        $falschesHaus[] = $station->name . ' / ' . $event->name;

                        continue;
                    }

                    $gewollt[] = $stationId;

                    $zuordnung = $event->eventStations()->firstOrCreate(
                        ['pickup_station_id' => $stationId],
                        [
                            'capacity_override' => $arguments['capacity_override'] ?? null,
                            'sort_order'        => $sortOrder,
                        ],
                    );

                    // Alle Pausen des Termins: Welche er hat, weiss nur er, und
                    // eine Station ohne Pause erschiene nirgends. Wer es feiner
                    // will, nimmt reservation.event-stations.POST je Termin.
                    $zuordnung->slots()->sync($alleSlots);

                    $zugeordnet++;
                }

                if (! $replace) {
                    continue;
                }

                foreach ($event->eventStations()->with('station')->get() as $vorhanden) {
                    if (in_array((int) $vorhanden->pickup_station_id, $gewollt, true)) {
                        continue;
                    }

                    if (! $force && $vorhanden->hatBuchungen()) {
                        $behalten[] = ($vorhanden->station?->name ?? '?') . ' / ' . $event->name;

                        continue;
                    }

                    $vorhanden->delete();
                    $entfernt++;
                }
            }

            return ToolResult::success([
                'events'      => $events->count(),
                'assigned'    => $zugeordnet,
                'removed'     => $entfernt,
                // Was NICHT passiert ist, und warum. Ein Stapel, der still die
                // Haelfte ueberspringt, ist schlimmer als einer, der abbricht.
                'skipped_no_slots'    => $ohnePausen,
                'skipped_wrong_venue' => array_values(array_unique($falschesHaus)),
                'kept_has_bookings'   => array_values(array_unique($behalten)),
                'unknown_events'      => $unbekannt,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Zuordnen der Abholstationen: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'events', 'stations', 'bulk'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['creates', 'updates', 'deletes'],
            'risk_level'    => 'write',
        ];
    }
}
