<?php

namespace Platform\Reservation\Tools;

use Illuminate\Support\Facades\Validator;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\MenuCategory;
use Platform\Reservation\Models\MenuItem;

/**
 * Aktualisiert einen Artikel/eine Speise des aktiven Teams.
 */
class MenuItemUpdateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.menu-items.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /reservation/menu-items - Aktualisiert einen Artikel. REST-Parameter: id (Pflicht); '
            . 'category_id, name, price, tax_rate (7|19), description, portion_size, available, '
            . 'is_vegetarian, is_vegan, is_alcoholic (jeweils optional).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'id'            => ['type' => 'integer'],
                'category_id'   => ['type' => 'integer'],
                'name'          => ['type' => 'string'],
                'price'         => ['type' => 'number'],
                'tax_rate'      => ['type' => 'number', 'enum' => [7, 19]],
                'description'   => ['type' => 'string'],
                'portion_size'  => ['type' => 'string'],
                'available'     => ['type' => 'boolean'],
                'is_vegetarian' => ['type' => 'boolean'],
                'is_vegan'      => ['type' => 'boolean'],
                'is_alcoholic'  => ['type' => 'boolean'],
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
                'id'            => 'required|integer',
                'category_id'   => 'sometimes|integer',
                'name'          => 'sometimes|string|max:255',
                'price'         => 'sometimes|numeric|min:0',
                'tax_rate'      => ['sometimes', fn ($a, $v, $fail) => in_array((float) $v, MenuItem::TAX_RATES, true) ?: $fail('tax_rate muss 7 oder 19 sein.')],
                'description'   => 'nullable|string',
                'portion_size'  => 'nullable|string|max:50',
                'available'     => 'sometimes|boolean',
                'is_vegetarian' => 'sometimes|boolean',
                'is_vegan'      => 'sometimes|boolean',
                'is_alcoholic'  => 'sometimes|boolean',
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

            $data = collect($validator->validated())->only([
                'category_id', 'name', 'price', 'tax_rate', 'description',
                'portion_size', 'available', 'is_vegetarian', 'is_vegan', 'is_alcoholic',
            ])->all();

            if (isset($data['tax_rate'])) {
                $data['tax_rate'] = number_format((float) $data['tax_rate'], 2, '.', '');
            }

            $item->update($data);

            return ToolResult::success([
                'id'       => $item->id,
                'name'     => $item->name,
                'price'    => (float) $item->price,
                'tax_rate' => (float) $item->tax_rate,
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
