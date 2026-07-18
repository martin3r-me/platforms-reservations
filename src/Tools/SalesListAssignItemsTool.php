<?php

namespace Platform\Reservation\Tools;

use Illuminate\Support\Facades\Validator;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\MenuItem;
use Platform\Reservation\Models\SalesList;

/**
 * Setzt die Artikel-Zuordnung einer Verkaufsliste (ersetzt die bisherige).
 */
class SalesListAssignItemsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.sales-lists.assign.POST';
    }

    public function getDescription(): string
    {
        return 'POST /reservation/sales-lists/assign - Setzt die Artikel einer Verkaufsliste (ersetzt die '
            . 'bisherige Zuordnung). REST-Parameter: sales_list_id (Pflicht), menu_item_ids (Array von IDs). '
            . 'Nur team-eigene Artikel werden übernommen; unbekannte IDs werden ignoriert.';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'sales_list_id' => ['type' => 'integer'],
                'menu_item_ids' => [
                    'type'        => 'array',
                    'items'       => ['type' => 'integer'],
                    'description' => 'Vollständige Liste der zuzuordnenden Artikel-IDs (leer = alle entfernen).',
                ],
            ],
            'required'   => ['sales_list_id', 'menu_item_ids'],
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
                'sales_list_id' => 'required|integer',
                'menu_item_ids' => 'present|array',
                'menu_item_ids.*'=> 'integer',
            ]);

            if ($validator->fails()) {
                return ToolResult::error($validator->errors()->first(), 'VALIDATION_ERROR');
            }

            $list = SalesList::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->find((int) $arguments['sales_list_id']);

            if (!$list) {
                return ToolResult::error('Verkaufsliste nicht gefunden.', 'NOT_FOUND');
            }

            // Nur team-eigene Artikel zulassen (fremde/unbekannte IDs verwerfen).
            $requested = array_map('intval', $arguments['menu_item_ids']);
            $allowed   = MenuItem::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->whereIn('id', $requested)
                ->pluck('id')
                ->all();

            $list->menuItems()->sync($allowed);

            return ToolResult::success([
                'sales_list_id' => $list->id,
                'assigned'      => count($allowed),
                'ignored'       => count($requested) - count($allowed),
            ], ['updated' => true]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Zuordnen der Artikel: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'sales-lists', 'assign', 'items'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['updates'],
            'risk_level'    => 'write',
        ];
    }
}
