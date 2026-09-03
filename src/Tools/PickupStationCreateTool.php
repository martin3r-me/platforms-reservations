<?php

namespace Platform\Reservation\Tools;

use Illuminate\Support\Facades\Validator;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\PickupStation;
use Platform\Reservation\Models\Venue;

/**
 * Legt eine Abholstation an – „Foyer links", „Rang 1 Bar".
 */
class PickupStationCreateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.pickup-stations.POST';
    }

    public function getDescription(): string
    {
        return 'POST /reservation/pickup-stations - Legt eine Abholstation an. REST-Parameter: venue_id (Pflicht), '
            . 'name (Pflicht); description, capacity_per_slot, sort_order, is_active (optional). '
            . 'capacity_per_slot sind Gaeste JE PAUSE, keine Sitzplaetze: 150 in Pause 1 und 150 in Pause 2 sind '
            . 'zusammen 300. Leer = unbegrenzt. Die Station gehoert dem VENUE und taucht in keiner Platzrechnung '
            . 'auf; um sie buchbar zu machen, muss sie einem Termin zugeordnet werden '
            . '(reservation.event-stations.POST).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'venue_id'          => ['type' => 'integer'],
                'name'              => ['type' => 'string'],
                'description'       => ['type' => 'string', 'description' => 'Hinweis fuer Gaeste, z.B. "neben der Garderobe".'],
                'capacity_per_slot' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Gaeste JE PAUSE, die hier bedient werden koennen. Weglassen = unbegrenzt. KEINE Sitzplaetze.'],
                'sort_order'        => ['type' => 'integer'],
                'is_active'         => ['type' => 'boolean'],
            ],
            'required'   => ['venue_id', 'name'],
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
                'venue_id'          => 'required|integer',
                'name'              => 'required|string|max:255',
                'description'       => 'nullable|string|max:1000',
                'capacity_per_slot' => 'nullable|integer|min:1|max:9999',
                'sort_order'        => 'nullable|integer',
                'is_active'         => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return ToolResult::error($validator->errors()->first(), 'VALIDATION_ERROR');
            }

            $venue = Venue::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->find((int) $arguments['venue_id']);

            if (! $venue) {
                return ToolResult::error('Venue nicht gefunden (oder gehört nicht zum Team).', 'VENUE_NOT_FOUND');
            }

            $station = PickupStation::create([
                'team_id'           => $teamId,
                'venue_id'          => $venue->id,
                'name'              => trim((string) $arguments['name']),
                'description'       => ($arguments['description'] ?? null) ?: null,
                'capacity_per_slot' => $arguments['capacity_per_slot'] ?? null,
                'sort_order'        => (int) ($arguments['sort_order'] ?? 0),
                'is_active'         => (bool) ($arguments['is_active'] ?? true),
            ]);

            return ToolResult::success([
                'id'       => $station->id,
                'name'     => $station->name,
                'venue_id' => $station->venue_id,
            ], ['created' => true]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Anlegen der Abholstation: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'stations', 'create'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['creates'],
            'risk_level'    => 'write',
        ];
    }
}
