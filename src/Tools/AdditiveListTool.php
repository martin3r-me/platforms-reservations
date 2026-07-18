<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\Additive;

/**
 * Listet die Zusatzstoffe des aktiven Teams.
 */
class AdditiveListTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.additives.GET';
    }

    public function getDescription(): string
    {
        return 'GET /reservation/additives - Listet die Zusatzstoffe des aktiven Teams (Code, Name, Icon). '
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

            $additives = Additive::where('team_id', $teamId)
                ->orderByRaw('CAST(code AS UNSIGNED), code')
                ->get()
                ->map(fn (Additive $a) => [
                    'id'   => $a->id,
                    'code' => $a->code,
                    'name' => $a->name,
                    'icon' => $a->icon,
                ]);

            return ToolResult::success([
                'count'     => $additives->count(),
                'additives' => $additives->all(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Laden der Zusatzstoffe: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['reservation', 'additives', 'list'],
            'requires_team' => true,
            'read_only'     => true,
            'idempotent'    => true,
            'risk_level'    => 'safe',
        ];
    }
}
