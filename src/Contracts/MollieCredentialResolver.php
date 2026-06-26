<?php

namespace Platform\Reservation\Contracts;

use Platform\Reservation\Support\MollieCredentials;

/**
 * Seam für die Herkunft der Mollie-Zugangsdaten eines Teams.
 *
 * Standard-Implementierung liest die verschlüsselte Modul-Einstellung
 * (mit ENV-Fallback). Später ohne Umbau gegen eine platforms-integrations-
 * Connection austauschbar – einfach das Binding im ServiceProvider ändern.
 */
interface MollieCredentialResolver
{
    /** Gibt die Zugangsdaten zurück oder null, wenn für das Team nichts Einsatzbereites hinterlegt ist. */
    public function forTeam(int $teamId): ?MollieCredentials;
}
