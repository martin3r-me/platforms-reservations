<?php

namespace Platform\Reservation\Tools;

use Illuminate\Support\Facades\Validator;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\Allergen;

/**
 * Aktualisiert ein Allergen des aktiven Teams.
 */
class AllergenUpdateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.allergens.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /reservation/allergens - Aktualisiert ein Allergen des aktiven Teams. '
            . 'REST-Parameter: id (Pflicht); code, name, icon (jeweils optional).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'id'   => ['type' => 'integer', 'description' => 'ID des Allergens.'],
                'code' => ['type' => 'string', 'description' => 'Neuer Kurzcode.'],
                'name' => ['type' => 'string', 'description' => 'Neue Bezeichnung.'],
                'icon' => ['type' => 'string', 'description' => 'Neues Icon/Emoji.'],
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
                'id'   => 'required|integer',
                'code' => 'sometimes|string|max:10',
                'name' => 'sometimes|string|max:255',
                'icon' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return ToolResult::error($validator->errors()->first(), 'VALIDATION_ERROR');
            }

            $allergen = Allergen::where('team_id', $teamId)->find((int) $arguments['id']);

            if (!$allergen) {
                return ToolResult::error('Allergen nicht gefunden.', 'NOT_FOUND');
            }

            $allergen->update(collect($validator->validated())->only(['code', 'name', 'icon'])->all());

            return ToolResult::success([
                'id'   => $allergen->id,
                'code' => $allergen->code,
                'name' => $allergen->name,
                'icon' => $allergen->icon,
            ], ['updated' => true]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Aktualisieren des Allergens: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'allergens', 'update'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['updates'],
            'risk_level'    => 'write',
        ];
    }
}
