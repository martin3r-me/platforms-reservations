<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\HoldingClass;

/**
 * Listet die Standzeit-/Zeitkritikalitäts-Klassen des aktiven Teams (#523).
 */
class HoldingClassListTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.holding-classes.GET';
    }

    public function getDescription(): string
    {
        return 'GET /reservation/holding-classes - Listet die Standzeit-/Zeitkritikalitäts-Klassen des aktiven Teams '
            . '(z.B. Unbedenklich, Sollte kalt sein, Sollte heiß sein). REST-Parameter: keine.';
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

            $classes = HoldingClass::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->withCount(['menuItems' => fn ($q) => $q->withoutGlobalScope('team')])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (HoldingClass $c) => [
                    'id'          => $c->id,
                    'name'        => $c->name,
                    'description' => $c->description,
                    'color'       => $c->color,
                    'lead_time_minutes' => $c->lead_time_minutes,
                    'sort_order'  => $c->sort_order,
                    'is_active'   => $c->is_active,
                    'items_count' => $c->menu_items_count,
                ]);

            return ToolResult::success([
                'count'   => $classes->count(),
                'classes' => $classes->all(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Laden der Standzeit-Klassen: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['reservation', 'menu', 'holding-classes', 'list'],
            'requires_team' => true,
            'read_only'     => true,
            'idempotent'    => true,
            'risk_level'    => 'safe',
        ];
    }
}
