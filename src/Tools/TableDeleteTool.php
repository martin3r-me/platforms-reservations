<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\Table;

/**
 * Löscht einen Tisch des aktiven Teams.
 */
class TableDeleteTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.tables.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /reservation/tables - Löscht einen Tisch. REST-Parameter: id (Pflicht). '
            . 'Bestehende Buchungen bleiben (Tischbezug wird gelöst).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'ID des zu löschenden Tisches.'],
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

            $table = Table::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->find((int) ($arguments['id'] ?? 0));

            if (!$table) {
                return ToolResult::error('Tisch nicht gefunden.', 'NOT_FOUND');
            }

            $table->delete();

            return ToolResult::success(['deleted' => true, 'id' => (int) $arguments['id']]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Löschen des Tisches: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'             => 'action',
            'tags'                 => ['reservation', 'tables', 'delete'],
            'requires_team'        => true,
            'read_only'            => false,
            'side_effects'         => ['deletes'],
            'risk_level'           => 'destructive',
            'confirmation_required'=> true,
        ];
    }
}
