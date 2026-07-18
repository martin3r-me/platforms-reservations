<?php

namespace Platform\Reservation\Exceptions;

use RuntimeException;

/**
 * Fachlicher Fehler bei der Gast-Bestellung (führt zu HTTP 422 in der Gast-API).
 */
class GuestOrderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'INVALID',
    ) {
        parent::__construct($message);
    }
}
