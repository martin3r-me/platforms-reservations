<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\Additive;
use Platform\Reservation\Models\Allergen;
use Platform\Reservation\Models\CheckoutSetting;
use Platform\Reservation\Models\MenuCategory;
use Platform\Reservation\Models\MenuItem;

/**
 * Liest die Übersetzungen (#522) eines Objekts: Basis-Sprache (DE) plus alle
 * hinterlegten Sprachen. Unterstützte Typen: menu-item, menu-category,
 * allergen, additive, checkout-settings.
 */
class TranslationsGetTool implements ToolContract, ToolMetadataContract
{
    public const TYPES = [
        'menu-item'     => MenuItem::class,
        'menu-category' => MenuCategory::class,
        'allergen'      => Allergen::class,
        'additive'      => Additive::class,
    ];

    public function getName(): string
    {
        return 'reservation.translations.GET';
    }

    public function getDescription(): string
    {
        return 'GET /reservation/translations - Liest Übersetzungen eines Objekts. REST-Parameter: '
            . 'type (menu-item|menu-category|allergen|additive|checkout-settings), id (Pflicht außer bei '
            . 'checkout-settings). Liefert die übersetzbaren Felder, die Basis-Werte (DE) und je Sprache die Übersetzungen.';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'type' => ['type' => 'string', 'enum' => ['menu-item', 'menu-category', 'allergen', 'additive', 'checkout-settings']],
                'id'   => ['type' => 'integer', 'description' => 'ID des Objekts (nicht nötig bei checkout-settings).'],
            ],
            'required'   => ['type'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $context->team?->id;

            if (!$teamId) {
                return ToolResult::error('Kein Team-Kontext vorhanden.', 'MISSING_TEAM');
            }

            $type   = (string) ($arguments['type'] ?? '');
            $target = $this->resolveTarget($type, $arguments['id'] ?? null, $teamId);

            if (!$target) {
                return ToolResult::error('Objekt nicht gefunden (oder unbekannter Typ).', 'NOT_FOUND');
            }

            $target->loadMissing('translations');

            $base = [];
            foreach ($target->translatableFields() as $field) {
                $base[$field] = $target->getAttribute($field);
            }

            return ToolResult::success([
                'type'                => $type,
                'id'                  => $target->getKey(),
                'translatable_fields' => $target->translatableFields(),
                'base'                => $base,          // Basis-Sprache DE
                'translations'        => $target->translationsByLocale(), // { locale: { field: value } }
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Laden der Übersetzungen: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    protected function resolveTarget(string $type, $id, int $teamId)
    {
        if ($type === 'checkout-settings') {
            $s = CheckoutSetting::forTeam($teamId);
            if (!$s->exists) {
                $s->save();
            }

            return $s;
        }

        if (!isset(self::TYPES[$type]) || !$id) {
            return null;
        }

        $class = self::TYPES[$type];

        return $class::withoutGlobalScope('team')->where('team_id', $teamId)->find((int) $id);
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['reservation', 'translations', 'i18n'],
            'requires_team' => true,
            'read_only'     => true,
            'idempotent'    => true,
            'risk_level'    => 'safe',
        ];
    }
}
