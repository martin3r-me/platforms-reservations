<?php

namespace Platform\Reservation\Http\Controllers\Api;

use Platform\Reservation\Models\Event;

/**
 * Basis für die token-gesicherte Gast-API. Das Team kommt fest aus der
 * Office-Config (RESERVATION_GUEST_TEAM_ID), NICHT aus dem Token – jede
 * Instanz bedient genau ein Team. Alle Queries laufen bewusst
 * withoutGlobalScope('team') + explizites Team.
 */
abstract class GuestApiController
{
    protected function guestTeamId(): int
    {
        $teamId = (int) config('reservation.guest_api.team_id');

        abort_if($teamId < 1, 503, 'Gast-API ist nicht konfiguriert.');

        return $teamId;
    }

    /** Veröffentlichten Termin des Gast-Teams per UUID laden (oder null). */
    protected function findEvent(string $uuid, array $with = []): ?Event
    {
        return Event::withoutGlobalScope('team')
            ->where('team_id', $this->guestTeamId())
            ->where('uuid', $uuid)
            ->where('status', Event::STATUS_PUBLISHED)
            ->with($with)
            ->first();
    }
}
