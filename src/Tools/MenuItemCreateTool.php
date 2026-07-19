<?php

namespace Platform\Reservation\Tools;

use Illuminate\Support\Facades\Validator;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\HoldingClass;
use Platform\Reservation\Models\MenuCategory;
use Platform\Reservation\Models\MenuItem;

/**
 * Legt einen Artikel/eine Speise für das aktive Team an (Status: Entwurf).
 */
class MenuItemCreateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.menu-items.POST';
    }

    public function getDescription(): string
    {
        return 'POST /reservation/menu-items - Legt einen Artikel an (Freigabestatus: Entwurf). REST-Parameter: '
            . 'category_id (Pflicht), name (Pflicht), price (Pflicht, brutto), tax_rate (7 oder 19, Default 7), '
            . 'holding_class_id (optional, Standzeit-Klasse), description, portion_size, available (bool), '
            . 'is_vegetarian, is_vegan, is_alcoholic (bool).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'category_id'      => ['type' => 'integer', 'description' => 'ID der Kategorie (des Teams).'],
                'holding_class_id' => ['type' => 'integer', 'description' => 'ID der Standzeit-Klasse (optional, des Teams).'],
                'name'          => ['type' => 'string'],
                'price'         => ['type' => 'number', 'description' => 'Bruttopreis in Euro.'],
                'tax_rate'      => ['type' => 'number', 'enum' => [7, 19], 'description' => 'MwSt-Satz (7 oder 19).'],
                'description'   => ['type' => 'string'],
                'portion_size'  => ['type' => 'string', 'description' => 'z.B. "0,3 l".'],
                'available'     => ['type' => 'boolean'],
                'is_vegetarian' => ['type' => 'boolean'],
                'is_vegan'      => ['type' => 'boolean'],
                'is_alcoholic'  => ['type' => 'boolean', 'description' => 'Enthält Alkohol (Label).'],
                'min_age'       => ['type' => ['integer', 'null'], 'enum' => [16, 18, null], 'description' => 'Altersgrenze: 16 (Bier/Wein/Sekt), 18 (Spirituosen), null = keine.'],
                'is_caffeinated' => ['type' => 'boolean', 'description' => 'Koffeinhaltig (Kennzeichnung).'],
                'caffeine_mg'   => ['type' => ['number', 'null'], 'description' => 'Koffeingehalt in mg/100 ml (optional).'],
            ],
            'required'   => ['category_id', 'name', 'price'],
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
                'category_id'      => 'required|integer',
                'holding_class_id' => 'nullable|integer',
                'name'          => 'required|string|max:255',
                'price'         => 'required|numeric|min:0',
                'tax_rate'      => ['nullable', fn ($a, $v, $fail) => in_array((float) $v, MenuItem::TAX_RATES, true) ?: $fail('tax_rate muss 7 oder 19 sein.')],
                'description'   => 'nullable|string',
                'portion_size'  => 'nullable|string|max:50',
                'available'     => 'nullable|boolean',
                'is_vegetarian' => 'nullable|boolean',
                'is_vegan'      => 'nullable|boolean',
                'is_alcoholic'  => 'nullable|boolean',
                'min_age'       => 'nullable|integer|in:16,18',
                'is_caffeinated' => 'nullable|boolean',
                'caffeine_mg'   => 'nullable|numeric|min:0|max:10000',
            ]);

            if ($validator->fails()) {
                return ToolResult::error($validator->errors()->first(), 'VALIDATION_ERROR');
            }

            $categoryOwned = MenuCategory::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->where('id', (int) $arguments['category_id'])
                ->exists();

            if (!$categoryOwned) {
                return ToolResult::error('Kategorie nicht gefunden (oder gehört nicht zum Team).', 'CATEGORY_NOT_FOUND');
            }

            if (!empty($arguments['holding_class_id']) && !$this->ownsHoldingClass($teamId, (int) $arguments['holding_class_id'])) {
                return ToolResult::error('Standzeit-Klasse nicht gefunden (oder gehört nicht zum Team).', 'HOLDING_CLASS_NOT_FOUND');
            }

            $data              = $validator->validated();
            $data['team_id']   = $teamId;
            $data['tax_rate']  = number_format((float) ($arguments['tax_rate'] ?? 7.0), 2, '.', '');

            $item = MenuItem::create($data);

            return ToolResult::success([
                'id'              => $item->id,
                'name'            => $item->name,
                'price'           => (float) $item->price,
                'tax_rate'        => (float) $item->tax_rate,
                'approval_status' => $item->approval_status,
            ], ['created' => true]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Anlegen des Artikels: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    protected function ownsHoldingClass(int $teamId, int $id): bool
    {
        return HoldingClass::withoutGlobalScope('team')->where('team_id', $teamId)->where('id', $id)->exists();
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'menu', 'items', 'create'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['creates'],
            'risk_level'    => 'write',
        ];
    }
}
