<?php

namespace Platform\Reservation\Services;

use Illuminate\Support\Facades\Log;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\CheckoutSetting;
use Platform\Reservation\Support\PrintingBridge;

/**
 * Bon automatisch drucken, sobald eine Buchung bestätigt ist.
 *
 * Ausgelöst über den Statuswechsel der Buchung (siehe Booking::booted), nicht
 * an einer einzelnen Stelle im Ablauf. Bestätigt wird nämlich auf mehreren
 * Wegen: nach erfolgreicher Zahlung, von Hand in der Buchungsliste, über die
 * Freigabe im Posteingang. Hinge der Druck an einem davon, fiele er bei den
 * anderen still aus.
 *
 * Der manuelle Druckknopf je Zeile bleibt davon unberührt.
 */
class AutoPrintService
{
    /**
     * Druckt den Bon, wenn das Team es eingeschaltet hat.
     *
     * Wirft nie: Ein klemmender Drucker darf keine Bestellung scheitern lassen.
     * Der Aufruf hängt am Statuswechsel, und der steckt im selben Ablauf wie
     * die Zahlungsbestätigung.
     */
    public function printBooking(Booking $booking): void
    {
        try {
            $service = PrintingBridge::service();

            if (! $service) {
                return;   // Druck-Modul nicht installiert
            }

            $settings = CheckoutSetting::forTeam((int) $booking->team_id);

            if (! $settings->autoPrintReady()) {
                return;
            }

            // Bundle-Namen mitladen: Der Bon zeigt Bundles als einen Posten.
            $booking->loadMissing(['items.menuItem', 'items.bundleMenuItem', 'table.floorPlan', 'event', 'slot']);

            $service->createJob(
                printable: $booking,
                data: ['requested_by' => 'Automatisch'],
                printerId: $settings->auto_print_printer_id ?: null,
                printerGroupId: $settings->auto_print_printer_group_id ?: null,
            );
        } catch (\Throwable $e) {
            // Bewusst nur protokollieren. Ein fehlgeschlagener Druck ist
            // ärgerlich, eine fehlgeschlagene Bestellung wäre schlimmer.
            Log::warning('Automatischer Bon-Druck fehlgeschlagen', [
                'booking_id' => $booking->id,
                'fehler'     => $e->getMessage(),
            ]);
        }
    }
}
