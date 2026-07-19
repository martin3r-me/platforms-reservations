<?php

namespace Platform\Reservation\Livewire\Guest;

use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Platform\Reservation\Models\CheckoutSetting;
use Platform\Reservation\Models\Order;
use Platform\Reservation\Services\OrderCancellationService;

/**
 * Öffentliche Storno-Seite (per signierter Order-URL aus der Bestätigungs-Mail).
 * Zeigt die Bestellung + Frist und storniert nach Bestätigung (inkl. Mollie-
 * Rückerstattung), bzw. fragt bei aktiver Freigabe nur an.
 */
class OrderCancel extends Component
{
    #[Locked]
    public string $uuid = '';

    /** null = noch keine Aktion; sonst cancelled|requested|error|already_cancelled|not_cancellable */
    public ?string $resultStatus = null;
    public string $resultMessage = '';

    public function mount(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    #[Computed]
    public function order(): ?Order
    {
        return Order::withoutGlobalScope('team')
            ->where('uuid', $this->uuid)
            ->with(['event', 'bookings' => fn ($q) => $q->withoutGlobalScope('team')->with('slot'), 'payment'])
            ->first();
    }

    #[Computed]
    public function settings(): CheckoutSetting
    {
        return CheckoutSetting::forTeam((int) ($this->order?->team_id ?? 0));
    }

    #[Computed]
    public function cancellable(): bool
    {
        $order = $this->order;

        return $order && $order->isCancellable($this->settings);
    }

    #[Computed]
    public function deadline(): ?\Carbon\Carbon
    {
        return $this->order?->cancellationDeadline($this->settings);
    }

    public function confirmCancel(OrderCancellationService $service): void
    {
        $order = $this->order;

        if (! $order) {
            $this->resultStatus  = 'error';
            $this->resultMessage = 'Bestellung nicht gefunden.';

            return;
        }

        $result = $service->requestOrCancel($order);
        unset($this->order);

        $this->resultStatus  = $result['status'];
        $this->resultMessage = $result['message'];
    }

    public function render()
    {
        return view('reservation::livewire.guest.order-cancel')
            ->layout('platform::layouts.guest');
    }
}
