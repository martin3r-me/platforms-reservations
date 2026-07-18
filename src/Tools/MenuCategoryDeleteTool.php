<?php

namespace Platform\Reservation\Tools;

use Illuminate\Database\QueryException;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\MenuCategory;

/**
 * Löscht eine Menü-Kategorie des aktiven Teams (inkl. ihrer Artikel).
 */
class MenuCategoryDeleteTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.menu-categories.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /reservation/menu-categories - Löscht eine Kategorie samt ihrer Artikel. '
            . 'REST-Parameter: id (Pflicht). Nicht möglich, wenn Artikel der Kategorie bereits bestellt wurden.';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'ID der zu löschenden Kategorie.'],
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

            $category = MenuCategory::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->find((int) ($arguments['id'] ?? 0));

            if (!$category) {
                return ToolResult::error('Kategorie nicht gefunden.', 'NOT_FOUND');
            }

            try {
                $category->delete();
            } catch (QueryException $e) {
                return ToolResult::error(
                    'Die Kategorie enthält bereits bestellte Artikel und kann nicht gelöscht werden.',
                    'HAS_ORDERS',
                );
            }

            return ToolResult::success(['deleted' => true, 'id' => (int) $arguments['id']]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Löschen der Kategorie: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'             => 'action',
            'tags'                 => ['reservation', 'menu', 'categories', 'delete'],
            'requires_team'        => true,
            'read_only'            => false,
            'side_effects'         => ['deletes'],
            'risk_level'           => 'destructive',
            'confirmation_required'=> true,
        ];
    }
}
