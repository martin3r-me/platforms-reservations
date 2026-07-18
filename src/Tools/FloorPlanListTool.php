<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\FloorPlan;

/**
 * Listet die Tischpläne des aktiven Teams (optional je Venue).
 */
class FloorPlanListTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.floor-plans.GET';
    }

    public function getDescription(): string
    {
        return 'GET /reservation/floor-plans - Listet Tischpläne des aktiven Teams. '
            . 'REST-Parameter (optional): venue_id (nur eines Venues).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'venue_id' => ['type' => 'integer', 'description' => 'Nur Tischpläne dieses Venues.'],
            ],
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

            $query = FloorPlan::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->with('venue')
                ->withCount(['tables' => fn ($q) => $q->withoutGlobalScope('team')]);

            if (!empty($arguments['venue_id'])) {
                $query->where('venue_id', (int) $arguments['venue_id']);
            }

            $plans = $query->orderBy('name')->get()->map(fn (FloorPlan $p) => [
                'id'           => $p->id,
                'name'         => $p->name,
                'venue'        => $p->venue?->name,
                'venue_id'     => $p->venue_id,
                'is_active'    => $p->is_active,
                'tables_count' => $p->tables_count,
            ]);

            return ToolResult::success([
                'count'       => $plans->count(),
                'floor_plans' => $plans->all(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Laden der Tischpläne: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['reservation', 'floor-plans', 'list'],
            'requires_team' => true,
            'read_only'     => true,
            'idempotent'    => true,
            'risk_level'    => 'safe',
        ];
    }
}
