<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\FloorPlan;

/**
 * Löscht einen Tischplan des aktiven Teams (inkl. seiner Tische).
 */
class FloorPlanDeleteTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.floor-plans.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /reservation/floor-plans - Löscht einen Tischplan samt Tischen. '
            . 'REST-Parameter: id (Pflicht). Bestehende Buchungen bleiben (Tischbezug wird gelöst).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'ID des zu löschenden Tischplans.'],
            ],
            'required'   => ['id'],
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
                ->find((int) ($arguments['id'] ?? 0));

            if (!$plan) {
                return ToolResult::error('Tischplan nicht gefunden.', 'NOT_FOUND');
            }

            $plan->delete();

            return ToolResult::success(['deleted' => true, 'id' => (int) $arguments['id']]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Löschen des Tischplans: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'             => 'action',
            'tags'                 => ['reservation', 'floor-plans', 'delete'],
            'requires_team'        => true,
            'read_only'            => false,
            'side_effects'         => ['deletes'],
            'risk_level'           => 'destructive',
            'confirmation_required'=> true,
        ];
    }
}
