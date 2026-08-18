<?php

namespace Platform\Reservation\Support;

use Illuminate\Support\Collection;

/**
 * Zugang zum Druck-Modul.
 *
 * Das Modul platforms-printing ist optional: Es kann fehlen, und dann darf hier
 * nichts knallen. Diese Prüfung stand an drei Stellen wortgleich – Buchungsliste,
 * Einstellungen, automatischer Druck. Jetzt an einer.
 *
 * Gedruckt wird ausschließlich über PrintingServiceInterface::createJob(); eine
 * eigene Druckstrecke gibt es hier nicht, und die Bon-Vorlage liegt einmal unter
 * resources/views/printing/reservation-booking.blade.php.
 */
class PrintingBridge
{
    private const INTERFACE = 'Platform\\Printing\\Contracts\\PrintingServiceInterface';

    /** Der Dienst – oder null, wenn das Druck-Modul nicht installiert ist. */
    public static function service()
    {
        return (interface_exists(self::INTERFACE) && app()->bound(self::INTERFACE))
            ? app(self::INTERFACE)
            : null;
    }

    public static function available(): bool
    {
        return self::service() !== null;
    }

    public static function printers(): Collection
    {
        return self::service()?->listPrinters() ?? collect();
    }

    public static function printerGroups(): Collection
    {
        return self::service()?->listPrinterGroups() ?? collect();
    }
}
