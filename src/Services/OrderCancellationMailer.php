<?php

namespace Platform\Reservation\Services;

use Platform\Reservation\Models\Order;

/**
 * Stornobestätigung an den Gast – EINE Mail je Bestellung.
 *
 * Bis zum 04.09.2026 gab es sie nicht: Wurde eine Bestellung storniert oder
 * ein ganzer Termin abgesagt, erfuhr der Gast davon nichts. Im besten Fall
 * merkte er es am Kontoauszug, im schlechteren stand er vor der Halle.
 *
 * Der Grund steht in der Mail, wenn einer mitkommt („Die Veranstaltung wurde
 * abgesagt"). Ohne Grund bleibt es bei der schlichten Aussage – falsch raten
 * wäre schlimmer als nichts zu sagen.
 *
 * Über die Erstattung wird nur gesprochen, wenn wirklich Geld zurückgeht: Eine
 * Bestellung, die nie bezahlt wurde, bekommt keine Zeile über eine Gutschrift,
 * auf die der Gast dann wartet.
 */
class OrderCancellationMailer
{
    /**
     * @param  string|null  $grund     eine Zeile, warum – oder null
     * @param  bool         $erstattet ob eine Rückerstattung ausgelöst wurde
     * @return array{status:string,message:string}
     */
    public static function send(Order $order, ?string $grund = null, bool $erstattet = false): array
    {
        $order->loadMissing([
            'event',
            'bookings' => fn ($q) => $q->withoutGlobalScope('team')->with(['slot', 'items.menuItem', 'items.bundleMenuItem']),
            'payment',
        ]);

        return GastMail::senden(
            $order,
            'Ihre Bestellung wurde storniert – ' . ($order->event?->name ?? 'PausePlus'),
            'reservation::emails.order-cancellation',
            [
                'grund'     => $grund,
                'erstattet' => $erstattet,
            ],
        );
    }
}
