<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\HoldingClass;

/**
 * Löscht eine Standzeit-/Zeitkritikalitäts-Klasse des aktiven Teams (#523).
 * Zugeordnete Artikel bleiben erhalten (Zuordnung wird auf null gesetzt).
 */
class HoldingClassDeleteTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.holding-classes.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /reservation/holding-classes - Löscht eine Standzeit-Klasse. REST-Parameter: id (Pflicht). '
            . 'Zugeordnete Artikel verlieren nur die Zuordnung (holding_class_id = null).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'ID der zu löschenden Standzeit-Klasse.'],
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

            $class = HoldingClass::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->find((int) ($arguments['id'] ?? 0));

            if (!$class) {
                return ToolResult::error('Standzeit-Klasse nicht gefunden.', 'NOT_FOUND');
            }

            $class->delete();

            return ToolResult::success(['deleted' => true, 'id' => (int) $arguments['id']]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Löschen der Standzeit-Klasse: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'             => 'action',
            'tags'                 => ['reservation', 'menu', 'holding-classes', 'delete'],
            'requires_team'        => true,
            'read_only'            => false,
            'side_effects'         => ['deletes'],
            'risk_level'           => 'destructive',
            'confirmation_required'=> true,
        ];
    }
}
