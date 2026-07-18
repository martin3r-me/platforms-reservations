<?php

namespace Platform\Reservation\Tools;

use Illuminate\Support\Facades\Validator;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\FloorPlan;
use Platform\Reservation\Models\SalesList;
use Platform\Reservation\Models\Venue;

/**
 * Legt einen Tischplan für ein Venue des aktiven Teams an.
 */
class FloorPlanCreateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.floor-plans.POST';
    }

    public function getDescription(): string
    {
        return 'POST /reservation/floor-plans - Legt einen Tischplan an. REST-Parameter: venue_id (Pflicht), '
            . 'name (Pflicht), default_sales_list_id (optional), is_active (bool). '
            . 'Tische werden separat über reservation.tables.POST angelegt.';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'venue_id'              => ['type' => 'integer'],
                'name'                  => ['type' => 'string'],
                'default_sales_list_id' => ['type' => 'integer', 'description' => 'Standard-Verkaufsliste (optional).'],
                'is_active'             => ['type' => 'boolean'],
            ],
            'required'   => ['venue_id', 'name'],
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
                'venue_id'              => 'required|integer',
                'name'                  => 'required|string|max:255',
                'default_sales_list_id' => 'nullable|integer',
                'is_active'             => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return ToolResult::error($validator->errors()->first(), 'VALIDATION_ERROR');
            }

            if (!Venue::withoutGlobalScope('team')->where('team_id', $teamId)->where('id', (int) $arguments['venue_id'])->exists()) {
                return ToolResult::error('Venue nicht gefunden (oder gehört nicht zum Team).', 'VENUE_NOT_FOUND');
            }

            if (!empty($arguments['default_sales_list_id'])
                && !SalesList::withoutGlobalScope('team')->where('team_id', $teamId)->where('id', (int) $arguments['default_sales_list_id'])->exists()) {
                return ToolResult::error('Verkaufsliste nicht gefunden (oder gehört nicht zum Team).', 'SALES_LIST_NOT_FOUND');
            }

            $data              = $validator->validated();
            $data['team_id']   = $teamId;

            $plan = FloorPlan::create($data);

            return ToolResult::success([
                'id'        => $plan->id,
                'name'      => $plan->name,
                'venue_id'  => $plan->venue_id,
                'is_active' => $plan->is_active,
            ], ['created' => true]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Anlegen des Tischplans: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'floor-plans', 'create'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['creates'],
            'risk_level'    => 'write',
        ];
    }
}
