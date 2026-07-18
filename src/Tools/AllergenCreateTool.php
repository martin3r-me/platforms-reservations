<?php

namespace Platform\Reservation\Tools;

use Illuminate\Support\Facades\Validator;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\Allergen;

/**
 * Legt ein Allergen für das aktive Team an.
 */
class AllergenCreateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.allergens.POST';
    }

    public function getDescription(): string
    {
        return 'POST /reservation/allergens - Legt ein Allergen für das aktive Team an. '
            . 'REST-Parameter: code (Pflicht, z.B. "A"), name (Pflicht), icon (optional).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'code' => ['type' => 'string', 'description' => 'Kurzcode (Legende), z.B. "A".'],
                'name' => ['type' => 'string', 'description' => 'Bezeichnung, z.B. "Glutenhaltiges Getreide".'],
                'icon' => ['type' => 'string', 'description' => 'Optionales Icon/Emoji.'],
            ],
            'required'   => ['code', 'name'],
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
                'code' => 'required|string|max:10',
                'name' => 'required|string|max:255',
                'icon' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return ToolResult::error($validator->errors()->first(), 'VALIDATION_ERROR');
            }

            $data             = $validator->validated();
            $data['team_id']  = $teamId;

            $allergen = Allergen::create($data);

            return ToolResult::success([
                'id'   => $allergen->id,
                'code' => $allergen->code,
                'name' => $allergen->name,
                'icon' => $allergen->icon,
            ], ['created' => true]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Anlegen des Allergens: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'allergens', 'create'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['creates'],
            'risk_level'    => 'write',
        ];
    }
}
