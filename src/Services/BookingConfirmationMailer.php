<?php

namespace Platform\Reservation\Services;

use Platform\Reservation\Models\Booking;

/**
 * Buchungsbestätigung an den Gast – versendet über den CRM-Comms-Dienst
 * (PostmarkEmailService), wie es auch das Events-Modul macht.
 *
 * Loose Coupling: das CRM-/Comms-Modul wird nur defensiv über class_exists
 * referenziert, damit PausePlus ohne CRM lauffähig bleibt. Ist kein
 * Email-Channel hinterlegt, wird NICHT versendet (Status zurückgegeben,
 * niemals Exception nach außen) – die Methode ist also „vorbereitet" und
 * bleibt inert, bis im CRM ein aktiver Postmark-Email-Channel existiert.
 *
 * @return array{status:string,message:string}
 */
class BookingConfirmationMailer
{
    public static function send(Booking $booking): array
    {
        $to = trim((string) $booking->guest_email);

        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 'invalid_recipient', 'message' => 'Keine gültige Gast-E-Mail hinterlegt.'];
        }

        // CRM/Comms optional – ohne das Modul wird nicht versendet.
        if (!class_exists(\Platform\Crm\Models\CommsChannel::class)
            || !class_exists(\Platform\Crm\Services\Comms\PostmarkEmailService::class)
        ) {
            return ['status' => 'no_comms_module', 'message' => 'CRM/Comms-Modul nicht installiert – keine Mail versendet.'];
        }

        $teamId = (int) $booking->team_id;

        // Aktiven Postmark-Email-Channel des Teams ermitteln.
        $channel = \Platform\Crm\Models\CommsChannel::query()
            ->where('team_id', $teamId)
            ->where('type', 'email')
            ->where('provider', 'postmark')
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if (!$channel) {
            return ['status' => 'no_channel', 'message' => 'Kein aktiver Email-Channel im CRM gefunden.'];
        }

        $booking->loadMissing(['event', 'slot', 'table', 'items.menuItem']);

        $subject  = 'Ihre Bestellung – ' . ($booking->event?->name ?? 'PausePlus');
        $htmlBody = view('reservation::emails.booking-confirmation', [
            'booking' => $booking,
        ])->render();

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
                    'context_model'    => Booking::class,
                    'context_model_id' => $booking->id,
                ],
            );

            return ['status' => 'sent', 'message' => 'Bestätigung an ' . $to . ' versendet.'];
        } catch (\Throwable $e) {
            \Log::warning('[Reservation\\BookingConfirmationMailer] Versand fehlgeschlagen', [
                'booking_id' => $booking->id,
                'to'         => $to,
                'error'      => $e->getMessage(),
            ]);

            return ['status' => 'failed', 'message' => 'Versand fehlgeschlagen: ' . $e->getMessage()];
        }
    }
}
