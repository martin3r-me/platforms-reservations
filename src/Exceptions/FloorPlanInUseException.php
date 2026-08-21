<?php

namespace Platform\Reservation\Exceptions;

use RuntimeException;

/**
 * Ein Raum, der in einem anstehenden Termin eingeplant ist, wird gelöscht.
 *
 * Absichtlich eine Ausnahme und keine stille Rückgabe: Am Löschen hängen
 * Datenbank-Kaskaden (die Raumzuordnung des Termins verschwindet, die Tische
 * mit ihr, und die Buchungen verlieren ihren Tischbezug). Wer den Weg nicht
 * kennt – ein MCP-Tool, ein Skript, eine spätere Oberfläche –, soll nicht
 * versehentlich daran vorbeikommen.
 */
class FloorPlanInUseException extends RuntimeException
{
}
