<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Models\FloorPlan;

/**
 * Weist Räume (Tischpläne) mehreren Terminen auf einmal zu – additiv oder
 * ersetzend. Die Ersetzen-Semantik entspricht sales-lists.assign.POST: nach dem
 * Aufruf hat jeder Termin genau die angegebenen Räume.
 */
class EventRoomBulkCreateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.event-rooms.bulk.POST';
    }

    public function getDescription(): string
    {
        return 'POST /reservation/event-rooms/bulk - Weist Tischpläne mehreren Terminen als Räume zu. '
            . 'REST-Parameter: event_uuids (Array, Pflicht); floor_plan_id (einzeln) ODER floor_plan_ids (Array, '
            . 'Reihenfolge = sort_order); fill_threshold_percent (Default 100), capacity_override, sort_order; '
            . 'replace (bool, Default false), force (bool, Default false). '
            . 'Ohne replace additiv und idempotent. MIT replace=true hat jeder Termin danach GENAU die '
            . 'angegebenen Räume – ein Raumwechsel über viele Termine ist damit ein einziger Call statt '
            . 'GET+DELETE je Termin. floor_plan_ids=[] mit replace=true entfernt alle Räume. '
            . 'Räume, auf deren Tischen der Termin Buchungen hat, werden NICHT entfernt (sonst zeigten die '
            . 'Buchungen auf einen nicht mehr zugewiesenen Raum) – mit force=true trotzdem.';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'event_uuids'            => ['type' => 'array', 'items' => ['type' => 'string']],
                'floor_plan_id'          => ['type' => 'integer', 'description' => 'Ein Tischplan; Kurzform für floor_plan_ids mit einem Element.'],
                'floor_plan_ids'         => [
                    'type'        => 'array',
                    'items'       => ['type' => 'integer'],
                    'description' => 'Mehrere Tischpläne; Reihenfolge bestimmt sort_order, sofern sort_order nicht gesetzt ist.',
                ],
                'fill_threshold_percent' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                'capacity_override'      => ['type' => 'integer', 'minimum' => 1],
                'sort_order'             => ['type' => 'integer', 'description' => 'Gilt für alle; sonst 0,1,2… nach Reihenfolge.'],
                'replace'                => ['type' => 'boolean', 'description' => 'true = vorhandene Raumzuordnungen des Termins ersetzen.'],
                'force'                  => ['type' => 'boolean', 'description' => 'true = auch Räume mit Buchungen entfernen.'],
            ],
            'required'   => ['event_uuids'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $context->team?->id;

            if (!$teamId) {
                return ToolResult::error('Kein Team-Kontext vorhanden.', 'MISSING_TEAM');
            }

            $uuids = $arguments['event_uuids'] ?? [];
            if (!is_array($uuids) || $uuids === []) {
                return ToolResult::error('Parameter "event_uuids" muss ein nicht-leeres Array sein.', 'VALIDATION_ERROR');
            }

            $replace = (bool) ($arguments['replace'] ?? false);
            $force   = (bool) ($arguments['force'] ?? false);

            // floor_plan_id ist die Kurzform von floor_plan_ids mit einem Element.
            $planIds = array_key_exists('floor_plan_ids', $arguments)
                ? array_values(array_unique(array_map('intval', (array) $arguments['floor_plan_ids'])))
                : (isset($arguments['floor_plan_id']) ? [(int) $arguments['floor_plan_id']] : []);

            // Leere Liste ist nur beim Ersetzen sinnvoll (= alle Räume entfernen).
            if ($planIds === [] && !$replace) {
                return ToolResult::error(
                    'floor_plan_id oder floor_plan_ids angeben (leer ist nur mit replace=true erlaubt).',
                    'VALIDATION_ERROR',
                );
            }

            // Alle Pläne müssen zum Team gehören – sonst würde man fremde Räume zuweisen.
            $ownPlanIds = FloorPlan::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->whereIn('id', $planIds)
                ->pluck('id')
                ->all();

            if (count($ownPlanIds) !== count($planIds)) {
                return ToolResult::error(
                    'Mindestens ein Tischplan wurde nicht gefunden oder gehört nicht zum Team.',
                    'FLOOR_PLAN_NOT_FOUND',
                );
            }

            $sortGiven = array_key_exists('sort_order', $arguments);
            $baseAttributes = [
                'fill_threshold_percent' => (int) ($arguments['fill_threshold_percent'] ?? 100),
                'capacity_override'      => $arguments['capacity_override'] ?? null,
            ];

            $assigned = 0;
            $removed  = 0;
            $kept     = [];   // wegen Buchungen nicht entfernte Räume
            $notFound = [];

            foreach ($uuids as $uuid) {
                $event = Event::withoutGlobalScope('team')
                    ->where('team_id', $teamId)
                    ->where('uuid', (string) $uuid)
                    ->first();

                if (!$event) {
                    $notFound[] = (string) $uuid;
                    continue;
                }

                foreach ($planIds as $i => $planId) {
                    $attributes = $baseAttributes + [
                        'sort_order' => $sortGiven ? (int) $arguments['sort_order'] : $i,
                    ];

                    // Vorhandene Zuordnung aktualisieren statt zu überspringen –
                    // sonst blieben beim erneuten Aufruf alte Schwellwerte stehen.
                    $room = $event->eventRooms()->firstOrNew(['floor_plan_id' => $planId]);
                    $room->fill($attributes)->save();
                    $assigned++;
                }

                if (!$replace) {
                    continue;
                }

                $obsolete = $event->eventRooms()
                    ->whereNotIn('floor_plan_id', $planIds ?: [0])
                    ->get();

                foreach ($obsolete as $room) {
                    // Buchungen auf Tischen dieses Plans? Dann stehenlassen, sonst
                    // zeigten sie auf einen Raum, der dem Termin nicht mehr zugewiesen ist.
                    if (!$force && $this->hasBookings($event, $room->floor_plan_id)) {
                        $kept[] = [
                            'event_uuid'    => $event->uuid,
                            'floor_plan_id' => $room->floor_plan_id,
                            'reason'        => 'bookings_exist',
                        ];
                        continue;
                    }

                    $room->delete();
                    $removed++;
                }
            }

            return ToolResult::success([
                'assigned_count'  => $assigned,
                'removed_count'   => $removed,
                'kept_count'      => count($kept),
                'kept'            => $kept,
                'not_found_count' => count($notFound),
                'not_found'       => $notFound,
            ], ['updated' => $assigned + $removed]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Zuweisen der Räume: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    /**
     * Hat der Termin Buchungen auf Tischen dieses Tischplans? Buchungen hängen
     * an table_id, nicht an der Raum-Zuordnung – ohne diese Prüfung würde ein
     * Ersetzen die Zuordnung entfernen und die Buchungen ins Leere zeigen lassen.
     */
    protected function hasBookings(Event $event, int $floorPlanId): bool
    {
        return Booking::withoutGlobalScope('team')
            ->where('event_id', $event->id)
            ->whereHas('table', fn ($q) => $q->withoutGlobalScope('team')->where('floor_plan_id', $floorPlanId))
            ->exists();
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'events', 'rooms', 'bulk', 'replace'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['creates'],
            'risk_level'    => 'write',
        ];
    }
}
