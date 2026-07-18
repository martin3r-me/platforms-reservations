<?php

namespace Platform\Reservation\Tools;

use Illuminate\Support\Facades\Validator;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\SalesList;

/**
 * Aktualisiert eine Verkaufsliste des aktiven Teams.
 */
class SalesListUpdateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.sales-lists.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /reservation/sales-lists - Aktualisiert eine Verkaufsliste. REST-Parameter: id (Pflicht); '
            . 'name, description, is_default (jeweils optional).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'id'          => ['type' => 'integer'],
                'name'        => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'is_default'  => ['type' => 'boolean'],
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
                'id'          => 'required|integer',
                'name'        => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'is_default'  => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return ToolResult::error($validator->errors()->first(), 'VALIDATION_ERROR');
            }

            $list = SalesList::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->find((int) $arguments['id']);

            if (!$list) {
                return ToolResult::error('Verkaufsliste nicht gefunden.', 'NOT_FOUND');
            }

            $list->update(collect($validator->validated())->only(['name', 'description', 'is_default'])->all());

            return ToolResult::success([
                'id'         => $list->id,
                'name'       => $list->name,
                'is_default' => $list->is_default,
            ], ['updated' => true]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Aktualisieren der Verkaufsliste: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'sales-lists', 'update'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['updates'],
            'risk_level'    => 'write',
        ];
    }
}
