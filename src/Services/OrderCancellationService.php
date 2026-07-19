<?php

namespace Platform\Reservation\Services;

use Illuminate\Support\Facades\DB;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\CheckoutSetting;
use Platform\Reservation\Models\Order;

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
    public function approveAndCancel(Order $order): array
    {
        $order->loadMissing('bookings');

        if ($order->status === Order::STATUS_CANCELLED) {
            return ['status' => 'already_cancelled', 'message' => 'Bereits storniert.'];
        }

        if (! in_array($order->status, [Order::STATUS_CONFIRMED, Order::STATUS_CANCELLATION_REQUESTED], true)) {
            return ['status' => 'not_cancellable', 'message' => 'Bestellung kann in diesem Status nicht storniert werden.'];
        }

        return $this->cancel($order);
    }

    /** Führt Storno (Plätze frei) + Rückerstattung aus. */
    protected function cancel(Order $order): array
    {
        DB::transaction(function () use ($order) {
            foreach ($order->bookings as $booking) {
                if (in_array($booking->status, [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED], true)) {
                    $booking->update(['status' => Booking::STATUS_CANCELLED]);
                }
            }

            $order->update(['status' => Order::STATUS_CANCELLED]);
        });

        $refund = $this->payments->refundOrder($order);

        return [
            'status'  => 'cancelled',
            'message' => 'Ihre Bestellung wurde storniert.',
            'refund'  => $refund,
        ];
    }
}
