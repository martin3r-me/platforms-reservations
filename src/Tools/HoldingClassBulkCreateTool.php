<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\HoldingClass;

/**
 * Legt mehrere Standzeit-/Zeitkritikalitäts-Klassen auf einmal an (#523).
 */
class HoldingClassBulkCreateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.holding-classes.bulk.POST';
    }

    public function getDescription(): string
    {
        return 'POST /reservation/holding-classes/bulk - Legt mehrere Standzeit-Klassen an. REST-Parameter: classes '
            . '(Array von Objekten mit name, optional description, color, sort_order, is_active).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'classes' => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'name'        => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                            'color'       => ['type' => 'string'],
                            'sort_order'  => ['type' => 'integer'],
                            'is_active'   => ['type' => 'boolean'],
                        ],
                        'required'   => ['name'],
                    ],
                ],
            ],
            'required'   => ['classes'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $context->team?->id;

            if (!$teamId) {
                return ToolResult::error('Kein Team-Kontext vorhanden.', 'MISSING_TEAM');
            }

            $rows = $arguments['classes'] ?? [];
            if (!is_array($rows) || $rows === []) {
                return ToolResult::error('Parameter "classes" muss ein nicht-leeres Array sein.', 'VALIDATION_ERROR');
            }

            $created = [];
            $failed  = [];

            foreach ($rows as $i => $row) {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '') {
                    $failed[] = ['index' => $i, 'error' => 'name fehlt'];
                    continue;
                }

                $class = HoldingClass::create([
                    'team_id'     => $teamId,
                    'name'        => $name,
                    'description' => $row['description'] ?? null,
                    'color'       => $row['color'] ?? null,
                    'sort_order'  => (int) ($row['sort_order'] ?? (($i + 1) * 10)),
                    'is_active'   => $row['is_active'] ?? true,
                ]);

                $created[] = ['id' => $class->id, 'name' => $class->name];
            }

            return ToolResult::success([
                'created_count' => count($created),
                'failed_count'  => count($failed),
                'created'       => $created,
                'failed'        => $failed,
            ], ['created' => count($created)]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Anlegen der Standzeit-Klassen: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'menu', 'holding-classes', 'create', 'bulk'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['creates'],
            'risk_level'    => 'write',
        ];
    }
}
