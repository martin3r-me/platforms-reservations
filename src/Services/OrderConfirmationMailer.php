<?php

namespace Platform\Reservation\Services;

use Platform\Reservation\Models\Order;

/**
 * Bestellbestätigung an den Kunden – EINE Mail je Order (nicht je Buchung),
 * versendet über den CRM-Comms-Dienst (PostmarkEmailService), wie es auch das
 * Events-/Reservations-Modul sonst macht.
 *
 * Loose Coupling: das CRM-/Comms-Modul wird nur defensiv über class_exists
 * referenziert, damit PausePlus ohne CRM lauffähig bleibt. Ohne aktiven
 * Postmark-Email-Channel wird NICHT versendet (Status zurückgegeben, nie
 * Exception nach außen).
 *
 * @return array{status:string,message:string}
 */
class OrderConfirmationMailer
{
    public static function send(Order $order): array
    {
        $order->loadMissing([
            'event',
            'bookings' => fn ($q) => $q->withoutGlobalScope('team')->with(['slot', 'table', 'items.menuItem']),
            'payment',
        ]);

        // Empfänger: Kundendaten der Order, sonst erste Buchung.
        $to = trim((string) ($order->email ?: $order->bookings->first()?->guest_email));

        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 'invalid_recipient', 'message' => 'Keine gültige Kunden-E-Mail hinterlegt.'];
        }

        // CRM/Comms optional – ohne das Modul wird nicht versendet.
        if (!class_exists(\Platform\Crm\Models\CommsChannel::class)
            || !class_exists(\Platform\Crm\Services\Comms\PostmarkEmailService::class)
        ) {
            return ['status' => 'no_comms_module', 'message' => 'CRM/Comms-Modul nicht installiert – keine Mail versendet.'];
        }

        // Absender wird explizit konfiguriert (kein Default/Fallback).
        $channelId = \Platform\Reservation\Models\CheckoutSetting::forTeam((int) $order->team_id)->confirmationChannelId();

        if (!$channelId) {
            return ['status' => 'no_channel_configured', 'message' => 'Kein Absender für Bestellbestätigungen konfiguriert – keine Mail versendet.'];
        }

        // Nur den gewählten Channel nehmen – und nur, wenn er zum Team passt und aktiv ist.
        $channel = \Platform\Crm\Models\CommsChannel::query()
            ->where('id', $channelId)
            ->where('team_id', (int) $order->team_id)
            ->where('type', 'email')
            ->where('provider', 'postmark')
            ->where('is_active', true)
            ->first();

        if (!$channel) {
            return ['status' => 'channel_invalid', 'message' => 'Konfigurierter Absender ist nicht (mehr) gültig – keine Mail versendet.'];
        }

        $subject  = 'Vielen Dank für Ihre Bestellung – ' . ($order->event?->name ?? 'PausePlus');
        $htmlBody = view('reservation::emails.order-confirmation', ['order' => $order])->render();

        try {
            /** @var \Platform\Crm\Services\Comms\PostmarkEmailService $svc */
            $svc = app(\Platform\Crm\Services\Comms\PostmarkEmailService::class);
            $svc->send(
                $channel,
                $to,
                $subject,
                $htmlBody,
                null,
                [],
                [
                    'context_model'    => Order::class,
                    'context_model_id' => $order->id,
                ],
            );

            return ['status' => 'sent', 'message' => 'Bestätigung an ' . $to . ' versendet.'];
        } catch (\Throwable $e) {
            \Log::warning('[Reservation\\OrderConfirmationMailer] Versand fehlgeschlagen', [
                'order_id' => $order->id,
                'to'       => $to,
                'error'    => $e->getMessage(),
            ]);

            return ['status' => 'failed', 'message' => 'Versand fehlgeschlagen: ' . $e->getMessage()];
        }
    }
}
