<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\Table;

/**
 * Löscht einen Tisch des aktiven Teams.
 */
class TableDeleteTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.tables.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /reservation/tables - Löscht einen Tisch. REST-Parameter: id (Pflicht), '
            . 'force (bool, Default false). Haengen aktive Buchungen am Tisch, bricht der Aufruf mit '
            . 'HAS_BOOKINGS ab und nennt die Anzahl; mit force=true wird trotzdem geloescht. Die '
            . 'Buchungen bleiben in jedem Fall erhalten und behalten den eingefrorenen Namen des '
            . 'Tisches - im Saalplan liegt er danach aber nicht mehr.';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'id'    => ['type' => 'integer', 'description' => 'ID des zu löschenden Tisches.'],
                'force' => ['type' => 'boolean', 'description' => 'Auch loeschen, wenn Buchungen daran haengen.'],
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

            $table = Table::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->find((int) ($arguments['id'] ?? 0));

            if (!$table) {
                return ToolResult::error('Tisch nicht gefunden.', 'NOT_FOUND');
            }

            // Erst zaehlen, dann loeschen. Ein geloeschter Tisch nimmt die
            // Buchungen nicht mit, aber er nimmt ihnen ihren Ort im Saalplan -
            // und das ist einmal unbemerkt passiert. Wer es trotzdem will,
            // sagt es ausdruecklich.
            $buchungen = Booking::withoutGlobalScope('team')
                ->where('table_id', $table->id)
                ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW])
                ->count();

            if ($buchungen > 0 && ! (bool) ($arguments['force'] ?? false)) {
                return ToolResult::error(
                    'Am Tisch "' . $table->label . '" haengen ' . $buchungen . ' aktive Buchung(en). '
                    . 'Sie bleiben erhalten und behalten den Namen des Tisches, verlieren aber ihren Platz '
                    . 'im Saalplan. Mit force=true trotzdem loeschen.',
                    'HAS_BOOKINGS'
                );
            }

            $table->delete();

            return ToolResult::success([
                'deleted'  => true,
                'id'       => (int) $arguments['id'],
                'bookings' => $buchungen,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Löschen des Tisches: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'             => 'action',
            'tags'                 => ['reservation', 'tables', 'delete'],
            'requires_team'        => true,
            'read_only'            => false,
            'side_effects'         => ['deletes'],
            'risk_level'           => 'destructive',
            'confirmation_required'=> true,
        ];
    }
}
