<?php

namespace Platform\Reservation\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\Reservation\Models\PaymentSetting;
use Platform\Reservation\Services\MolliePaymentService;

/**
 * Admin-Einstellungen für Mollie (pro Team). API-Keys werden verschlüsselt
 * gespeichert; vorhandene Keys werden maskiert angezeigt und nur bei Eingabe
 * überschrieben.
 */
class PaymentSettings extends Component
{
    public bool $enabled = false;
    public string $mode = 'test';
    public string $testApiKey = '';
    public string $liveApiKey = '';

    public bool $testKeySet = false;
    public bool $liveKeySet = false;

    protected function getTeamId(): int
    {
        return (int) (Auth::user()?->current_team_id ?? 0);
    }

    public function mount(): void
    {
        $setting = PaymentSetting::where('team_id', $this->getTeamId())->first();

        if ($setting) {
            $this->enabled    = $setting->enabled;
            $this->mode       = $setting->mode;
            $this->testKeySet = (bool) $setting->test_api_key;
            $this->liveKeySet = (bool) $setting->live_api_key;
        }
    }

    public function save(): void
    {
        $this->validate([
            'mode'       => 'required|in:test,live',
            'testApiKey' => 'nullable|string|max:255',
            'liveApiKey' => 'nullable|string|max:255',
        ]);

        $setting = PaymentSetting::firstOrNew([
            'team_id'  => $this->getTeamId(),
            'provider' => 'mollie',
        ]);

        $setting->enabled = $this->enabled;
        $setting->mode    = $this->mode;

        // Keys nur überschreiben, wenn etwas eingegeben wurde.
        if (trim($this->testApiKey) !== '') {
            $setting->test_api_key = trim($this->testApiKey);
        }
        if (trim($this->liveApiKey) !== '') {
            $setting->live_api_key = trim($this->liveApiKey);
        }

        $setting->save();

        $this->testApiKey = '';
        $this->liveApiKey = '';
        $this->testKeySet = (bool) $setting->test_api_key;
        $this->liveKeySet = (bool) $setting->live_api_key;

        session()->flash('payment_message', 'Zahlungseinstellungen gespeichert.');
    }

    public function getIsReadyProperty(): bool
    {
        return app(MolliePaymentService::class)->isEnabledForTeam($this->getTeamId());
    }

    public function render()
    {
        return view('reservation::livewire.payment-settings', [
            'webhookUrl' => route('reservation.api.payment.webhook'),
            'isReady'    => $this->getIsReadyProperty(),
        ])->layout('platform::layouts.app');
    }
}
