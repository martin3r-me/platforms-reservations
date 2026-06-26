<?php

namespace Platform\Reservation\Support;

/**
 * Aufgelöste Mollie-Zugangsdaten eines Teams (unabhängig von der Quelle:
 * Modul-Einstellung, ENV-Fallback oder später platforms-integrations).
 */
final class MollieCredentials
{
    public function __construct(
        public readonly string $apiKey,
        public readonly string $mode = 'test',
    ) {
    }

    public function isLive(): bool
    {
        return $this->mode === 'live';
    }
}
