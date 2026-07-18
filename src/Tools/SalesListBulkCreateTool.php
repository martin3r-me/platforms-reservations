<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\SalesList;

/**
 * Legt mehrere Verkaufslisten auf einmal an.
 */
class SalesListBulkCreateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.sales-lists.bulk.POST';
    }

    public function getDescription(): string
    {
        return 'POST /reservation/sales-lists/bulk - Legt mehrere Verkaufslisten an. REST-Parameter: sales_lists '
            . '(Array von Objekten mit name, optional description, is_default).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'sales_lists' => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'name'        => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                            'is_default'  => ['type' => 'boolean'],
                        ],
                        'required'   => ['name'],
                    ],
                ],
            ],
            'required'   => ['sales_lists'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $context->team?->id;

            if (!$teamId) {
                return ToolResult::error('Kein Team-Kontext vorhanden.', 'MISSING_TEAM');
            }

            $rows = $arguments['sales_lists'] ?? [];
            if (!is_array($rows) || $rows === []) {
                return ToolResult::error('Parameter "sales_lists" muss ein nicht-leeres Array sein.', 'VALIDATION_ERROR');
            }

            $created = [];
            $failed  = [];

            foreach ($rows as $i => $row) {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '') {
                    $failed[] = ['index' => $i, 'error' => 'name fehlt'];
                    continue;
                }

                $list = SalesList::create([
                    'team_id'     => $teamId,
                    'name'        => $name,
                    'description' => $row['description'] ?? null,
                    'is_default'  => $row['is_default'] ?? false,
                ]);

                $created[] = ['id' => $list->id, 'name' => $list->name, 'is_default' => $list->is_default];
            }

            return ToolResult::success([
                'created_count' => count($created),
                'failed_count'  => count($failed),
                'created'       => $created,
                'failed'        => $failed,
            ], ['created' => count($created)]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Anlegen der Verkaufslisten: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'sales-lists', 'create', 'bulk'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['creates'],
            'risk_level'    => 'write',
        ];
    }
}
