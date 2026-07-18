<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\Event;

/**
 * Listet Buchungen des aktiven Teams (optional gefiltert).
 */
class ListBookingsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.bookings.GET';
    }

    public function getDescription(): string
    {
        return 'GET /reservation/bookings - Listet Buchungen des aktiven Teams. REST-Parameter (optional): '
            . 'event_uuid (nur Buchungen eines Termins), date (YYYY-MM-DD), '
            . 'status (pending|confirmed|cancelled|no_show|completed), limit (1-200, Default 50).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'event_uuid' => [
                    'type'        => 'string',
                    'description' => 'UUID eines Termins – nur dessen Buchungen.',
                ],
                'date'       => [
                    'type'        => 'string',
                    'description' => 'Datum YYYY-MM-DD – nur Buchungen an diesem Tag.',
                ],
                'status'     => [
                    'type'        => 'string',
                    'enum'        => ['pending', 'confirmed', 'cancelled', 'no_show', 'completed'],
                    'description' => 'Nur Buchungen mit diesem Status.',
                ],
                'limit'      => [
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

            $query = Booking::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->with(['items', 'event', 'slot', 'table']);

            if (!empty($arguments['event_uuid'])) {
                $eventId = Event::withoutGlobalScope('team')
                    ->where('team_id', $teamId)
                    ->where('uuid', $arguments['event_uuid'])
                    ->value('id');

                if (!$eventId) {
                    return ToolResult::error('Termin nicht gefunden.', 'EVENT_NOT_FOUND');
                }

                $query->where('event_id', $eventId);
            }

            if (!empty($arguments['date'])) {
                $query->whereDate('date', $arguments['date']);
            }

            if (!empty($arguments['status'])) {
                $query->where('status', $arguments['status']);
            }

            $bookings = $query->orderByDesc('date')->limit($limit)->get()->map(fn (Booking $booking) => [
                'uuid'         => $booking->uuid,
                'guest_name'   => $booking->guest_name,
                'guest_count'  => $booking->guest_count,
                'date'         => $booking->date?->toDateString(),
                'status'       => $booking->status,
                'event'        => $booking->event?->name,
                'slot'         => $booking->slot?->name,
                'table'        => $booking->table?->label,
                'items_count'  => $booking->items->count(),
                'total_amount' => round((float) $booking->total_amount, 2),
            ]);

            return ToolResult::success([
                'count'    => $bookings->count(),
                'bookings' => $bookings->all(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Laden der Buchungen: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['reservation', 'bookings', 'list'],
            'requires_team' => true,
            'read_only'     => true,
            'idempotent'    => true,
            'risk_level'    => 'safe',
            'examples'      => [
                'Zeige mir die Buchungen für den Termin X.',
                'Welche bestätigten Buchungen gibt es am 2026-08-29?',
            ],
        ];
    }
}
