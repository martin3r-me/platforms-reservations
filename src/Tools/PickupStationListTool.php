<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\PickupStation;

/**
 * Die Abholstationen eines Hauses – der zweite mögliche Zielort neben dem Tisch.
 */
class PickupStationListTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.pickup-stations.GET';
    }

    public function getDescription(): string
    {
        return 'GET /reservation/pickup-stations - Listet die Abholstationen des aktiven Teams. Eine Abholstation '
            . 'ist der zweite moegliche Zielort einer Buchung: Der Gast sitzt nicht an einem Tisch, sondern holt '
            . 'in der Pause selbst ab. Sie gehoert zum VENUE, nicht zu einem Raum, und taucht in KEINER '
            . 'Platzrechnung auf. capacity_per_slot sind Gaeste JE PAUSE (null = unbegrenzt), keine Sitzplaetze. '
            . 'REST-Parameter (optional): venue_id, include_inactive (bool).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'venue_id'         => ['type' => 'integer'],
                'include_inactive' => ['type' => 'boolean', 'description' => 'Abgeschaltete Stationen mitliefern (Default: nein).'],
            ],
            'required'   => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $context->team?->id;

            if (! $teamId) {
                return ToolResult::error('Kein Team-Kontext vorhanden.', 'MISSING_TEAM');
            }

            $stationen = PickupStation::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->when(! empty($arguments['venue_id']), fn ($q) => $q->where('venue_id', (int) $arguments['venue_id']))
                ->when(empty($arguments['include_inactive']), fn ($q) => $q->where('is_active', true))
                ->with(['venue' => fn ($q) => $q->withoutGlobalScope('team')])
                ->orderBy('venue_id')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            return ToolResult::success([
                'count'    => $stationen->count(),
                'stations' => $stationen->map(fn (PickupStation $s) => [
                    'id'                => $s->id,
                    'name'              => $s->name,
                    'description'       => $s->description,
                    'venue_id'          => $s->venue_id,
                    'venue'             => $s->venue?->name,
                    // Gaeste je Pause, nicht Sitzplaetze. null = unbegrenzt.
                    'capacity_per_slot' => $s->capacity_per_slot,
                    'is_active'         => (bool) $s->is_active,
                    'sort_order'        => (int) $s->sort_order,
                    // Wo sie im Plan gezeichnet ist - null heisst "liegt in
                    // keinem Raum" und ist der Normalfall, kein Mangel.
                    'floor_plan_id'     => $s->floor_plan_id,
                ])->all(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Laden der Abholstationen: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'data',
            'tags'          => ['reservation', 'stations', 'list'],
            'requires_team' => true,
            'read_only'     => true,
            'risk_level'    => 'safe',
        ];
    }
}
