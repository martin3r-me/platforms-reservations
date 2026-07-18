<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\Venue;

/**
 * Listet die Venues (Spielstätten) des aktiven Teams.
 */
class VenueListTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.venues.GET';
    }

    public function getDescription(): string
    {
        return 'GET /reservation/venues - Listet die Venues des aktiven Teams (mit Anzahl Tischpläne). '
            . 'REST-Parameter: keine.';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => new \stdClass(),
            'required'   => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $context->team?->id;

            if (!$teamId) {
                return ToolResult::error('Kein Team-Kontext vorhanden.', 'MISSING_TEAM');
            }

            $venues = Venue::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->withCount(['floorPlans' => fn ($q) => $q->withoutGlobalScope('team')])
                ->orderBy('name')
                ->get()
                ->map(fn (Venue $v) => [
                    'id'                => $v->id,
                    'name'              => $v->name,
                    'city'              => $v->city,
                    'is_active'         => $v->is_active,
                    'floor_plans_count' => $v->floor_plans_count,
                ]);

            return ToolResult::success([
                'count'  => $venues->count(),
                'venues' => $venues->all(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Laden der Venues: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['reservation', 'venues', 'list'],
            'requires_team' => true,
            'read_only'     => true,
            'idempotent'    => true,
            'risk_level'    => 'safe',
        ];
    }
}
