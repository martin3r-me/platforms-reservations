<?php

namespace Platform\Reservation\Tools;

use Illuminate\Support\Facades\Validator;
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
 * Setzt Übersetzungen (#522) für ein Objekt in einer beliebigen Sprache.
 * locale = freier Code (z.B. "en", "fr"). locale "de" schreibt die Basis-Werte;
 * leerer Wert entfernt die Übersetzung. Unbekannte Felder werden ignoriert.
 */
class TranslationsUpdateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.translations.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /reservation/translations - Setzt Übersetzungen eines Objekts. REST-Parameter: '
            . 'type (menu-item|menu-category|allergen|additive|checkout-settings), id (Pflicht außer bei '
            . 'checkout-settings), locale (z.B. "en"), values (Objekt { feld: wert }). locale "de" = Basis; '
            . 'leerer Wert löscht die Übersetzung. Übersetzbare Felder: menu-item/menu-category name,description; '
            . 'allergen/additive name; checkout-settings age_check_text,legal_text.';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'type'   => ['type' => 'string', 'enum' => ['menu-item', 'menu-category', 'allergen', 'additive', 'checkout-settings']],
                'id'     => ['type' => 'integer', 'description' => 'ID (nicht nötig bei checkout-settings).'],
                'locale' => ['type' => 'string', 'description' => 'Sprach-Code, z.B. "en", "fr".'],
                'values' => ['type' => 'object', 'description' => 'Feld → Wert, z.B. { "name": "Currywurst", "description": "…" }.'],
            ],
            'required'   => ['type', 'locale', 'values'],
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
                'type'   => 'required|in:menu-item,menu-category,allergen,additive,checkout-settings',
                'id'     => 'nullable|integer',
                'locale' => ['required', 'string', 'regex:/^[a-zA-Z]{2}(_[a-zA-Z]{2})?$/'],
                'values' => 'required|array|min:1',
            ]);

            if ($validator->fails()) {
                return ToolResult::error($validator->errors()->first(), 'VALIDATION_ERROR');
            }

            $type   = (string) $arguments['type'];
            $locale = strtolower($arguments['locale']);
            $target = $this->resolveTarget($type, $arguments['id'] ?? null, $teamId);

            if (!$target) {
                return ToolResult::error('Objekt nicht gefunden (oder unbekannter Typ).', 'NOT_FOUND');
            }

            $allowed = $target->translatableFields();
            $applied = [];
            $ignored = [];

            foreach ((array) $arguments['values'] as $field => $value) {
                if (!in_array($field, $allowed, true)) {
                    $ignored[] = $field;
                    continue;
                }
                $target->setTranslation((string) $field, $locale, $value !== null ? (string) $value : null);
                $applied[] = $field;
            }

            $target->unsetRelation('translations');
            $target->loadMissing('translations');

            return ToolResult::success([
                'type'         => $type,
                'id'           => $target->getKey(),
                'locale'       => $locale,
                'applied'      => $applied,
                'ignored'      => $ignored,
                'translations' => $target->translationsByLocale(),
            ], ['updated' => true]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Speichern der Übersetzungen: ' . $e->getMessage(), 'EXECUTION_ERROR');
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

        $map = [
            'menu-item'     => MenuItem::class,
            'menu-category' => MenuCategory::class,
            'allergen'      => Allergen::class,
            'additive'      => Additive::class,
        ];

        if (!isset($map[$type]) || !$id) {
            return null;
        }

        $class = $map[$type];

        return $class::withoutGlobalScope('team')->where('team_id', $teamId)->find((int) $id);
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'translations', 'i18n', 'update'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['updates'],
            'risk_level'    => 'write',
        ];
    }
}
