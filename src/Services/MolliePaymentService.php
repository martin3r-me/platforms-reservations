<?php

namespace Platform\Reservation\Services;

use Illuminate\Support\Carbon;
use Mollie\Api\MollieApiClient;
use Platform\Reservation\Contracts\MollieCredentialResolver;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\Order;
use Platform\Reservation\Models\Payment;
use Platform\Reservation\Services\OrderConfirmationMailer;
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
        // Ohne installiertes SDK gilt Mollie als nicht aktiv – der Checkout
        // fällt dann sauber auf den Mock/Bestätigungs-Flow zurück (kein Dead-End).
        return class_exists(\Mollie\Api\MollieApiClient::class)
            && $this->resolver->forTeam($teamId) !== null;
    }

    /**
     * Mollie-Zahlung für eine Order (eine oder mehrere Slot-Buchungen) anlegen
     * und Checkout-URL zurückgeben. Betrag = Summe aller Buchungen der Order.
     */
    public function createForOrder(Order $order, ?string $redirectUrl = null): string
    {
        $creds = $this->resolver->forTeam($order->team_id);

        if (!$creds) {
            throw new \RuntimeException('Keine Mollie-Zugangsdaten für dieses Team hinterlegt.');
        }

        $client   = $this->client($creds);
        $currency = strtoupper((string) config('reservation.currency', 'EUR'));
        $value    = number_format((float) $order->total_amount, 2, '.', '');

        $molliePayment = $client->payments->create([
            'amount'      => ['currency' => $currency, 'value' => $value],
            'description' => 'PausePlus Bestellung ' . $order->uuid,
            // Rücksprung: vom Frontend übergebene (validierte) URL, sonst In-App-Return.
            'redirectUrl' => $redirectUrl ?: route('reservation.guest.payment.return', $order->uuid),
            'webhookUrl'  => route('reservation.api.payment.webhook'),
            'metadata'    => [
                'order_id'   => $order->id,
                'order_uuid' => $order->uuid,
            ],
        ]);

        Payment::updateOrCreate(
            ['order_id' => $order->id],
            [
                'mollie_id' => $molliePayment->id,
                'amount'    => $order->total_amount,
                'currency'  => $currency,
                'status'    => $molliePayment->status,
                'metadata'  => ['mode' => $creds->mode],
            ],
        );

        // Referenz auf die Buchungen spiegeln (Export/Anzeige nutzen es weiter).
        // withoutGlobalScope, damit es auch im authentifizierten API-Kontext greift.
        $order->bookings()->withoutGlobalScope('team')->update(['mollie_payment_id' => $molliePayment->id]);

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
        $order   = $payment?->order;

        if (!$payment || !$order) {
            return;
        }

        $creds = $this->resolver->forTeam($order->team_id);
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
        //   paid                        → alle Buchungen der Order bestätigt (+ Mail)
        //   failed | canceled | expired → storniert (gibt Plätze wieder frei)
        //   open | pending | authorized → bleibt pending (Return-Seite pollt weiter)
        $isFailure = $molliePayment->isFailed()
            || $molliePayment->isCanceled()
            || $molliePayment->isExpired();

        if ($molliePayment->isPaid()) {
            if ($order->status === Order::STATUS_PENDING) {
                $order->update(['status' => Order::STATUS_CONFIRMED]);

                foreach ($order->bookings as $booking) {
                    if ($booking->status !== Booking::STATUS_PENDING) {
                        continue;
                    }

                    $booking->update([
                        'status'         => Booking::STATUS_CONFIRMED,
                        // Von Mollie gemeldete echte Zahlungsart übernehmen (z.B. ideal, creditcard, paypal).
                        'payment_method' => $molliePayment->method ?: $booking->payment_method,
                    ]);
                }

                // EINE Bestellbestätigung je Order (über CRM-Comms; inert ohne Channel).
                OrderConfirmationMailer::send($order);
            }
        } elseif ($isFailure) {
            if ($order->status === Order::STATUS_PENDING) {
                $order->update(['status' => Order::STATUS_CANCELLED]);

                foreach ($order->bookings as $booking) {
                    if ($booking->status === Booking::STATUS_PENDING) {
                        $booking->update(['status' => Booking::STATUS_CANCELLED]);
                    }
                }
            }
        }
    }

    /**
     * Löst die (volle) Rückerstattung einer Bestellung bei Mollie aus und
     * vermerkt sie an der Payment. Defensiv: ohne SDK/Zahlung/Bezahlung inert.
     *
     * @return array{status:string,message:string}
     */
    public function refundOrder(Order $order): array
    {
        if (!class_exists(\Mollie\Api\MollieApiClient::class)) {
            return ['status' => 'no_sdk', 'message' => 'Mollie-SDK nicht installiert.'];
        }

        $payment = $order->payment()->first();

        if (!$payment || !$payment->mollie_id) {
            return ['status' => 'no_payment', 'message' => 'Keine Mollie-Zahlung zur Bestellung.'];
        }
        if ($payment->refunded_at) {
            return ['status' => 'already_refunded', 'message' => 'Bereits erstattet.'];
        }
        if ($payment->status !== 'paid') {
            return ['status' => 'not_paid', 'message' => 'Zahlung nicht bezahlt – keine Erstattung nötig.'];
        }

        $creds = $this->resolver->forTeam($order->team_id);
        if (!$creds) {
            return ['status' => 'no_credentials', 'message' => 'Keine Mollie-Zugangsdaten.'];
        }

        try {
            $molliePayment = $this->client($creds)->payments->get($payment->mollie_id);

            if (method_exists($molliePayment, 'canBeRefunded') && !$molliePayment->canBeRefunded()) {
                return ['status' => 'not_refundable', 'message' => 'Zahlung ist bei Mollie nicht erstattbar.'];
            }

            $currency = strtoupper((string) ($payment->currency ?: config('reservation.currency', 'EUR')));
            $value    = number_format((float) $payment->amount, 2, '.', '');

            $molliePayment->refund([
                'amount'      => ['currency' => $currency, 'value' => $value],
                'description' => 'Storno PausePlus Bestellung ' . $order->uuid,
            ]);

            $payment->update([
                'status'          => 'refunded',
                'refunded_at'     => now(),
                'refunded_amount' => $payment->amount,
            ]);

            return ['status' => 'refunded', 'message' => 'Rückerstattung ausgelöst: ' . $value . ' ' . $currency];
        } catch (\Throwable $e) {
            \Log::warning('[Reservation\\MolliePaymentService] Refund fehlgeschlagen', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);

            return ['status' => 'failed', 'message' => 'Rückerstattung fehlgeschlagen: ' . $e->getMessage()];
        }
    }

    protected function client(MollieCredentials $creds): MollieApiClient
    {
        $client = new MollieApiClient();
        $client->setApiKey($creds->apiKey);

        return $client;
    }
}
