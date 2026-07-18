<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\Allergen;

/**
 * Löscht ein Allergen des aktiven Teams.
 */
class AllergenDeleteTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.allergens.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /reservation/allergens - Löscht ein Allergen des aktiven Teams. '
            . 'REST-Parameter: id (Pflicht). Zuordnungen zu Artikeln werden mit entfernt.';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'ID des zu löschenden Allergens.'],
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

            $allergen = Allergen::where('team_id', $teamId)->find((int) ($arguments['id'] ?? 0));

            if (!$allergen) {
                return ToolResult::error('Allergen nicht gefunden.', 'NOT_FOUND');
            }

            $allergen->delete();

            return ToolResult::success(['deleted' => true, 'id' => (int) $arguments['id']]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Löschen des Allergens: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'             => 'action',
            'tags'                 => ['reservation', 'allergens', 'delete'],
            'requires_team'        => true,
            'read_only'            => false,
            'side_effects'         => ['deletes'],
            'risk_level'           => 'destructive',
            'confirmation_required'=> true,
        ];
    }
}
