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
 * Aktualisiert einen Tisch des aktiven Teams.
 */
class TableUpdateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.tables.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /reservation/tables - Aktualisiert einen Tisch. REST-Parameter: id (Pflicht); '
            . 'label, capacity, shape (round|square|rectangle), '
            . 'rotation (0..315 in 45°-Schritten – 90 quer, 45 diagonal; runde Tische immer 0), '
            . 'x_pct/y_pct (Mittelpunkt), w_pct (Breite), is_active (optional). '
            . 'Wird w_pct oder shape geändert, rechnet der Server h_pct passend nach – h_pct nur angeben, '
            . 'wenn die Proportion bewusst abweichen soll. color wird nicht mehr dargestellt.';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'id'        => ['type' => 'integer'],
                'label'     => ['type' => 'string'],
                'capacity'  => ['type' => 'integer', 'minimum' => 1],
                'shape'     => ['type' => 'string', 'enum' => ['round', 'square', 'rectangle']],
                'rotation'  => [
                    'type'        => 'integer',
                    'description' => 'Ausrichtung in Grad, 45°-Schritte (0..315). 90 = quer, 45 = diagonal. '
                        . 'Wird gerundet; runde Tische werden immer auf 0 gesetzt.',
                ],
                'x_pct'     => ['type' => 'number', 'description' => 'Mittelpunkt X (0..1).'],
                'y_pct'     => ['type' => 'number', 'description' => 'Mittelpunkt Y (0..1).'],
                'w_pct'     => ['type' => 'number', 'description' => 'Breite (0..1); h_pct folgt automatisch.'],
                'h_pct'     => [
                    'type'        => 'number',
                    'description' => 'Höhe (0..1). Nur setzen, wenn die Proportion bewusst abweichen soll.',
                ],
                'is_active' => ['type' => 'boolean'],
                'color'     => ['type' => 'string', 'description' => 'Ohne Wirkung, nur für Altbestand.'],
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

            $validator = Validator::make($arguments, [
                'id'        => 'required|integer',
                'label'     => 'sometimes|string|max:50',
                'capacity'  => 'sometimes|integer|min:1|max:200',
                'shape'     => 'sometimes|in:round,square,rectangle',
                'rotation'  => 'sometimes|integer|min:0|max:359',
                'color'     => 'nullable|string|max:50',
                'x_pct'     => 'sometimes|numeric|min:0|max:1',
                'y_pct'     => 'sometimes|numeric|min:0|max:1',
                'w_pct'     => 'sometimes|numeric|min:0|max:1',
                'h_pct'     => 'sometimes|numeric|min:0|max:1',
                'is_active' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return ToolResult::error($validator->errors()->first(), 'VALIDATION_ERROR');
            }

            $table = Table::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->find((int) $arguments['id']);

            if (!$table) {
                return ToolResult::error('Tisch nicht gefunden.', 'NOT_FOUND');
            }

            $data = collect($validator->validated())->only([
                'label', 'capacity', 'shape', 'color', 'x_pct', 'y_pct', 'w_pct', 'h_pct', 'is_active',
            ])->all();

            $shape = $data['shape'] ?? $table->shape;

            // Runde Tische sehen gedreht identisch aus – Ausrichtung dort auf 0.
            if (array_key_exists('rotation', $validator->validated()) || isset($data['shape'])) {
                $rotation = array_key_exists('rotation', $validator->validated())
                    ? (int) $validator->validated()['rotation']
                    : (int) $table->rotation;

                $data['rotation'] = $shape === 'round' ? 0 : Table::normalizeRotation($rotation);
            }

            // Breite oder Form geändert, aber keine Höhe mitgegeben? Dann Höhe
            // nachrechnen, sonst bliebe die alte stehen und der Tisch wäre
            // verzerrt – dieselbe Regel wie im Editor.
            if (! array_key_exists('h_pct', $data) && (array_key_exists('w_pct', $data) || isset($data['shape']))) {
                // Ohne globalen Scope laden, wie der restliche Tool-Code: im
                // MCP-Kontext kann das aktive UI-Team des Users vom Team-Kontext
                // des Tools abweichen, die Relation lieferte dann null.
                $plan = FloorPlan::withoutGlobalScope('team')
                    ->where('team_id', $teamId)
                    ->find($table->floor_plan_id);

                $wPct = (float) ($data['w_pct'] ?? $table->w_pct);

                if ($plan) {
                    $data['h_pct'] = $plan->heightForWidth($wPct, $shape);
                }
            }

            $table->update($data);

            return ToolResult::success([
                'id'       => $table->id,
                'label'    => $table->label,
                'capacity' => $table->capacity,
                'shape'    => $table->shape,
                'rotation' => $table->rotation,
            ], ['updated' => true]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Aktualisieren des Tisches: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'tables', 'update'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['updates'],
            'risk_level'    => 'write',
        ];
    }
}
