<?php

namespace Platform\Reservation\Tools;

use Illuminate\Support\Facades\Validator;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
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
            . 'label, capacity, shape (round|square|rectangle), color, x_pct/y_pct/w_pct/h_pct, is_active (optional).';
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
                'color'     => ['type' => 'string'],
                'x_pct'     => ['type' => 'number'],
                'y_pct'     => ['type' => 'number'],
                'w_pct'     => ['type' => 'number'],
                'h_pct'     => ['type' => 'number'],
                'is_active' => ['type' => 'boolean'],
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

            $table->update(collect($validator->validated())->only([
                'label', 'capacity', 'shape', 'color', 'x_pct', 'y_pct', 'w_pct', 'h_pct', 'is_active',
            ])->all());

            return ToolResult::success([
                'id'       => $table->id,
                'label'    => $table->label,
                'capacity' => $table->capacity,
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
