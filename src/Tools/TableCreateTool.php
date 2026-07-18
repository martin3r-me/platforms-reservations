<?php

namespace Platform\Reservation\Tools;

use Illuminate\Support\Facades\Validator;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\FloorPlan;

/**
 * Legt einen Tisch in einem Tischplan des aktiven Teams an.
 */
class TableCreateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.tables.POST';
    }

    public function getDescription(): string
    {
        return 'POST /reservation/tables - Legt einen Tisch an. REST-Parameter: floor_plan_id (Pflicht), '
            . 'label (Pflicht), capacity (int, Default 2), shape (round|square|rectangle), color, '
            . 'x_pct/y_pct/w_pct/h_pct (0..1, Position/Größe im Plan – optional, Default mittig).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'floor_plan_id' => ['type' => 'integer'],
                'label'         => ['type' => 'string', 'description' => 'z.B. "Tisch 1".'],
                'capacity'      => ['type' => 'integer', 'minimum' => 1],
                'shape'         => ['type' => 'string', 'enum' => ['round', 'square', 'rectangle']],
                'color'         => ['type' => 'string'],
                'x_pct'         => ['type' => 'number', 'description' => 'Mittelpunkt X (0..1).'],
                'y_pct'         => ['type' => 'number', 'description' => 'Mittelpunkt Y (0..1).'],
                'w_pct'         => ['type' => 'number', 'description' => 'Breite (0..1).'],
                'h_pct'         => ['type' => 'number', 'description' => 'Höhe (0..1).'],
            ],
            'required'   => ['floor_plan_id', 'label'],
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
                'floor_plan_id' => 'required|integer',
                'label'         => 'required|string|max:50',
                'capacity'      => 'nullable|integer|min:1|max:200',
                'shape'         => 'nullable|in:round,square,rectangle',
                'color'         => 'nullable|string|max:50',
                'x_pct'         => 'nullable|numeric|min:0|max:1',
                'y_pct'         => 'nullable|numeric|min:0|max:1',
                'w_pct'         => 'nullable|numeric|min:0|max:1',
                'h_pct'         => 'nullable|numeric|min:0|max:1',
            ]);

            if ($validator->fails()) {
                return ToolResult::error($validator->errors()->first(), 'VALIDATION_ERROR');
            }

            $plan = FloorPlan::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->find((int) $arguments['floor_plan_id']);

            if (!$plan) {
                return ToolResult::error('Tischplan nicht gefunden (oder gehört nicht zum Team).', 'FLOOR_PLAN_NOT_FOUND');
            }

            $table = $plan->tables()->create([
                'label'    => $arguments['label'],
                'capacity' => (int) ($arguments['capacity'] ?? 2),
                'shape'    => $arguments['shape'] ?? 'square',
                'color'    => $arguments['color'] ?? null,
                'x_pct'    => (float) ($arguments['x_pct'] ?? 0.5),
                'y_pct'    => (float) ($arguments['y_pct'] ?? 0.5),
                'w_pct'    => (float) ($arguments['w_pct'] ?? 0.1),
                'h_pct'    => (float) ($arguments['h_pct'] ?? 0.1),
            ]);

            return ToolResult::success([
                'id'       => $table->id,
                'label'    => $table->label,
                'capacity' => $table->capacity,
                'shape'    => $table->shape,
            ], ['created' => true]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Anlegen des Tisches: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'tables', 'create'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['creates'],
            'risk_level'    => 'write',
        ];
    }
}
