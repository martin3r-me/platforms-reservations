<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\Additive;

/**
 * Legt mehrere Zusatzstoffe auf einmal an (idempotent je Code).
 */
class AdditiveBulkCreateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.additives.bulk.POST';
    }

    public function getDescription(): string
    {
        return 'POST /reservation/additives/bulk - Legt mehrere Zusatzstoffe an. REST-Parameter: additives '
            . '(Array von Objekten mit code, name, optional icon). Idempotent je Code (updateOrCreate).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'additives' => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'code' => ['type' => 'string'],
                            'name' => ['type' => 'string'],
                            'icon' => ['type' => 'string'],
                        ],
                        'required'   => ['code', 'name'],
                    ],
                ],
            ],
            'required'   => ['additives'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $context->team?->id;

            if (!$teamId) {
                return ToolResult::error('Kein Team-Kontext vorhanden.', 'MISSING_TEAM');
            }

            $rows = $arguments['additives'] ?? [];
            if (!is_array($rows) || $rows === []) {
                return ToolResult::error('Parameter "additives" muss ein nicht-leeres Array sein.', 'VALIDATION_ERROR');
            }

            $saved  = [];
            $failed = [];

            foreach ($rows as $i => $row) {
                $code = trim((string) ($row['code'] ?? ''));
                $name = trim((string) ($row['name'] ?? ''));
                if ($code === '' || $name === '') {
                    $failed[] = ['index' => $i, 'error' => 'code/name fehlt'];
                    continue;
                }

                $additive = Additive::updateOrCreate(
                    ['team_id' => $teamId, 'code' => $code],
                    ['name' => $name, 'icon' => $row['icon'] ?? null],
                );

                $saved[] = ['id' => $additive->id, 'code' => $additive->code, 'name' => $additive->name];
            }

            return ToolResult::success([
                'saved_count'  => count($saved),
                'failed_count' => count($failed),
                'saved'        => $saved,
                'failed'       => $failed,
            ], ['created' => count($saved)]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Anlegen der Zusatzstoffe: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'additives', 'create', 'bulk'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['creates', 'updates'],
            'risk_level'    => 'write',
        ];
    }
}
