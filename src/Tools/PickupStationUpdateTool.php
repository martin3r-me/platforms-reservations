<?php

namespace Platform\Reservation\Tools;

use Illuminate\Support\Facades\Validator;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\PickupStation;

/**
 * Ändert eine Abholstation. Die Lage im Tischplan bleibt dem Editor vorbehalten.
 */
class PickupStationUpdateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.pickup-stations.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /reservation/pickup-stations - Aendert eine Abholstation. REST-Parameter: id (Pflicht); '
            . 'name, description, capacity_per_slot (null zum Entfernen der Grenze = unbegrenzt), sort_order, '
            . 'is_active (jeweils optional). Die LAGE im Tischplan wird hier nicht gesetzt - sie entsteht durch '
            . 'Ziehen im Tischplan-Editor, wo man sieht, wohin man sie legt.';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'id'                => ['type' => 'integer'],
                'name'              => ['type' => 'string'],
                'description'       => ['type' => 'string'],
                'capacity_per_slot' => ['type' => ['integer', 'null'], 'minimum' => 1, 'description' => 'Gaeste JE PAUSE; null = unbegrenzt.'],
                'sort_order'        => ['type' => 'integer'],
                'is_active'         => ['type' => 'boolean'],
            ],
            'required'   => ['id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $context->team?->id;

            if (! $teamId) {
                return ToolResult::error('Kein Team-Kontext vorhanden.', 'MISSING_TEAM');
            }

            $validator = Validator::make($arguments, [
                'id'                => 'required|integer',
                'name'              => 'nullable|string|max:255',
                'description'       => 'nullable|string|max:1000',
                'capacity_per_slot' => 'nullable|integer|min:1|max:9999',
                'sort_order'        => 'nullable|integer',
                'is_active'         => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return ToolResult::error($validator->errors()->first(), 'VALIDATION_ERROR');
            }

            $station = PickupStation::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->find((int) $arguments['id']);

            if (! $station) {
                return ToolResult::error('Abholstation nicht gefunden.', 'NOT_FOUND');
            }

            // Nur, was wirklich mitgeschickt wurde. array_key_exists statt
            // isset, damit ein ausdrueckliches null bei capacity_per_slot
            // ankommt - das ist die einzige Art, eine Grenze wieder
            // aufzuheben, und isset() wuerde genau das verschlucken.
            $daten = [];

            foreach (['name', 'description', 'sort_order', 'is_active'] as $feld) {
                if (array_key_exists($feld, $arguments)) {
                    $daten[$feld] = $arguments[$feld];
                }
            }

            if (array_key_exists('capacity_per_slot', $arguments)) {
                $daten['capacity_per_slot'] = $arguments['capacity_per_slot'];
            }

            $station->update($daten);

            return ToolResult::success([
                'id'                => $station->id,
                'name'              => $station->name,
                'capacity_per_slot' => $station->capacity_per_slot,
                'is_active'         => (bool) $station->is_active,
            ], ['updated' => true]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Aendern der Abholstation: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'stations', 'update'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['updates'],
            'risk_level'    => 'write',
        ];
    }
}
