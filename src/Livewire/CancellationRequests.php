<?php

namespace Platform\Reservation\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Reservation\Models\Order;
use Platform\Reservation\Services\OrderCancellationService;

/**
 * Offene Storno-Anfragen (Freigabe-Modus): Freigeben (storniert + erstattet)
 * oder Ablehnen (Bestellung bleibt bestätigt).
 */
class CancellationRequests extends Component
{
    protected function getTeamId(): int
    {
        return (int) (Auth::user()?->current_team_id ?? 0);
    }

    #[Computed]
    public function requests(): \Illuminate\Database\Eloquent\Collection
    {
        return Order::where('team_id', $this->getTeamId())
            ->where('status', Order::STATUS_CANCELLATION_REQUESTED)
            ->with(['event', 'payment', 'bookings'])
            ->orderByDesc('cancellation_requested_at')
            ->orderByDesc('updated_at')
            ->get();
    }

    public function approve(int $orderId, OrderCancellationService $service): void
    {
        $order = Order::where('team_id', $this->getTeamId())->find($orderId);

        if ($order && $order->status === Order::STATUS_CANCELLATION_REQUESTED) {
            $result = $service->approveAndCancel($order);
            $refund = $result['refund']['status'] ?? null;
            session()->flash('cancel_message', 'Storno freigegeben & Bestellung storniert.'
                . ($refund === 'refunded' ? ' Rückerstattung ausgelöst.' : ($refund ? ' (Rückerstattung: ' . $refund . ')' : '')));
        }

        unset($this->requests);
    }

    public function reject(int $orderId): void
    {
        $order = Order::where('team_id', $this->getTeamId())->find($orderId);

        if ($order && $order->status === Order::STATUS_CANCELLATION_REQUESTED) {
            $order->update([
                'status'                    => Order::STATUS_CONFIRMED,
                'cancellation_requested_at' => null,
            ]);
            session()->flash('cancel_message', 'Storno-Anfrage abgelehnt – die Bestellung bleibt bestätigt.');
        }

        unset($this->requests);
    }

    public function render()
    {
        return view('reservation::livewire.cancellation-requests')
            ->layout('platform::layouts.app');
    }
}
