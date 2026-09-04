<?php

namespace Platform\Reservation\Services;

use Illuminate\Support\Facades\Log;
use Platform\Reservation\Models\CheckoutSetting;
use Platform\Reservation\Models\Order;

/**
 * Der Weg jeder Mail an einen Gast: Empfänger finden, Absender prüfen,
 * Vorlage rendern, über CRM-Comms versenden.
 *
 * Steht hier und nicht in jedem Mailer, weil es inzwischen zwei gibt –
 * Bestätigung und Storno – und sie sich nur in Betreff und Vorlage
 * unterscheiden. Stünde das Drumherum zweimal da, liefe es beim nächsten
 * Wunsch auseinander: Der eine bekäme den geprüften Absender, der andere den
 * von vorgestern.
 *
 * Loose Coupling: Das CRM-/Comms-Modul wird nur defensiv über class_exists
 * referenziert, damit PausePlus ohne CRM lauffähig bleibt. Ohne aktiven
 * Postmark-Kanal wird NICHT versendet – der Status kommt zurück, nie eine
 * Ausnahme nach außen. Eine fehlgeschlagene Mail darf kein Storno umwerfen.
 */
class GastMail
{
    /**
     * @param  string  $ansicht  Blade-Vorlage, bekommt 'order' plus $daten
     * @return array{status:string,message:string}
     */
    public static function senden(Order $order, string $betreff, string $ansicht, array $daten = []): array
    {
        // Empfänger: Kundendaten der Order, sonst erste Buchung.
        $to = trim((string) ($order->email ?: $order->bookings->first()?->guest_email));

        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 'invalid_recipient', 'message' => 'Keine gültige Kunden-E-Mail hinterlegt.'];
        }

        if (! class_exists(\Platform\Crm\Models\CommsChannel::class)
            || ! class_exists(\Platform\Crm\Services\Comms\PostmarkEmailService::class)
        ) {
            return ['status' => 'no_comms_module', 'message' => 'CRM/Comms-Modul nicht installiert – keine Mail versendet.'];
        }

        $settings  = CheckoutSetting::forTeam((int) $order->team_id);
        $channelId = $settings->confirmationChannelId();

        if (! $channelId) {
            return ['status' => 'no_channel_configured', 'message' => 'Kein Absender konfiguriert – keine Mail versendet.'];
        }

        // Nur den gewählten Kanal, und nur wenn er zum Team passt und aktiv ist.
        $channel = \Platform\Crm\Models\CommsChannel::query()
            ->where('id', $channelId)
            ->where('team_id', (int) $order->team_id)
            ->where('type', 'email')
            ->where('provider', 'postmark')
            ->where('is_active', true)
            ->first();

        if (! $channel) {
            return ['status' => 'channel_invalid', 'message' => 'Konfigurierter Absender ist nicht (mehr) gültig – keine Mail versendet.'];
        }

        try {
            /** @var \Platform\Crm\Services\Comms\PostmarkEmailService $svc */
            $svc = app(\Platform\Crm\Services\Comms\PostmarkEmailService::class);
            $svc->send(
                $channel,
                $to,
                $betreff,
                view($ansicht, $daten + ['order' => $order])->render(),
                null,
                [],
                [
                    'context_model'    => Order::class,
                    'context_model_id' => $order->id,
                ],
            );

            return ['status' => 'sent', 'message' => 'Mail an ' . $to . ' versendet.'];
        } catch (\Throwable $e) {
            Log::warning('[Reservation\\GastMail] Versand fehlgeschlagen', [
                'order_id' => $order->id,
                'ansicht'  => $ansicht,
                'to'       => $to,
                'error'    => $e->getMessage(),
            ]);

            return ['status' => 'failed', 'message' => 'Versand fehlgeschlagen: ' . $e->getMessage()];
        }
    }
}
