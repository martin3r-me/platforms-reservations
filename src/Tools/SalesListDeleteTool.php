<?php

namespace Platform\Reservation\Tools;

use Illuminate\Database\QueryException;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\SalesList;

/**
 * Löscht eine Verkaufsliste des aktiven Teams.
 */
class SalesListDeleteTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.sales-lists.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /reservation/sales-lists - Löscht eine Verkaufsliste. REST-Parameter: id (Pflicht). '
            . 'Nicht möglich, solange die Liste noch einem Termin/Tischplan zugeordnet ist.';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'ID der zu löschenden Verkaufsliste.'],
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

            $list = SalesList::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->find((int) ($arguments['id'] ?? 0));

            if (!$list) {
                return ToolResult::error('Verkaufsliste nicht gefunden.', 'NOT_FOUND');
            }

            try {
                $list->delete();
            } catch (QueryException $e) {
                return ToolResult::error(
                    'Die Verkaufsliste ist noch einem Termin oder Tischplan zugeordnet und kann nicht gelöscht werden.',
                    'IN_USE',
                );
            }

            return ToolResult::success(['deleted' => true, 'id' => (int) $arguments['id']]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Löschen der Verkaufsliste: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'             => 'action',
            'tags'                 => ['reservation', 'sales-lists', 'delete'],
            'requires_team'        => true,
            'read_only'            => false,
            'side_effects'         => ['deletes'],
            'risk_level'           => 'destructive',
            'confirmation_required'=> true,
        ];
    }
}
