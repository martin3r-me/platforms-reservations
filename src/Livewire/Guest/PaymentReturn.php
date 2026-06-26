<?php

namespace Platform\Reservation\Livewire\Guest;

use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Services\MolliePaymentService;

/**
 * Rückkehr-Seite von Mollie. Da der Redirect keinen verlässlichen End-Status
 * garantiert, gleichen wir beim Laden aktiv mit Mollie ab und zeigen dann den
 * Status der Buchung (bezahlt / in Bearbeitung / fehlgeschlagen).
 */
class PaymentReturn extends Component
{
    #[Locked]
    public string $uuid = '';

    public function mount(string $uuid): void
    {
        $this->uuid = $uuid;

        $booking = $this->booking;

        if ($booking?->mollie_payment_id) {
            try {
                app(MolliePaymentService::class)->syncFromMollie($booking->mollie_payment_id);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /** Vom wire:poll auf der „in Bearbeitung“-Seite aufgerufen. */
    public function refresh(): void
    {
        $booking = $this->booking;

        if ($booking?->mollie_payment_id && $booking->status === Booking::STATUS_PENDING) {
            try {
                app(MolliePaymentService::class)->syncFromMollie($booking->mollie_payment_id);
            } catch (\Throwable $e) {
                report($e);
            }
            unset($this->booking);
        }
    }

    #[Computed]
    public function booking(): ?Booking
    {
        return Booking::where('uuid', $this->uuid)
            ->with(['event', 'slot', 'payment'])
            ->first();
    }

    public function render()
    {
        return view('reservation::livewire.guest.payment-return')
            ->layout('platform::layouts.guest');
    }
}
