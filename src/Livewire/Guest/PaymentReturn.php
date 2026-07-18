<?php

namespace Platform\Reservation\Livewire\Guest;

use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Platform\Reservation\Models\Order;
use Platform\Reservation\Services\MolliePaymentService;

/**
 * Rückkehr-Seite von Mollie (per Order-UUID). Da der Redirect keinen
 * verlässlichen End-Status garantiert, gleichen wir beim Laden aktiv mit Mollie
 * ab und zeigen dann den Status der Bestellung (bezahlt / in Bearbeitung /
 * fehlgeschlagen).
 */
class PaymentReturn extends Component
{
    #[Locked]
    public string $uuid = '';

    public function mount(string $uuid): void
    {
        $this->uuid = $uuid;
        $this->syncPayment();
    }

    /** Vom wire:poll auf der „in Bearbeitung“-Seite aufgerufen. */
    public function refresh(): void
    {
        if ($this->order?->status === Order::STATUS_PENDING) {
            $this->syncPayment();
        }
    }

    /** Status aktiv mit Mollie abgleichen und den Computed-Cache erneuern. */
    protected function syncPayment(): void
    {
        $mollieId = $this->order?->payment?->mollie_id;

        if ($mollieId) {
            try {
                app(MolliePaymentService::class)->syncFromMollie($mollieId);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        unset($this->order);
    }

    #[Computed]
    public function order(): ?Order
    {
        return Order::where('uuid', $this->uuid)
            ->with(['payment', 'bookings.event', 'bookings.slot'])
            ->first();
    }

    public function render()
    {
        return view('reservation::livewire.guest.payment-return')
            ->layout('platform::layouts.guest');
    }
}
