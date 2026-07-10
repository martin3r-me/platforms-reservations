<?php

namespace Platform\Reservation\Services;

use Illuminate\Support\Carbon;
use Mollie\Api\MollieApiClient;
use Platform\Reservation\Contracts\MollieCredentialResolver;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\Payment;
use Platform\Reservation\Services\BookingConfirmationMailer;
use Platform\Reservation\Support\MollieCredentials;

/**
 * Mollie-Zahlungen für Buchungen (Hosted Redirect).
 *
 * Anlegen: Zahlung bei Mollie erstellen, lokalen Payment-Datensatz + Booking
 * verknüpfen, Checkout-URL zurückgeben. Status kommt asynchron per Webhook
 * zurück (syncFromMollie) – die Buchung wird erst nach Zahlungseingang
 * bestätigt. Bleibt inert, solange für das Team kein Key hinterlegt ist.
 */
class MolliePaymentService
{
    public function __construct(
        protected MollieCredentialResolver $resolver,
    ) {
    }

    public function isEnabledForTeam(int $teamId): bool
    {
        return $this->resolver->forTeam($teamId) !== null;
    }

    /**
     * Mollie-Zahlung für eine Buchung anlegen und Checkout-URL zurückgeben.
     */
    public function createForBooking(Booking $booking): string
    {
        $creds = $this->resolver->forTeam($booking->team_id);

        if (!$creds) {
            throw new \RuntimeException('Keine Mollie-Zugangsdaten für dieses Team hinterlegt.');
        }

        $client   = $this->client($creds);
        $currency = strtoupper((string) config('reservation.currency', 'EUR'));
        $value    = number_format((float) $booking->total_amount, 2, '.', '');

        $molliePayment = $client->payments->create([
            'amount'      => ['currency' => $currency, 'value' => $value],
            'description' => 'PausePlus Bestellung ' . $booking->uuid,
            'redirectUrl' => route('reservation.guest.payment.return', $booking->uuid),
            'webhookUrl'  => route('reservation.api.payment.webhook'),
            'metadata'    => [
                'booking_id'   => $booking->id,
                'booking_uuid' => $booking->uuid,
            ],
        ]);

        Payment::updateOrCreate(
            ['booking_id' => $booking->id],
            [
                'mollie_id' => $molliePayment->id,
                'amount'    => $booking->total_amount,
                'currency'  => $currency,
                'status'    => $molliePayment->status,
                'metadata'  => ['mode' => $creds->mode],
            ],
        );

        $booking->update(['mollie_payment_id' => $molliePayment->id]);

        return $molliePayment->getCheckoutUrl();
    }

    /**
     * Status einer Mollie-Zahlung abgleichen (vom Webhook aufgerufen).
     * Bei Zahlungseingang wird die Buchung bestätigt; bei Fehlschlag/Ablauf
     * storniert (gibt Plätze wieder frei).
     */
    public function syncFromMollie(string $molliePaymentId): void
    {
        $payment = Payment::where('mollie_id', $molliePaymentId)->first();
        $booking = $payment?->booking;

        if (!$payment || !$booking) {
            return;
        }

        $creds = $this->resolver->forTeam($booking->team_id);
        if (!$creds) {
            return;
        }

        $molliePayment = $this->client($creds)->payments->get($molliePaymentId);

        $payment->update([
            'status'  => $molliePayment->status,
            'method'  => $molliePayment->method,
            'paid_at' => $molliePayment->paidAt ? Carbon::parse($molliePayment->paidAt) : null,
        ]);

        // Vollständige Status-Behandlung (idempotent – nur aus pending heraus):
        //   paid                        → bestätigt (+ Mail)
        //   failed | canceled | expired → storniert (gibt Plätze wieder frei)
        //   open | pending | authorized → bleibt pending (Return-Seite pollt weiter)
        $isFailure = $molliePayment->isFailed()
            || $molliePayment->isCanceled()
            || $molliePayment->isExpired();

        if ($molliePayment->isPaid()) {
            if ($booking->status === Booking::STATUS_PENDING) {
                $booking->update([
                    'status'         => Booking::STATUS_CONFIRMED,
                    // Von Mollie gemeldete echte Zahlungsart übernehmen (z.B. ideal, creditcard, paypal).
                    'payment_method' => $molliePayment->method ?: $booking->payment_method,
                ]);

                // Bestätigungsmail an den Gast (über CRM-Comms; inert ohne Channel).
                BookingConfirmationMailer::send($booking);
            }
        } elseif ($isFailure) {
            if ($booking->status === Booking::STATUS_PENDING) {
                $booking->update(['status' => Booking::STATUS_CANCELLED]);
            }
        }
    }

    protected function client(MollieCredentials $creds): MollieApiClient
    {
        $client = new MollieApiClient();
        $client->setApiKey($creds->apiKey);

        return $client;
    }
}
