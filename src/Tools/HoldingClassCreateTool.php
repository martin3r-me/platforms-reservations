<?php

namespace Platform\Reservation\Tools;

use Illuminate\Support\Facades\Validator;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\HoldingClass;

/**
 * Legt eine Standzeit-/Zeitkritikalitäts-Klasse für das aktive Team an (#523).
 */
class HoldingClassCreateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.holding-classes.POST';
    }

    public function getDescription(): string
    {
        return 'POST /reservation/holding-classes - Legt eine Standzeit-Klasse an. REST-Parameter: '
            . 'name (Pflicht), description (optional), color (optional, Hex #rrggbb), lead_time_minutes (optional int, '
            . 'Vorlaufzeit in Minuten vor Pausenbeginn; null = egal/zeitunkritisch), sort_order (optional int), '
            . 'is_active (optional bool).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'name'        => ['type' => 'string', 'description' => 'z.B. "Sollte heiß sein".'],
                'description' => ['type' => 'string', 'description' => 'Optionale Beschreibung.'],
                'color'       => ['type' => 'string', 'description' => 'Hex-Farbe #rrggbb (optional).'],
                'lead_time_minutes' => ['type' => 'integer', 'description' => 'Vorlaufzeit in Minuten vor Pausenbeginn; weglassen/null = egal.'],
                'sort_order'  => ['type' => 'integer', 'description' => 'Reihenfolge (kleiner = früher).'],
                'is_active'   => ['type' => 'boolean', 'description' => 'Aktiv (Default true).'],
            ],
            'required'   => ['name'],
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
                'name'        => 'required|string|max:255',
                'description' => 'nullable|string',
                'color'       => 'nullable|string|max:7',
                'lead_time_minutes' => 'nullable|integer|min:0|max:1440',
                'sort_order'  => 'nullable|integer',
                'is_active'   => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return ToolResult::error($validator->errors()->first(), 'VALIDATION_ERROR');
            }

            $data            = $validator->validated();
            $data['team_id'] = $teamId;

            $class = HoldingClass::create($data);

            return ToolResult::success([
                'id'        => $class->id,
                'name'      => $class->name,
                'is_active' => $class->is_active,
            ], ['created' => true]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Anlegen der Standzeit-Klasse: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'menu', 'holding-classes', 'create'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['creates'],
            'risk_level'    => 'write',
        ];
    }
}
