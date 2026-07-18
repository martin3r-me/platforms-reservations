<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\Allergen;

/**
 * Listet die Allergene des aktiven Teams.
 */
class AllergenListTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.allergens.GET';
    }

    public function getDescription(): string
    {
        return 'GET /reservation/allergens - Listet die Allergene des aktiven Teams (Code, Name, Icon). '
            . 'REST-Parameter: keine.';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => new \stdClass(),
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

            $allergens = Allergen::where('team_id', $teamId)
                ->orderBy('code')
                ->get()
                ->map(fn (Allergen $a) => [
                    'id'   => $a->id,
                    'code' => $a->code,
                    'name' => $a->name,
                    'icon' => $a->icon,
                ]);

            return ToolResult::success([
                'count'     => $allergens->count(),
                'allergens' => $allergens->all(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Laden der Allergene: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['reservation', 'allergens', 'list'],
            'requires_team' => true,
            'read_only'     => true,
            'idempotent'    => true,
            'risk_level'    => 'safe',
        ];
    }
}
