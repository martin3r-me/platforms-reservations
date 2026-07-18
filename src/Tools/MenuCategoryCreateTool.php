<?php

namespace Platform\Reservation\Tools;

use Illuminate\Support\Facades\Validator;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\MenuCategory;

/**
 * Legt eine Menü-Kategorie für das aktive Team an.
 */
class MenuCategoryCreateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.menu-categories.POST';
    }

    public function getDescription(): string
    {
        return 'POST /reservation/menu-categories - Legt eine Menü-Kategorie an. REST-Parameter: '
            . 'name (Pflicht), description (optional), sort_order (optional int), is_active (optional bool).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'name'        => ['type' => 'string', 'description' => 'Name der Kategorie, z.B. "Getränke".'],
                'description' => ['type' => 'string', 'description' => 'Optionale Beschreibung.'],
                'sort_order'  => ['type' => 'integer', 'description' => 'Sortierreihenfolge.'],
                'is_active'   => ['type' => 'boolean', 'description' => 'Aktiv sichtbar (Default true).'],
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
                'sort_order'  => 'nullable|integer',
                'is_active'   => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return ToolResult::error($validator->errors()->first(), 'VALIDATION_ERROR');
            }

            $data            = $validator->validated();
            $data['team_id'] = $teamId;

            $category = MenuCategory::create($data);

            return ToolResult::success([
                'id'        => $category->id,
                'name'      => $category->name,
                'is_active' => $category->is_active,
            ], ['created' => true]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Anlegen der Kategorie: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'menu', 'categories', 'create'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['creates'],
            'risk_level'    => 'write',
        ];
    }
}
