<?php

namespace Platform\Reservation\Tools;

use Illuminate\Support\Facades\Validator;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\Additive;

/**
 * Legt einen Zusatzstoff für das aktive Team an.
 */
class AdditiveCreateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.additives.POST';
    }

    public function getDescription(): string
    {
        return 'POST /reservation/additives - Legt einen Zusatzstoff für das aktive Team an. '
            . 'REST-Parameter: code (Pflicht, z.B. "1"), name (Pflicht), icon (optional).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'code' => ['type' => 'string', 'description' => 'Kurzcode (Legende), z.B. "1".'],
                'name' => ['type' => 'string', 'description' => 'Bezeichnung, z.B. "mit Farbstoff".'],
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

            $data            = $validator->validated();
            $data['team_id'] = $teamId;

            $additive = Additive::create($data);

            return ToolResult::success([
                'id'   => $additive->id,
                'code' => $additive->code,
                'name' => $additive->name,
                'icon' => $additive->icon,
            ], ['created' => true]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Anlegen des Zusatzstoffs: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'additives', 'create'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['creates'],
            'risk_level'    => 'write',
        ];
    }
}
