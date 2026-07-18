<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\Event;

/**
 * Listet die Termine (Events) des aktiven Teams.
 */
class ListEventsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.events.GET';
    }

    public function getDescription(): string
    {
        return 'GET /reservation/events - Listet Termine des aktiven Teams. REST-Parameter (optional): '
            . 'status (draft|published|closed), upcoming (bool – nur ab heute), limit (1-200, Default 50).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'status'   => [
                    'type'        => 'string',
                    'enum'        => ['draft', 'published', 'closed'],
                    'description' => 'Nur Termine mit diesem Status.',
                ],
                'upcoming' => [
                    'type'        => 'boolean',
                    'description' => 'Nur Termine ab heute (Datum >= heute).',
                ],
                'limit'    => [
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'maximum'     => 200,
                    'description' => 'Maximale Anzahl (Default 50).',
                ],
            ],
            'required'   => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $context->team?->id;

            if (!$teamId) {
                return ToolResult::error('Kein Team-Kontext vorhanden.', 'MISSING_TEAM');
            }

            $limit = (int) ($arguments['limit'] ?? 50);
            $limit = max(1, min(200, $limit));

            $query = Event::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->with('venue')
                ->withCount([
                    'slots',
                    // Count-Subquery ebenfalls vom Auth-Global-Scope befreien.
                    'bookings' => fn ($q) => $q->withoutGlobalScope('team'),
                ]);

            if (!empty($arguments['status'])) {
                $query->where('status', $arguments['status']);
            }

            if (!empty($arguments['upcoming'])) {
                $query->whereDate('date', '>=', now()->toDateString());
            }

            $events = $query->orderBy('date')->limit($limit)->get()->map(fn (Event $event) => [
                'uuid'           => $event->uuid,
                'name'           => $event->name,
                'date'           => $event->date?->toDateString(),
                'status'         => $event->status->value,
                'venue'          => $event->venue?->name,
                'slots_count'    => $event->slots_count,
                'bookings_count' => $event->bookings_count,
                'order_deadline' => $event->order_deadline_at?->toIso8601String(),
            ]);

            return ToolResult::success([
                'count'  => $events->count(),
                'events' => $events->all(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Laden der Termine: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['reservation', 'events', 'list'],
            'requires_team' => true,
            'read_only'     => true,
            'idempotent'    => true,
            'risk_level'    => 'safe',
            'examples'      => [
                'Zeige mir alle kommenden Termine.',
                'Liste die veröffentlichten Veranstaltungen.',
            ],
        ];
    }
}
