<?php

namespace Platform\Reservation\Tools;

use Illuminate\Support\Facades\Validator;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\Venue;

/**
 * Aktualisiert ein Venue des aktiven Teams.
 */
class VenueUpdateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.venues.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /reservation/venues - Aktualisiert ein Venue. REST-Parameter: id (Pflicht); '
            . 'name, address, city, postal_code, country, phone, email, notes, is_active (jeweils optional).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'id'          => ['type' => 'integer'],
                'name'        => ['type' => 'string'],
                'address'     => ['type' => 'string'],
                'city'        => ['type' => 'string'],
                'postal_code' => ['type' => 'string'],
                'country'     => ['type' => 'string'],
                'phone'       => ['type' => 'string'],
                'email'       => ['type' => 'string'],
                'notes'       => ['type' => 'string'],
                'is_active'   => ['type' => 'boolean'],
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
                'id'          => 'required|integer',
                'name'        => 'sometimes|string|max:255',
                'address'     => 'nullable|string|max:255',
                'city'        => 'nullable|string|max:255',
                'postal_code' => 'nullable|string|max:20',
                'country'     => 'nullable|string|max:100',
                'phone'       => 'nullable|string|max:50',
                'email'       => 'nullable|email|max:255',
                'notes'       => 'nullable|string',
                'is_active'   => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return ToolResult::error($validator->errors()->first(), 'VALIDATION_ERROR');
            }

            $venue = Venue::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->find((int) $arguments['id']);

            if (!$venue) {
                return ToolResult::error('Venue nicht gefunden.', 'NOT_FOUND');
            }

            $venue->update(collect($validator->validated())->only([
                'name', 'address', 'city', 'postal_code', 'country', 'phone', 'email', 'notes', 'is_active',
            ])->all());

            return ToolResult::success([
                'id'        => $venue->id,
                'name'      => $venue->name,
                'is_active' => $venue->is_active,
            ], ['updated' => true]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Aktualisieren des Venues: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'venues', 'update'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['updates'],
            'risk_level'    => 'write',
        ];
    }
}
