<?php

namespace Platform\Reservation\Tools;

use Illuminate\Database\QueryException;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\MenuItem;

/**
 * Löscht einen Artikel des aktiven Teams (sofern nicht bereits bestellt).
 */
class MenuItemDeleteTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.menu-items.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /reservation/menu-items - Löscht einen Artikel. REST-Parameter: id (Pflicht). '
            . 'Bereits bestellte Artikel können nicht gelöscht werden – dann besser available=false setzen.';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'ID des zu löschenden Artikels.'],
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

            $item = MenuItem::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->find((int) ($arguments['id'] ?? 0));

            if (!$item) {
                return ToolResult::error('Artikel nicht gefunden.', 'NOT_FOUND');
            }

            try {
                $item->delete();
            } catch (QueryException $e) {
                return ToolResult::error(
                    'Der Artikel wurde bereits bestellt und kann nicht gelöscht werden. Bitte stattdessen deaktivieren (available=false).',
                    'HAS_ORDERS',
                );
            }

            return ToolResult::success(['deleted' => true, 'id' => (int) $arguments['id']]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Löschen des Artikels: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'             => 'action',
            'tags'                 => ['reservation', 'menu', 'items', 'delete'],
            'requires_team'        => true,
            'read_only'            => false,
            'side_effects'         => ['deletes'],
            'risk_level'           => 'destructive',
            'confirmation_required'=> true,
        ];
    }
}
