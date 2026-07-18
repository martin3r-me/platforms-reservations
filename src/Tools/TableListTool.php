<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\FloorPlan;

/**
 * Listet die Tische eines Tischplans.
 */
class TableListTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.tables.GET';
    }

    public function getDescription(): string
    {
        return 'GET /reservation/tables - Listet die Tische eines Tischplans. '
            . 'REST-Parameter: floor_plan_id (Pflicht).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'floor_plan_id' => ['type' => 'integer', 'description' => 'ID des Tischplans.'],
            ],
            'required'   => ['floor_plan_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $context->team?->id;

            if (!$teamId) {
                return ToolResult::error('Kein Team-Kontext vorhanden.', 'MISSING_TEAM');
            }

            $plan = FloorPlan::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->with(['tables' => fn ($q) => $q->withoutGlobalScope('team')])
                ->find((int) ($arguments['floor_plan_id'] ?? 0));

            if (!$plan) {
                return ToolResult::error('Tischplan nicht gefunden.', 'NOT_FOUND');
            }

            $tables = $plan->tables->map(fn ($t) => [
                'id'        => $t->id,
                'label'     => $t->label,
                'capacity'  => $t->capacity,
                'shape'     => $t->shape,
                'is_active' => $t->is_active,
            ]);

            return ToolResult::success([
                'floor_plan_id' => $plan->id,
                'count'         => $tables->count(),
                'tables'        => $tables->all(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Laden der Tische: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['reservation', 'tables', 'list'],
            'requires_team' => true,
            'read_only'     => true,
            'idempotent'    => true,
            'risk_level'    => 'safe',
        ];
    }
}
