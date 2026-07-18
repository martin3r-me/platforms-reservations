<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\MenuItem;

/**
 * Gibt mehrere Artikel frei (approval_status = approved → gast-sichtbar).
 * Admin-/Import-Aktion: setzt die Freigabe direkt (ohne UI-Vier-Augen-Schritt).
 */
class MenuItemApproveBulkTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.menu-items.approve.bulk.POST';
    }

    public function getDescription(): string
    {
        return 'POST /reservation/menu-items/approve/bulk - Gibt Artikel frei (gast-sichtbar). REST-Parameter: '
            . 'item_ids (Array von Artikel-IDs) ODER all=true (alle noch nicht freigegebenen Artikel des Teams). '
            . 'Setzt die Freigabe direkt (Admin-/Import-Aktion).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'item_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                'all'      => ['type' => 'boolean', 'description' => 'Alle nicht freigegebenen Artikel des Teams freigeben.'],
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

            $ids = $arguments['item_ids'] ?? [];
            $all = (bool) ($arguments['all'] ?? false);

            if (!$all && (!is_array($ids) || $ids === [])) {
                return ToolResult::error('Entweder item_ids (Array) oder all=true angeben.', 'VALIDATION_ERROR');
            }

            $query = MenuItem::withoutGlobalScope('team')->where('team_id', $teamId);

            if (!$all) {
                $query->whereIn('id', array_map('intval', $ids));
            } else {
                $query->where('approval_status', '!=', MenuItem::APPROVAL_APPROVED);
            }

            $approved = $query->update([
                'approval_status' => MenuItem::APPROVAL_APPROVED,
                'approved_by'     => $context->user?->id,
                'approved_at'     => now(),
            ]);

            return ToolResult::success([
                'approved_count' => $approved,
            ], ['updated' => $approved]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler bei der Freigabe: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'menu', 'items', 'approve', 'bulk'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['updates'],
            'risk_level'    => 'write',
        ];
    }
}
