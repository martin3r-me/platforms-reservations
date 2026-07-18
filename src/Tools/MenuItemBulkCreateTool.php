<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\Additive;
use Platform\Reservation\Models\Allergen;
use Platform\Reservation\Models\HoldingClass;
use Platform\Reservation\Models\MenuCategory;
use Platform\Reservation\Models\MenuItem;

/**
 * Legt mehrere Artikel/Speisen auf einmal an (Status: Entwurf), inkl. optionaler
 * Allergen-/Zusatzstoff-Zuordnung per Code.
 */
class MenuItemBulkCreateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.menu-items.bulk.POST';
    }

    public function getDescription(): string
    {
        return 'POST /reservation/menu-items/bulk - Legt mehrere Artikel an (Freigabestatus draft). '
            . 'REST-Parameter: items (Array). Je Item: category_id (Pflicht), name (Pflicht), price (Pflicht, brutto), '
            . 'tax_rate (7|19, Default 7), holding_class_id (optional, Standzeit-Klasse), description, portion_size, '
            . 'available, is_vegetarian, is_vegan, is_alcoholic, allergen_codes (Array von Codes), additive_codes (Array von Codes).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'items' => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'category_id'    => ['type' => 'integer'],
                            'holding_class_id' => ['type' => 'integer', 'description' => 'Standzeit-Klasse (optional).'],
                            'name'           => ['type' => 'string'],
                            'price'          => ['type' => 'number'],
                            'tax_rate'       => ['type' => 'number', 'enum' => [7, 19]],
                            'description'    => ['type' => 'string'],
                            'portion_size'   => ['type' => 'string'],
                            'available'      => ['type' => 'boolean'],
                            'is_vegetarian'  => ['type' => 'boolean'],
                            'is_vegan'       => ['type' => 'boolean'],
                            'is_alcoholic'   => ['type' => 'boolean'],
                            'allergen_codes' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'additive_codes' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                        'required'   => ['category_id', 'name', 'price'],
                    ],
                ],
                'approved' => [
                    'type'        => 'boolean',
                    'description' => 'true = Artikel direkt freigeben (gast-sichtbar), statt als Entwurf. Default false.',
                ],
            ],
            'required'   => ['items'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $context->team?->id;

            if (!$teamId) {
                return ToolResult::error('Kein Team-Kontext vorhanden.', 'MISSING_TEAM');
            }

            $rows = $arguments['items'] ?? [];
            if (!is_array($rows) || $rows === []) {
                return ToolResult::error('Parameter "items" muss ein nicht-leeres Array sein.', 'VALIDATION_ERROR');
            }
            if (count($rows) > 500) {
                return ToolResult::error('Maximal 500 Artikel je Aufruf.', 'TOO_MANY');
            }

            $categoryIds     = MenuCategory::withoutGlobalScope('team')->where('team_id', $teamId)->pluck('id')->flip();
            $holdingClassIds = HoldingClass::withoutGlobalScope('team')->where('team_id', $teamId)->pluck('id')->flip();
            $allergenMap = Allergen::where('team_id', $teamId)->pluck('id', 'code'); // code => id
            $additiveMap = Additive::where('team_id', $teamId)->pluck('id', 'code');

            $approve = (bool) ($arguments['approved'] ?? false);

            $created = [];
            $failed  = [];

            foreach ($rows as $i => $row) {
                $name       = trim((string) ($row['name'] ?? ''));
                $categoryId = (int) ($row['category_id'] ?? 0);
                $price      = $row['price'] ?? null;

                if ($name === '' || !is_numeric($price)) {
                    $failed[] = ['index' => $i, 'error' => 'name/price fehlt oder ungültig'];
                    continue;
                }
                if (!$categoryIds->has($categoryId)) {
                    $failed[] = ['index' => $i, 'name' => $name, 'error' => 'category_id gehört nicht zum Team'];
                    continue;
                }

                $rate = (float) ($row['tax_rate'] ?? 7);
                if (!in_array($rate, MenuItem::TAX_RATES, true)) {
                    $failed[] = ['index' => $i, 'name' => $name, 'error' => 'tax_rate muss 7 oder 19 sein'];
                    continue;
                }

                $holdingClassId = isset($row['holding_class_id']) && $row['holding_class_id'] !== null
                    ? (int) $row['holding_class_id']
                    : null;
                if ($holdingClassId !== null && !$holdingClassIds->has($holdingClassId)) {
                    $failed[] = ['index' => $i, 'name' => $name, 'error' => 'holding_class_id gehört nicht zum Team'];
                    continue;
                }

                $data = [
                    'team_id'          => $teamId,
                    'category_id'      => $categoryId,
                    'holding_class_id' => $holdingClassId,
                    'name'          => $name,
                    'description'   => $row['description'] ?? null,
                    'portion_size'  => $row['portion_size'] ?? null,
                    'price'         => $price,
                    'tax_rate'      => number_format($rate, 2, '.', ''),
                    'available'     => $row['available'] ?? true,
                    'is_vegetarian' => $row['is_vegetarian'] ?? false,
                    'is_vegan'      => $row['is_vegan'] ?? false,
                    'is_alcoholic'  => $row['is_alcoholic'] ?? false,
                ];

                if ($approve) {
                    $data['approval_status'] = MenuItem::APPROVAL_APPROVED;
                    $data['approved_by']     = $context->user?->id;
                    $data['approved_at']     = now();
                }

                $item = MenuItem::create($data);

                // Allergene/Zusatzstoffe per Code zuordnen (unbekannte Codes ignorieren).
                if (!empty($row['allergen_codes']) && is_array($row['allergen_codes'])) {
                    $ids = $allergenMap->only($row['allergen_codes'])->values()->all();
                    $item->allergens()->sync($ids);
                }
                if (!empty($row['additive_codes']) && is_array($row['additive_codes'])) {
                    $ids = $additiveMap->only($row['additive_codes'])->values()->all();
                    $item->additives()->sync($ids);
                }

                $created[] = ['id' => $item->id, 'name' => $item->name, 'price' => (float) $item->price];
            }

            return ToolResult::success([
                'created_count' => count($created),
                'failed_count'  => count($failed),
                'created'       => $created,
                'failed'        => $failed,
            ], ['created' => count($created)]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Anlegen der Artikel: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'menu', 'items', 'create', 'bulk'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['creates'],
            'risk_level'    => 'write',
        ];
    }
}
