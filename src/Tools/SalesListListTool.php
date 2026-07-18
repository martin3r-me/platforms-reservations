<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\SalesList;

/**
 * Listet die Verkaufslisten des aktiven Teams.
 */
class SalesListListTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.sales-lists.GET';
    }

    public function getDescription(): string
    {
        return 'GET /reservation/sales-lists - Listet die Verkaufslisten des aktiven Teams (mit Artikelanzahl). '
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

            $lists = SalesList::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->withCount('menuItems')
                ->orderBy('name')
                ->get()
                ->map(fn (SalesList $l) => [
                    'id'          => $l->id,
                    'name'        => $l->name,
                    'description' => $l->description,
                    'is_default'  => $l->is_default,
                    'items_count' => $l->menu_items_count,
                ]);

            return ToolResult::success([
                'count'       => $lists->count(),
                'sales_lists' => $lists->all(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Laden der Verkaufslisten: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['reservation', 'sales-lists', 'list'],
            'requires_team' => true,
            'read_only'     => true,
            'idempotent'    => true,
            'risk_level'    => 'safe',
        ];
    }
}
