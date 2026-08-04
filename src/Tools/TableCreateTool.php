<?php

namespace Platform\Reservation\Tools;

use Illuminate\Support\Facades\Validator;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\FloorPlan;
use Platform\Reservation\Models\Table;

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
            . 'label (Pflicht), capacity (int, Default 2), shape (round|square|rectangle), '
            . 'rotation (0..315 in 45°-Schritten – 90 stellt quer, 45 diagonal; bei runden Tischen ohne Wirkung), '
            . 'x_pct/y_pct (0..1, Mittelpunkt – Default mittig), w_pct (0..1, Breite – Default 0.1). '
            . 'h_pct wird aus w_pct, Form und Seitenverhältnis des Plans berechnet und muss nicht '
            . 'angegeben werden. color wird nicht mehr dargestellt (Tische sind einheitlich einheitlich gefärbt).';
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
                'rotation'      => [
                    'type'        => 'integer',
                    'description' => 'Ausrichtung in Grad, 45°-Schritte (0..315). 90 = quer, 45 = diagonal. '
                        . 'Wird auf den nächsten 45°-Schritt gerundet; runde Tische werden immer auf 0 gesetzt.',
                ],
                'x_pct'         => ['type' => 'number', 'description' => 'Mittelpunkt X (0..1).'],
                'y_pct'         => ['type' => 'number', 'description' => 'Mittelpunkt Y (0..1).'],
                'w_pct'         => ['type' => 'number', 'description' => 'Breite (0..1).'],
                'h_pct'         => [
                    'type'        => 'number',
                    'description' => 'Höhe (0..1). Optional – wird sonst passend zu Breite und Form berechnet. '
                        . 'Ein eigener Wert kann die Proportion verzerren.',
                ],
                'color'         => ['type' => 'string', 'description' => 'Ohne Wirkung, nur für Altbestand.'],
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
                'rotation'      => 'nullable|integer|min:0|max:359',
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

            $shape = $arguments['shape'] ?? 'square';
            $wPct  = (float) ($arguments['w_pct'] ?? 0.1);

            // Höhe passend zur Form ableiten, wenn nicht ausdrücklich gesetzt.
            // Der alte Default 0.1 ignorierte das Seitenverhältnis – ein runder
            // Tisch wurde dadurch zur Ellipse.
            $hPct = isset($arguments['h_pct'])
                ? (float) $arguments['h_pct']
                : $plan->heightForWidth($wPct, $shape);

            $table = $plan->tables()->create([
                'label'    => $arguments['label'],
                'capacity' => (int) ($arguments['capacity'] ?? 2),
                'shape'    => $shape,
                'rotation' => $shape === 'round'
                    ? 0
                    : Table::normalizeRotation((int) ($arguments['rotation'] ?? 0)),
                'color'    => $arguments['color'] ?? null,
                'x_pct'    => (float) ($arguments['x_pct'] ?? 0.5),
                'y_pct'    => (float) ($arguments['y_pct'] ?? 0.5),
                'w_pct'    => $wPct,
                'h_pct'    => $hPct,
            ]);

            return ToolResult::success([
                'id'       => $table->id,
                'label'    => $table->label,
                'capacity' => $table->capacity,
                'shape'    => $table->shape,
                'rotation' => $table->rotation,
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
