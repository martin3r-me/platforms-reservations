<?php

namespace Platform\Reservation\Tools;

use Illuminate\Support\Facades\Validator;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\HoldingClass;

/**
 * Aktualisiert eine Standzeit-/Zeitkritikalitäts-Klasse des aktiven Teams (#523).
 */
class HoldingClassUpdateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.holding-classes.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /reservation/holding-classes - Aktualisiert eine Standzeit-Klasse. REST-Parameter: '
            . 'id (Pflicht); name, description, color, sort_order, is_active (jeweils optional).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'id'          => ['type' => 'integer', 'description' => 'ID der Standzeit-Klasse.'],
                'name'        => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'color'       => ['type' => 'string', 'description' => 'Hex-Farbe #rrggbb.'],
                'sort_order'  => ['type' => 'integer'],
                'is_active'   => ['type' => 'boolean'],
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

            $validator = Validator::make($arguments, [
                'id'          => 'required|integer',
                'name'        => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'color'       => 'nullable|string|max:7',
                'sort_order'  => 'sometimes|integer',
                'is_active'   => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return ToolResult::error($validator->errors()->first(), 'VALIDATION_ERROR');
            }

            $class = HoldingClass::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->find((int) $arguments['id']);

            if (!$class) {
                return ToolResult::error('Standzeit-Klasse nicht gefunden.', 'NOT_FOUND');
            }

            $class->update(
                collect($validator->validated())->only(['name', 'description', 'color', 'sort_order', 'is_active'])->all()
            );

            return ToolResult::success([
                'id'        => $class->id,
                'name'      => $class->name,
                'is_active' => $class->is_active,
            ], ['updated' => true]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Aktualisieren der Standzeit-Klasse: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'menu', 'holding-classes', 'update'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['updates'],
            'risk_level'    => 'write',
        ];
    }
}
