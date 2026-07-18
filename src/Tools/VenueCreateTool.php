<?php

namespace Platform\Reservation\Tools;

use Illuminate\Support\Facades\Validator;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\Venue;

/**
 * Legt ein Venue (Spielstätte) für das aktive Team an.
 */
class VenueCreateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.venues.POST';
    }

    public function getDescription(): string
    {
        return 'POST /reservation/venues - Legt ein Venue an. REST-Parameter: name (Pflicht), address, city, '
            . 'postal_code, country, phone, email, notes, is_active (bool).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
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
            'required'   => ['name'],
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
                'name'        => 'required|string|max:255',
                'address'     => 'nullable|string|max:255',
                'city'        => 'nullable|string|max:255',
                'postal_code' => 'nullable|string|max:20',
                'country'     => 'nullable|string|max:100',
                'phone'       => 'nullable|string|max:50',
                'email'       => 'nullable|email|max:255',
                'notes'       => 'nullable|string',
                'is_active'   => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return ToolResult::error($validator->errors()->first(), 'VALIDATION_ERROR');
            }

            $data            = $validator->validated();
            $data['team_id'] = $teamId;

            $venue = Venue::create($data);

            return ToolResult::success([
                'id'        => $venue->id,
                'name'      => $venue->name,
                'is_active' => $venue->is_active,
            ], ['created' => true]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Anlegen des Venues: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'venues', 'create'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['creates'],
            'risk_level'    => 'write',
        ];
    }
}
