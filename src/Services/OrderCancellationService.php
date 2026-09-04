<?php

namespace Platform\Reservation\Services;

use Illuminate\Support\Facades\DB;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\CheckoutSetting;
use Platform\Reservation\Models\Order;
use Platform\Reservation\Models\Payment;

/**
 * Storno einer Bestellung durch den Kunden (Storno-Link) oder das Team.
 * Standard: sofortiges Storno + Mollie-Rückerstattung. Ist die Freigabe im
 * Team-Setting aktiv, wird zunächst nur „Storno angefragt" gesetzt und erst
 * nach {@see approveAndCancel()} tatsächlich storniert/erstattet.
 *
 * Storno gibt die Plätze wieder frei (stornierte Buchungen zählen in der
 * SeatAvailability nicht mehr).
 */
class OrderCancellationService
{
    public function __construct(protected MolliePaymentService $payments)
    {
    }

    /**
     * Vom Kunden ausgelöst (Storno-Link). Prüft Aktivierung + Frist + Status.
     *
     * @return array{status:string,message:string,refund?:array}
     */
    public function requestOrCancel(Order $order): array
    {
        $order->loadMissing(['event', 'bookings']);
        $settings = CheckoutSetting::forTeam((int) $order->team_id);

        if ($order->status === Order::STATUS_CANCELLED) {
            return ['status' => 'already_cancelled', 'message' => 'Diese Bestellung ist bereits storniert.'];
        }

        if (! $order->isCancellable($settings)) {
            return ['status' => 'not_cancellable', 'message' => 'Ein Storno ist für diese Bestellung nicht (mehr) möglich.'];
        }

        if ($settings->cancellationRequiresApproval()) {
            $order->update([
                'status'                    => Order::STATUS_CANCELLATION_REQUESTED,
                'cancellation_requested_at' => now(),
            ]);

            return ['status' => 'requested', 'message' => 'Ihr Storno wurde angefragt und wird geprüft. Sie erhalten die Rückerstattung nach Freigabe.'];
        }

        return $this->cancel($order);
    }

    /**
     * Freigabe/Storno durch das Team (auch für „Storno angefragt").
     *
     * @return array{status:string,message:string,refund?:array}
     */
    public function approveAndCancel(Order $order, ?string $grund = null): array
    {
        $order->loadMissing('bookings');

        if ($order->status === Order::STATUS_CANCELLED) {
            return ['status' => 'already_cancelled', 'message' => 'Bereits storniert.'];
        }

        if (! in_array($order->status, [Order::STATUS_CONFIRMED, Order::STATUS_CANCELLATION_REQUESTED], true)) {
            return ['status' => 'not_cancellable', 'message' => 'Bestellung kann in diesem Status nicht storniert werden.'];
        }

        return $this->cancel($order, $grund);
    }

    /**
     * Geld ist bei Mollie zurückgegangen, ohne dass PausePlus es ausgelöst hat
     * – jemand hat im Mollie-Dashboard erstattet, oder die Bank hat
     * zurückgebucht.
     *
     * Ohne diese Behandlung bliebe die Buchung „bestätigt": Der Gast stünde
     * weiter auf dem Laufzettel, und der Betrag zählte weiter in Umsatz und
     * DATEV, obwohl das Geld weg ist.
     *
     * VOLL zurück → storniert wie jedes andere Storno, samt Mail und freiem
     * Platz. TEILWEISE → nur festhalten: Welche Position gemeint war, weiß
     * niemand, und wer 8 von 24 € zurückbekommt, soll nicht vor einem leeren
     * Tisch stehen. Der Betrag steht danach an der Zahlung, damit im
     * Backoffice jemand hinsehen kann.
     *
     * @return string keine|teilweise|voll|rueckbelastung
     */
    public function erstattungAusMollie(Order $order, Payment $payment, float $erstattet, float $rueckbelastet): string
    {
        $zurueck = $erstattet + $rueckbelastet;

        if ($zurueck <= 0.0) {
            return 'keine';
        }

        // Centbruchteile: Mollie rechnet in Strings, ein Vergleich auf Gleichheit
        // ginge irgendwann schief.
        $voll           = $zurueck >= ((float) $payment->amount - 0.005);
        $rueckbelastung = $rueckbelastet > 0.0;

        $payment->update([
            'status' => match (true) {
                $rueckbelastung => 'charged_back',
                $voll           => 'refunded',
                // Teilweise: Bei Mollie steht die Zahlung weiter auf „paid",
                // und das stimmt ja auch – ein Teil ist geflossen.
                default         => $payment->status,
            },
            // NUR bei voll. refunded_at ist die Sperre gegen eine zweite
            // Erstattung; nach einer Teilerstattung muss der Rest noch
            // erstattbar bleiben.
            'refunded_at'     => $voll ? ($payment->refunded_at ?? now()) : $payment->refunded_at,
            'refunded_amount' => $zurueck,
        ]);

        if (! $voll) {
            return 'teilweise';
        }

        // Über den gewohnten Weg – der schreibt die Buchungen fort und schickt
        // die Storno-Mail. refundOrder() darin läuft ins Leere, weil oben
        // refunded_at gesetzt wurde: KEINE zweite Erstattung bei Mollie.
        $this->approveAndCancel($order, $rueckbelastung
            ? 'Ihre Bank hat die Zahlung zurückgebucht.'
            : 'Der Betrag wurde Ihnen zurückerstattet.');

        return $rueckbelastung ? 'rueckbelastung' : 'voll';
    }

    /** Führt Storno (Plätze frei) + Rückerstattung + Mail an den Gast aus. */
    protected function cancel(Order $order, ?string $grund = null): array
    {
        DB::transaction(function () use ($order) {
            foreach ($order->bookings as $booking) {
                // Alles ausser bereits storniert.
                //
                // Vorher standen hier nur "ausstehend" und "bestaetigt", und
                // eine Erstattung nach dem Abend lief ins Leere: Die Buchung
                // blieb auf No-Show oder Abgeschlossen stehen, waehrend die
                // Bestellung storniert war. Bei einer abgeschlossenen Buchung
                // zaehlte das Geld danach weiter in Umsatz und DATEV, obwohl es
                // zurueckgegangen ist - und bei No-Show genauso, sobald die
                // Einstellung No-Shows als Umsatz fuehrt.
                if ($booking->status !== Booking::STATUS_CANCELLED) {
                    $booking->update(['status' => Booking::STATUS_CANCELLED]);
                }
            }

            $order->update(['status' => Order::STATUS_CANCELLED]);
        });

        $refund = $this->payments->refundOrder($order);

        // Der Gast erfährt davon – bis zum 04.09.2026 tat er das nicht. Im
        // besten Fall merkte er es am Kontoauszug, im schlechteren stand er
        // vor der Halle.
        //
        // NACH der Erstattung, damit in der Mail steht, was wirklich passiert
        // ist: „already_refunded" zählt mit, „not_paid" oder „no_payment"
        // nicht - über eine Gutschrift zu schreiben, die es nicht gibt, wäre
        // schlimmer als nichts zu schreiben.
        //
        // Der Versand darf das Storno nicht umwerfen: GastMail gibt Fehler als
        // Status zurück, statt zu werfen. Die Bestellung IST storniert, auch
        // wenn kein Absender konfiguriert ist.
        $mail = OrderCancellationMailer::send(
            $order,
            $grund,
            in_array($refund['status'] ?? '', ['refunded', 'already_refunded'], true),
        );

        return [
            'status'  => 'cancelled',
            'message' => 'Ihre Bestellung wurde storniert.',
            'refund'  => $refund,
            'mail'    => $mail,
        ];
    }
}
