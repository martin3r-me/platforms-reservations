<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\MenuCategory;

/**
 * Legt mehrere Menü-Kategorien auf einmal an.
 */
class MenuCategoryBulkCreateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.menu-categories.bulk.POST';
    }

    public function getDescription(): string
    {
        return 'POST /reservation/menu-categories/bulk - Legt mehrere Kategorien an. REST-Parameter: categories '
            . '(Array von Objekten mit name, optional description, sort_order, is_active).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'categories' => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'name'        => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                            'sort_order'  => ['type' => 'integer'],
                            'is_active'   => ['type' => 'boolean'],
                        ],
                        'required'   => ['name'],
                    ],
                ],
            ],
            'required'   => ['categories'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $context->team?->id;

            if (!$teamId) {
                return ToolResult::error('Kein Team-Kontext vorhanden.', 'MISSING_TEAM');
            }

            $rows = $arguments['categories'] ?? [];
            if (!is_array($rows) || $rows === []) {
                return ToolResult::error('Parameter "categories" muss ein nicht-leeres Array sein.', 'VALIDATION_ERROR');
            }

            $created = [];
            $failed  = [];

            foreach ($rows as $i => $row) {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '') {
                    $failed[] = ['index' => $i, 'error' => 'name fehlt'];
                    continue;
                }

                $category = MenuCategory::create([
                    'team_id'     => $teamId,
                    'name'        => $name,
                    'description' => $row['description'] ?? null,
                    'sort_order'  => (int) ($row['sort_order'] ?? 0),
                    'is_active'   => $row['is_active'] ?? true,
                ]);

                $created[] = ['id' => $category->id, 'name' => $category->name];
            }

            return ToolResult::success([
                'created_count' => count($created),
                'failed_count'  => count($failed),
                'created'       => $created,
                'failed'        => $failed,
            ], ['created' => count($created)]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Anlegen der Kategorien: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'menu', 'categories', 'create', 'bulk'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['creates'],
            'risk_level'    => 'write',
        ];
    }
}
