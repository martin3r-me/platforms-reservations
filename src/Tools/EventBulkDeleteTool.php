<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\Event;

/**
 * Löscht mehrere Termine auf einmal.
 *
 * Termine mit Buchungen werden übersprungen, solange der Aufrufer sie nicht
 * ausdrücklich freigibt: Das Löschen nimmt der Buchung ihren Termin, nicht
 * ihr Dasein - übrig bliebe ein Beleg ohne Veranstaltung. Wer aufräumt, meint
 * fast immer die leeren Termine; die anderen soll er bewusst benennen.
 */
class EventBulkDeleteTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.events.bulk.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /reservation/events/bulk - Löscht mehrere Termine. REST-Parameter: '
            . 'event_uuids (Array, Pflicht), include_with_bookings (bool, Default false). '
            . 'Termine MIT Buchungen werden standardmäßig übersprungen und als skipped_with_bookings '
            . 'gemeldet - beim Löschen behalten ihre Buchungen zwar Positionen und Zahlung, '
            . 'verlieren aber den Termin-Bezug. include_with_bookings=true löscht sie mit. '
            . 'Der Stapel läuft weiter, wenn einzelne UUIDs unbekannt sind (not_found).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'event_uuids'           => ['type' => 'array', 'items' => ['type' => 'string']],
                'include_with_bookings' => ['type' => 'boolean', 'description' => 'true = auch Termine mit Buchungen löschen; deren Buchungen verlieren den Termin-Bezug.'],
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

            $mitBuchungen = (bool) ($arguments['include_with_bookings'] ?? false);

            $geloescht     = [];
            $mitBuchung    = [];
            $nichtGefunden = [];

            foreach ($uuids as $uuid) {
                $uuid = (string) $uuid;

                $event = Event::withoutGlobalScope('team')
                    ->where('team_id', $teamId)
                    ->where('uuid', $uuid)
                    ->withCount('bookings')
                    ->first();

                if (!$event) {
                    $nichtGefunden[] = $uuid;
                    continue;
                }

                // Überspringen statt abbrechen: Ein Stapel von fünfzig Terminen
                // soll nicht an dem einen scheitern, der noch Buchungen hat.
                if (!$mitBuchungen && $event->bookings_count > 0) {
                    $mitBuchung[] = ['uuid' => $uuid, 'name' => $event->name, 'bookings_count' => $event->bookings_count];
                    continue;
                }

                $event->delete();
                $geloescht[] = $uuid;
            }

            return ToolResult::success([
                'deleted_count'               => count($geloescht),
                'deleted'                     => $geloescht,
                'skipped_with_bookings_count' => count($mitBuchung),
                'skipped_with_bookings'       => $mitBuchung,
                'not_found_count'             => count($nichtGefunden),
                'not_found'                   => $nichtGefunden,
            ], ['deleted' => count($geloescht)]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Löschen der Termine: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'              => 'action',
            'tags'                  => ['reservation', 'events', 'delete', 'bulk'],
            'requires_team'         => true,
            'read_only'             => false,
            'side_effects'          => ['deletes'],
            'risk_level'            => 'destructive',
            'confirmation_required' => true,
        ];
    }
}
