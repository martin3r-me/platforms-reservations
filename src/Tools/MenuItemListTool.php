<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\MenuItem;

/**
 * Listet die Artikel/Speisen des aktiven Teams (optional je Kategorie).
 */
class MenuItemListTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.menu-items.GET';
    }

    public function getDescription(): string
    {
        return 'GET /reservation/menu-items - Listet Artikel/Speisen des aktiven Teams. REST-Parameter (optional): '
            . 'category_id (nur einer Kategorie), limit (1-500, Default 100).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'category_id' => ['type' => 'integer', 'description' => 'Nur Artikel dieser Kategorie.'],
                'limit'       => ['type' => 'integer', 'minimum' => 1, 'maximum' => 500, 'description' => 'Default 100.'],
            ],
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

            $limit = max(1, min(500, (int) ($arguments['limit'] ?? 100)));

            $query = MenuItem::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->with('category');

            if (!empty($arguments['category_id'])) {
                $query->where('category_id', (int) $arguments['category_id']);
            }

            $items = $query->orderBy('sort_order')->limit($limit)->get()->map(fn (MenuItem $item) => [
                'id'              => $item->id,
                'name'            => $item->name,
                'category'        => $item->category?->name,
                'category_id'     => $item->category_id,
                'portion_size'    => $item->portion_size,
                'price'           => (float) $item->price,
                'tax_rate'        => (float) $item->tax_rate,
                'available'       => $item->available,
                'is_vegetarian'   => $item->is_vegetarian,
                'is_vegan'        => $item->is_vegan,
                'is_alcoholic'    => $item->is_alcoholic,
                'approval_status' => $item->approval_status,
            ]);

            return ToolResult::success([
                'count' => $items->count(),
                'items' => $items->all(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Laden der Artikel: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['reservation', 'menu', 'items', 'list'],
            'requires_team' => true,
            'read_only'     => true,
            'idempotent'    => true,
            'risk_level'    => 'safe',
        ];
    }
}
