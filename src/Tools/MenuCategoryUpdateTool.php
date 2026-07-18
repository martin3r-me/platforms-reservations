<?php

namespace Platform\Reservation\Tools;

use Illuminate\Support\Facades\Validator;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\MenuCategory;

/**
 * Aktualisiert eine Menü-Kategorie des aktiven Teams.
 */
class MenuCategoryUpdateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.menu-categories.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /reservation/menu-categories - Aktualisiert eine Kategorie. REST-Parameter: '
            . 'id (Pflicht); name, description, sort_order, is_active (jeweils optional).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'id'          => ['type' => 'integer', 'description' => 'ID der Kategorie.'],
                'name'        => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'sort_order'  => ['type' => 'integer'],
                'is_active'   => ['type' => 'boolean'],
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
                'sort_order'  => 'sometimes|integer',
                'is_active'   => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return ToolResult::error($validator->errors()->first(), 'VALIDATION_ERROR');
            }

            $category = MenuCategory::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->find((int) $arguments['id']);

            if (!$category) {
                return ToolResult::error('Kategorie nicht gefunden.', 'NOT_FOUND');
            }

            $category->update(
                collect($validator->validated())->only(['name', 'description', 'sort_order', 'is_active'])->all()
            );

            return ToolResult::success([
                'id'        => $category->id,
                'name'      => $category->name,
                'is_active' => $category->is_active,
            ], ['updated' => true]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Aktualisieren der Kategorie: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'menu', 'categories', 'update'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['updates'],
            'risk_level'    => 'write',
        ];
    }
}
