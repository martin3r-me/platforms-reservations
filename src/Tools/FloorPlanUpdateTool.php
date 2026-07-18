<?php

namespace Platform\Reservation\Tools;

use Illuminate\Support\Facades\Validator;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\FloorPlan;
use Platform\Reservation\Models\SalesList;

/**
 * Aktualisiert einen Tischplan des aktiven Teams.
 */
class FloorPlanUpdateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.floor-plans.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /reservation/floor-plans - Aktualisiert einen Tischplan. REST-Parameter: id (Pflicht); '
            . 'name, default_sales_list_id, background_rotation (0|90|180|270), is_active (jeweils optional).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'id'                    => ['type' => 'integer'],
                'name'                  => ['type' => 'string'],
                'default_sales_list_id' => ['type' => 'integer'],
                'background_rotation'   => ['type' => 'integer', 'enum' => [0, 90, 180, 270]],
                'is_active'             => ['type' => 'boolean'],
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
                'id'                    => 'required|integer',
                'name'                  => 'sometimes|string|max:255',
                'default_sales_list_id' => 'nullable|integer',
                'background_rotation'   => 'sometimes|in:0,90,180,270',
                'is_active'             => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return ToolResult::error($validator->errors()->first(), 'VALIDATION_ERROR');
            }

            $plan = FloorPlan::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->find((int) $arguments['id']);

            if (!$plan) {
                return ToolResult::error('Tischplan nicht gefunden.', 'NOT_FOUND');
            }

            if (!empty($arguments['default_sales_list_id'])
                && !SalesList::withoutGlobalScope('team')->where('team_id', $teamId)->where('id', (int) $arguments['default_sales_list_id'])->exists()) {
                return ToolResult::error('Verkaufsliste nicht gefunden (oder gehört nicht zum Team).', 'SALES_LIST_NOT_FOUND');
            }

            $plan->update(collect($validator->validated())->only([
                'name', 'default_sales_list_id', 'background_rotation', 'is_active',
            ])->all());

            return ToolResult::success([
                'id'        => $plan->id,
                'name'      => $plan->name,
                'is_active' => $plan->is_active,
            ], ['updated' => true]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Aktualisieren des Tischplans: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'floor-plans', 'update'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['updates'],
            'risk_level'    => 'write',
        ];
    }
}
