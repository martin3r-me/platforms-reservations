<?php

namespace Platform\Reservation\Tools;

use Illuminate\Support\Facades\Validator;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Models\EventRoom;

/**
 * Aktualisiert die Freigabe-Konfiguration einer Raum-Zuordnung.
 *
 * Bisher ließen sich Schwellwert, Kapazität und Reihenfolge nach dem Zuweisen
 * nur durch Löschen und Neuanlegen ändern.
 */
class EventRoomUpdateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.event-rooms.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /reservation/event-rooms - Aktualisiert eine Raum-Zuordnung. '
            . 'REST-Parameter: id (Pflicht, aus event-rooms.GET) ODER event_uuid + floor_plan_id; '
            . 'sort_order, fill_threshold_percent (1..100 - oeffnet den NAECHSTEN Raum), capacity_override '
            . '(null = Summe der Tischkapazitäten; Nenner der Prozentrechnung, begrenzt nichts), '
            . 'is_open_override (bool, überstimmt die sequentielle Freigabe) – alle optional. '
            . 'fill_threshold_percent und capacity_override wirken nur bei sequentieller Raumfreigabe. '
            . 'Der zugewiesene Tischplan selbst wird nicht getauscht; dafür event-rooms.bulk.POST mit replace=true.';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'id'                     => ['type' => 'integer', 'description' => 'ID der Raum-Zuordnung.'],
                'event_uuid'             => ['type' => 'string', 'description' => 'Alternativ zu id, zusammen mit floor_plan_id.'],
                'floor_plan_id'          => ['type' => 'integer', 'description' => 'Alternativ zu id, zusammen mit event_uuid.'],
                'sort_order'             => ['type' => 'integer'],
                'fill_threshold_percent' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'description' => 'Prozentsatz, ab dem der NAECHSTE Raum in der Reihenfolge oeffnet (1..100, Default 100). Wirkt nur bei sequentieller Raumfreigabe; am letzten Raum ohne Wirkung.'],
                'capacity_override'      => ['type' => ['integer', 'null'], 'minimum' => 1, 'description' => 'Nenner der Prozentrechnung fuer die Freigabe; null = Summe der aktiven Tischkapazitaeten. Begrenzt NICHTS - die Platzpruefung laeuft je Tisch. Wirkt nur bei sequentieller Freigabe; wird zusaetzlich als capacity/total_capacity in der Gast-API gemeldet.'],
                'is_open_override'       => ['type' => ['boolean', 'null'], 'description' => 'true = immer offen, false = geschlossen (nimmt keine neuen Buchungen an, bestehende bleiben; wird in der Reihenfolge uebersprungen), null = der Reihenfolge folgen.'],
            ],
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
                'id'                     => 'sometimes|integer',
                'event_uuid'             => 'sometimes|string',
                'floor_plan_id'          => 'sometimes|integer',
                'sort_order'             => 'sometimes|integer',
                'fill_threshold_percent' => 'sometimes|integer|min:1|max:100',
                'capacity_override'      => 'sometimes|nullable|integer|min:1',
                'is_open_override'       => 'sometimes|nullable|boolean',
            ]);

            if ($validator->fails()) {
                return ToolResult::error($validator->errors()->first(), 'VALIDATION_ERROR');
            }

            $room = $this->resolveRoom($arguments, $teamId);

            if (!$room) {
                return ToolResult::error(
                    'Raum-Zuordnung nicht gefunden. Entweder id angeben, oder event_uuid und floor_plan_id zusammen.',
                    'NOT_FOUND',
                );
            }

            $data = collect($validator->validated())
                ->only(['sort_order', 'fill_threshold_percent', 'capacity_override', 'is_open_override'])
                ->all();

            if ($data === []) {
                return ToolResult::error('Kein zu änderndes Feld angegeben.', 'VALIDATION_ERROR');
            }

            $room->update($data);

            return ToolResult::success([
                'id'                     => $room->id,
                'event_id'               => $room->event_id,
                'floor_plan_id'          => $room->floor_plan_id,
                'sort_order'             => $room->sort_order,
                'fill_threshold_percent' => $room->fill_threshold_percent,
                'capacity_override'      => $room->capacity_override,
                'is_open_override'       => $room->is_open_override,
            ], ['updated' => true]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Aktualisieren der Raum-Zuordnung: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    /**
     * Zuordnung über id oder über event_uuid + floor_plan_id auflösen – immer
     * gegen das Team abgesichert. EventRoom hat keine eigene team_id, die
     * Trennung läuft über den Termin.
     */
    protected function resolveRoom(array $arguments, int $teamId): ?EventRoom
    {
        if (isset($arguments['id'])) {
            $room = EventRoom::find((int) $arguments['id']);

            if (!$room) {
                return null;
            }

            $belongsToTeam = Event::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->whereKey($room->event_id)
                ->exists();

            return $belongsToTeam ? $room : null;
        }

        if (!isset($arguments['event_uuid'], $arguments['floor_plan_id'])) {
            return null;
        }

        $event = Event::withoutGlobalScope('team')
            ->where('team_id', $teamId)
            ->where('uuid', (string) $arguments['event_uuid'])
            ->first();

        return $event
            ? $event->eventRooms()->where('floor_plan_id', (int) $arguments['floor_plan_id'])->first()
            : null;
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'events', 'rooms', 'update'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['updates'],
            'risk_level'    => 'write',
        ];
    }
}
