<?php

namespace Platform\Reservation\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\Reservation\Models\CheckoutSetting;
use Platform\Reservation\Models\PaymentSetting;
use Platform\Reservation\Services\MolliePaymentService;

/**
 * Allgemeine Modul-Einstellungen: Standard-Raumfreigabe, Zahlung (Mollie)
 * und Checkout-Texte (18+, Rechtstext, Datenschutz-Link).
 */
class CheckoutSettings extends Component
{
    public string $ageCheckText = '';
    public string $legalText = '';
    public string $privacyUrl = '';
    public string $defaultRoomReleaseMode = 'parallel';

    // Zahlung (Mollie)
    public bool $payEnabled = false;
    public string $payMode = 'test';
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
        $setting = CheckoutSetting::forTeam($this->getTeamId());

        $this->ageCheckText           = (string) ($setting->age_check_text ?? '');
        $this->legalText              = (string) ($setting->legal_text ?? '');
        $this->privacyUrl             = (string) ($setting->privacy_url ?? '');
        $this->defaultRoomReleaseMode = $setting->defaultRoomReleaseMode();

        $payment = PaymentSetting::where('team_id', $this->getTeamId())->first();
        if ($payment) {
            $this->payEnabled = $payment->enabled;
            $this->payMode    = $payment->mode;
            $this->testKeySet = (bool) $payment->test_api_key;
            $this->liveKeySet = (bool) $payment->live_api_key;
        }
    }

    public function save(): void
    {
        $this->validate([
            'ageCheckText'           => 'nullable|string|max:1000',
            'legalText'              => 'nullable|string|max:1000',
            'privacyUrl'             => 'nullable|url|max:255',
            'defaultRoomReleaseMode' => 'required|in:parallel,sequential',
            'payMode'                => 'required|in:test,live',
            'testApiKey'             => 'nullable|string|max:255',
            'liveApiKey'             => 'nullable|string|max:255',
        ], [
            'privacyUrl.url' => 'Bitte eine gültige URL angeben (inkl. https://).',
        ]);

        $setting = CheckoutSetting::forTeam($this->getTeamId());
        $setting->fill([
            'age_check_text'            => trim($this->ageCheckText) ?: null,
            'legal_text'                => trim($this->legalText) ?: null,
            'privacy_url'               => trim($this->privacyUrl) ?: null,
            'default_room_release_mode' => $this->defaultRoomReleaseMode,
        ])->save();

        // Zahlung (Mollie) speichern – Keys nur bei Eingabe überschreiben.
        $payment = PaymentSetting::firstOrNew([
            'team_id'  => $this->getTeamId(),
            'provider' => 'mollie',
        ]);
        $payment->enabled = $this->payEnabled;
        $payment->mode    = $this->payMode;
        if (trim($this->testApiKey) !== '') {
            $payment->test_api_key = trim($this->testApiKey);
        }
        if (trim($this->liveApiKey) !== '') {
            $payment->live_api_key = trim($this->liveApiKey);
        }
        $payment->save();

        $this->testApiKey = '';
        $this->liveApiKey = '';
        $this->testKeySet = (bool) $payment->test_api_key;
        $this->liveKeySet = (bool) $payment->live_api_key;

        session()->flash('checkout_message', 'Einstellungen gespeichert.');
    }

    public function render()
    {
        return view('reservation::livewire.checkout-settings', [
            'defaultAge'   => CheckoutSetting::DEFAULT_AGE_TEXT,
            'defaultLegal' => CheckoutSetting::DEFAULT_LEGAL_TEXT,
            'payReady'     => app(MolliePaymentService::class)->isEnabledForTeam($this->getTeamId()),
            'webhookUrl'   => route('reservation.api.payment.webhook'),
        ])->layout('platform::layouts.app');
    }
}
