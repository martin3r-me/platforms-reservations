<?php

namespace Platform\Reservation\Services;

use Platform\Reservation\Models\Order;

/**
 * Bestellbestätigung an den Kunden – EINE Mail je Order (nicht je Buchung),
 * versendet über den CRM-Comms-Dienst (PostmarkEmailService), wie es auch das
 * Events-/Reservations-Modul sonst macht.
 *
 * Baut nur die Links und den Betreff – Empfänger, Absender und Versand
 * liegen in {@see GastMail}, weil die Storno-Mail dasselbe braucht.
 *
 * @return array{status:string,message:string}
 */
class OrderConfirmationMailer
{
    public static function send(Order $order): array
    {
        $order->loadMissing([
            'event',
            'bookings' => fn ($q) => $q->withoutGlobalScope('team')->with(['slot', 'table', 'pickupStation', 'items.menuItem', 'items.bundleMenuItem']),
            'payment',
        ]);

        $settings = \Platform\Reservation\Models\CheckoutSetting::forTeam((int) $order->team_id);

        // Signierter Storno-Link, wenn Selbst-Storno aktiviert ist.
        $cancelUrl = $settings->cancellationEnabled()
            ? \Illuminate\Support\Facades\URL::signedRoute('reservation.guest.order.cancel', ['uuid' => $order->uuid])
            : null;

        // Signierte Beleg-Links (PDF) – Bestellbestätigung & Bewirtungsbeleg.
        $receiptUrl = \Illuminate\Support\Facades\URL::signedRoute(
            'reservation.guest.order.receipt', ['uuid' => $order->uuid, 'type' => 'confirmation'],
        );
        // Bewirtungsbeleg nur bei vorhandenen Unternehmensdaten (Firma).
        $bewirtungUrl = $order->hasBusinessData()
            ? \Illuminate\Support\Facades\URL::signedRoute('reservation.guest.order.receipt', ['uuid' => $order->uuid, 'type' => 'bewirtungsbeleg'])
            : null;

        return GastMail::senden(
            $order,
            'Vielen Dank für Ihre Bestellung – ' . ($order->event?->name ?? 'PausePlus'),
            'reservation::emails.order-confirmation',
            [
                'cancelUrl'    => $cancelUrl,
                'receiptUrl'   => $receiptUrl,
                'bewirtungUrl' => $bewirtungUrl,
            ],
        );
    }
}
