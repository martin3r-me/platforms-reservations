<?php

namespace Platform\Reservation\Tools;

use Illuminate\Support\Facades\Validator;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Tools\Concerns\PflegtKennzeichnung;
use Platform\Reservation\Models\HoldingClass;
use Platform\Reservation\Models\MenuCategory;
use Platform\Reservation\Models\MenuItem;
use Platform\Reservation\Support\BundleComponents;

/**
 * Aktualisiert einen Artikel/eine Speise des aktiven Teams.
 */
class MenuItemUpdateTool implements ToolContract, ToolMetadataContract
{
    use PflegtKennzeichnung;

    public function getName(): string
    {
        return 'reservation.menu-items.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /reservation/menu-items - Aktualisiert einen Artikel. REST-Parameter: id (Pflicht); '
            . 'category_id, holding_class_id (null zum Entfernen; bei Bundles ignoriert – die Standzeit '
            . 'liegt am Bestandteil), name, price, tax_rate (7|19), description, '
            . 'portion_size, available, is_vegetarian, is_vegan, is_alcoholic, is_bundle mit components '
            . '(jeweils optional). Bei Bundles kann price_notice zurueckkommen: ein Hinweis, dass der '
            . 'Bundle-Preis keinen Vorteil gegenueber den Einzelpreisen bringt. Kein Fehler – '
            . 'gespeichert wurde trotzdem –, sollte dem Nutzer aber gemeldet werden. '
            . 'Allergene und Zusatzstoffe als CODES: allergen_codes ("A","C","G") und additive_codes ("1","2","11"). Sie ERSETZEN die bisherigen; ein leeres Array entfernt alle, Weglassen laesst sie unveraendert. Unbekannte Codes kommen als unknown_codes zurueck, statt still zu verschwinden. '
            . 'VIER-AUGEN: Aendert sich bei aktiver Pflicht der INHALT eines freigegebenen Artikels (Name, Preis, Steuersatz, Beschreibung, Portion, Kennzeichnung, Bundle), faellt die Freigabe zurueck auf draft - der Artikel ist dann NICHT mehr gast-sichtbar und braucht eine neue Freigabe. Die Antwort meldet das als approval_reset=true; sag es dem Nutzer. available, category_id und holding_class_id zaehlen nicht als Inhalt.';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'id'            => ['type' => 'integer'],
                'category_id'   => ['type' => 'integer'],
                'holding_class_id' => ['type' => ['integer', 'null'], 'description' => 'Standzeit-Klasse; null entfernt die Zuordnung.'],
                'name'          => ['type' => 'string'],
                'price'         => ['type' => 'number'],
                'tax_rate'      => ['type' => 'number', 'enum' => [7, 19]],
                'description'   => ['type' => 'string'],
                'portion_size'  => ['type' => 'string'],
                'available'     => ['type' => 'boolean'],
                'is_vegetarian' => ['type' => 'boolean'],
                'is_vegan'      => ['type' => 'boolean'],
                'is_alcoholic'  => ['type' => 'boolean'],
                'is_bundle'     => ['type' => 'boolean', 'description' => 'Bundle: mehrere Artikel zu einem Preis.'],
                'components'    => [
                    'type'        => 'array',
                    'description' => 'Bestandteile [{component_id, quantity}]; ersetzt die bisherige Zuordnung. Keine Bundles, nicht der Artikel selbst.',
                    'items'       => ['type' => 'object', 'properties' => [
                        'component_id' => ['type' => 'integer'],
                        'quantity'     => ['type' => 'integer', 'minimum' => 1],
                    ]],
                ],
                'min_age'       => ['type' => ['integer', 'null'], 'enum' => [16, 18, null], 'description' => 'Altersgrenze 16|18|null.'],
                'is_caffeinated' => ['type' => 'boolean'],
                'caffeine_mg'   => ['type' => ['number', 'null'], 'description' => 'mg/100 ml.'],
            ] + self::kennzeichnungSchema(),
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
                'id'            => 'required|integer',
                'category_id'   => 'sometimes|integer',
                'holding_class_id' => 'sometimes|nullable|integer',
                'name'          => 'sometimes|string|max:255',
                'price'         => 'sometimes|numeric|min:0',
                'tax_rate'      => ['sometimes', fn ($a, $v, $fail) => in_array((float) $v, MenuItem::TAX_RATES, true) ?: $fail('tax_rate muss 7 oder 19 sein.')],
                'description'   => 'nullable|string',
                'portion_size'  => 'nullable|string|max:50',
                'available'     => 'sometimes|boolean',
                'is_vegetarian' => 'sometimes|boolean',
                'is_vegan'      => 'sometimes|boolean',
                'is_alcoholic'  => 'sometimes|boolean',
                'is_bundle'     => 'sometimes|boolean',
                'components'    => 'sometimes|array',
                'min_age'       => 'sometimes|nullable|integer|in:16,18',
                'is_caffeinated' => 'sometimes|boolean',
                'caffeine_mg'   => 'sometimes|nullable|numeric|min:0|max:10000',
            ]);

            if ($validator->fails()) {
                return ToolResult::error($validator->errors()->first(), 'VALIDATION_ERROR');
            }

            $item = MenuItem::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->find((int) $arguments['id']);

            if (!$item) {
                return ToolResult::error('Artikel nicht gefunden.', 'NOT_FOUND');
            }

            if (array_key_exists('category_id', $arguments)) {
                $owned = MenuCategory::withoutGlobalScope('team')
                    ->where('team_id', $teamId)
                    ->where('id', (int) $arguments['category_id'])
                    ->exists();

                if (!$owned) {
                    return ToolResult::error('Kategorie nicht gefunden (oder gehört nicht zum Team).', 'CATEGORY_NOT_FOUND');
                }
            }

            if (!empty($arguments['holding_class_id'])
                && !HoldingClass::withoutGlobalScope('team')->where('team_id', $teamId)->where('id', (int) $arguments['holding_class_id'])->exists()) {
                return ToolResult::error('Standzeit-Klasse nicht gefunden (oder gehört nicht zum Team).', 'HOLDING_CLASS_NOT_FOUND');
            }

            $data = collect($validator->validated())->only([
                'category_id', 'holding_class_id', 'name', 'price', 'tax_rate', 'description',
                'portion_size', 'available', 'is_vegetarian', 'is_vegan', 'is_alcoholic',
                'min_age', 'is_caffeinated', 'caffeine_mg', 'is_bundle',
            ])->all();

            if (isset($data['tax_rate'])) {
                $data['tax_rate'] = number_format((float) $data['tax_rate'], 2, '.', '');
            }

            // Bundle-Zustand nach der Änderung: entweder ausdrücklich gesetzt
            // oder der bisherige.
            $isBundle = array_key_exists('is_bundle', $data)
                ? (bool) $data['is_bundle']
                : $item->isBundle();

            $componentsGiven = array_key_exists('components', $arguments);
            $components      = BundleComponents::normalize((array) ($arguments['components'] ?? []));

            if ($isBundle) {
                // Bestandteile nur prüfen, wenn welche mitkommen oder der Artikel
                // gerade erst zum Bundle wird – sonst bleiben die bisherigen.
                if ($componentsGiven || ! $item->isBundle()) {
                    if ($error = BundleComponents::validate($components, $teamId, $item->id)) {
                        return ToolResult::error($error, 'INVALID_BUNDLE');
                    }
                }
            }

            $item->update($data);

            // Vor den Bestandteilen abfragen: wasChanged() gilt fuer den
            // letzten save(), und sync() weiter unten loest keinen aus.
            $inhaltGeaendert = $item->wasChanged(MenuItem::CONTENT_FIELDS);

            $bewegung = ['attached' => [], 'detached' => [], 'updated' => []];

            if ($isBundle && ($componentsGiven || $components !== [])) {
                $bewegung = BundleComponents::apply($item, $components);
            } elseif (! $isBundle) {
                // Kein Bundle mehr: Bestandteile lösen, sonst bliebe ein
                // unsichtbarer Inhalt am Artikel hängen.
                $bewegung = $item->components()->sync([]);
            }

            $kennzeichnung = $this->kennzeichnungSetzen($item, $arguments, $teamId);

            $inhaltGeaendert = $inhaltGeaendert
                || $kennzeichnung['geaendert']
                || $bewegung['attached'] !== []
                || $bewegung['detached'] !== []
                || ($bewegung['updated'] ?? []) !== [];

            // Inhaltlich geaendert und die Vier-Augen-Pflicht gilt? Dann faellt
            // die Freigabe, genau wie in der Oberflaeche - sonst waere das
            // Werkzeug der bequeme Weg an der Pruefung vorbei: einmal
            // freigeben lassen, danach den Inhalt per PATCH austauschen.
            //
            // Ist die Pflicht aus, bleibt die Freigabe stehen. Ein Team, das
            // kein zweites Augenpaar will, soll nicht bei jedem Tippfehler den
            // Artikel aus dem Shop fallen sehen.
            $freigabeZurueckgesetzt = $inhaltGeaendert && $item->requiresReapprovalAfterChange();

            if ($freigabeZurueckgesetzt) {
                $item->resetApproval();
            }

            $priceNotice = $item->is_bundle
                ? BundleComponents::priceNotice($item->load('components'))
                : null;

            return ToolResult::success([
                'id'           => $item->id,
                'name'         => $item->name,
                'price'        => (float) $item->price,
                'tax_rate'     => (float) $item->tax_rate,
                'is_bundle'    => $item->is_bundle,
                'components'   => $item->components()->count(),
                // Kein Fehler: gespeichert ist gespeichert. Nur ein Hinweis, damit
                // ein Bundle ohne Preisvorteil nicht unbemerkt bleibt.
                'price_notice' => $priceNotice,
                // Uebergangene Codes zurueckmelden statt still zu schlucken:
                // Eine verlorene Kennzeichnung faellt sonst niemandem auf.
                'unknown_codes' => $kennzeichnung['unbekannt'],
                'approval_status' => $item->approval_status,
                // Muss gemeldet werden: Der Artikel ist damit nicht mehr
                // gast-sichtbar, und das darf der Mensch nicht erst im Shop
                // merken.
                'approval_reset'  => $freigabeZurueckgesetzt,
            ], ['updated' => true]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Aktualisieren des Artikels: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'menu', 'items', 'update'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['updates'],
            'risk_level'    => 'write',
        ];
    }
}
