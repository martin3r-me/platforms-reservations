<?php

namespace Platform\Reservation\Tools;

use Illuminate\Support\Facades\Validator;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Models\SalesList;
use Platform\Reservation\Models\Venue;

/**
 * Setzt gemeinsame Felder für mehrere Termine auf einmal.
 *
 * Bewusst NUR Felder, bei denen ein gemeinsamer Wert fachlich sinnvoll ist:
 * name und date fehlen absichtlich – dieselbe Bezeichnung oder dasselbe Datum
 * für 46 Termine wäre nie gewollt und würde nur Daten zerstören.
 * Status läuft weiterhin über events.publish.bulk.POST.
 */
class EventBulkUpdateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.events.bulk.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /reservation/events/bulk - Setzt gemeinsame Felder mehrerer Termine. '
            . 'REST-Parameter: event_uuids (Array, Pflicht); sales_list_id, venue_id, room_release_mode, max_guest_count, table_binding '
            . '(parallel|sequential), order_deadline_at, description – mindestens eines davon. '
            . 'name und date gibt es hier absichtlich nicht (für viele Termine nie derselbe Wert); '
            . 'Status über events.publish.bulk.POST. sales_list_id/venue_id dürfen null sein (Zuordnung lösen).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'event_uuids'       => ['type' => 'array', 'items' => ['type' => 'string']],
                'sales_list_id'     => ['type' => ['integer', 'null']],
                'venue_id'          => ['type' => ['integer', 'null']],
                'room_release_mode' => ['type' => 'string', 'enum' => ['parallel', 'sequential']],
                'max_guest_count'   => ['type' => ['integer', 'null'], 'description' => 'Groesste buchbare Gruppe; null = Vorgabe des Teams.'],
                'table_binding'     => ['type' => ['string', 'null'], 'enum' => ['event', 'slot', null], 'description' => 'Wem gehoert ein Tisch bei mehreren Pausen: event = dem Gast den ganzen Abend (eine Tischwahl fuer alle Pausen), slot = jede Pause wird einzeln vergeben. Bei Terminen mit nur EINER Pause ohne Wirkung - beide rechnen dann gleich. null = Vorgabe des Teams.'],
                'order_deadline_at' => ['type' => 'string', 'description' => 'Datum/Zeit. Aufheben ist nicht möglich – ohne Frist bliebe der Termin ewig bestellbar.'],
                'description'       => ['type' => ['string', 'null']],
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

            $validator = Validator::make($arguments, [
                'event_uuids'       => 'required|array|min:1',
                'event_uuids.*'     => 'string',
                'sales_list_id'     => 'sometimes|nullable|integer',
                'venue_id'          => 'sometimes|nullable|integer',
                'room_release_mode' => 'sometimes|in:parallel,sequential',
                'max_guest_count'   => 'sometimes|nullable|integer|min:1|max:200',
                'table_binding'     => 'sometimes|nullable|in:event,slot',
                'order_deadline_at' => 'sometimes|required|date',
                'description'       => 'sometimes|nullable|string',
            ]);

            if ($validator->fails()) {
                return ToolResult::error($validator->errors()->first(), 'VALIDATION_ERROR');
            }

            $data = collect($validator->validated())
                ->only(['sales_list_id', 'venue_id', 'room_release_mode', 'max_guest_count', 'table_binding', 'order_deadline_at', 'description'])
                ->all();

            if ($data === []) {
                return ToolResult::error('Mindestens ein zu setzendes Feld angeben.', 'VALIDATION_ERROR');
            }

            // Fremde Verkaufsliste/Venue würde sonst quer durch die Mandanten zeigen.
            if (!empty($data['sales_list_id'])
                && !SalesList::withoutGlobalScope('team')->where('team_id', $teamId)->whereKey($data['sales_list_id'])->exists()
            ) {
                return ToolResult::error('Verkaufsliste nicht gefunden (oder gehört nicht zum Team).', 'SALES_LIST_NOT_FOUND');
            }

            if (!empty($data['venue_id'])
                && !Venue::withoutGlobalScope('team')->where('team_id', $teamId)->whereKey($data['venue_id'])->exists()
            ) {
                return ToolResult::error('Venue nicht gefunden (oder gehört nicht zum Team).', 'VENUE_NOT_FOUND');
            }

            $updated  = 0;
            $notFound = [];

            foreach ($validator->validated()['event_uuids'] as $uuid) {
                $event = Event::withoutGlobalScope('team')
                    ->where('team_id', $teamId)
                    ->where('uuid', (string) $uuid)
                    ->first();

                if (!$event) {
                    $notFound[] = (string) $uuid;
                    continue;
                }

                $event->update($data);
                $updated++;
            }

            return ToolResult::success([
                'updated_count'   => $updated,
                'fields'          => array_keys($data),
                'not_found_count' => count($notFound),
                'not_found'       => $notFound,
            ], ['updated' => $updated]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Aktualisieren der Termine: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'events', 'bulk', 'update'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['updates'],
            'risk_level'    => 'write',
        ];
    }
}
