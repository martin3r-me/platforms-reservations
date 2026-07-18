<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\MenuCategory;

/**
 * Listet die Menü-Kategorien des aktiven Teams.
 */
class MenuCategoryListTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.menu-categories.GET';
    }

    public function getDescription(): string
    {
        return 'GET /reservation/menu-categories - Listet die Menü-Kategorien des aktiven Teams. '
            . 'REST-Parameter: keine.';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => new \stdClass(),
            'required'   => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $context->team?->id;

            if (!$teamId) {
                return ToolResult::error('Kein Team-Kontext vorhanden.', 'MISSING_TEAM');
            }

            $categories = MenuCategory::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->withCount(['menuItems' => fn ($q) => $q->withoutGlobalScope('team')])
                ->orderBy('sort_order')
                ->get()
                ->map(fn (MenuCategory $c) => [
                    'id'          => $c->id,
                    'name'        => $c->name,
                    'description' => $c->description,
                    'sort_order'  => $c->sort_order,
                    'is_active'   => $c->is_active,
                    'items_count' => $c->menu_items_count,
                ]);

            return ToolResult::success([
                'count'      => $categories->count(),
                'categories' => $categories->all(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Laden der Kategorien: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['reservation', 'menu', 'categories', 'list'],
            'requires_team' => true,
            'read_only'     => true,
            'idempotent'    => true,
            'risk_level'    => 'safe',
        ];
    }
}
