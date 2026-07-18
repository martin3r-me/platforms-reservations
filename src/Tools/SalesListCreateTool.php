<?php

namespace Platform\Reservation\Tools;

use Illuminate\Support\Facades\Validator;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\SalesList;

/**
 * Legt eine Verkaufsliste für das aktive Team an.
 */
class SalesListCreateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.sales-lists.POST';
    }

    public function getDescription(): string
    {
        return 'POST /reservation/sales-lists - Legt eine Verkaufsliste an. REST-Parameter: name (Pflicht), '
            . 'description, is_default (bool – Team-Standardliste).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'name'        => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'is_default'  => ['type' => 'boolean'],
            ],
            'required'   => ['name'],
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
                'name'        => 'required|string|max:255',
                'description' => 'nullable|string',
                'is_default'  => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return ToolResult::error($validator->errors()->first(), 'VALIDATION_ERROR');
            }

            $data            = $validator->validated();
            $data['team_id'] = $teamId;

            $list = SalesList::create($data);

            return ToolResult::success([
                'id'         => $list->id,
                'name'       => $list->name,
                'is_default' => $list->is_default,
            ], ['created' => true]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Anlegen der Verkaufsliste: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'sales-lists', 'create'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['creates'],
            'risk_level'    => 'write',
        ];
    }
}
