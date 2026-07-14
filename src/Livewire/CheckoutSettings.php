<?php

namespace Platform\Reservation\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\Reservation\Models\CheckoutSetting;

/**
 * Allgemeine Modul-Einstellungen: Checkout-Texte (18+, Rechtstext,
 * Datenschutz-Link) und Standard-Raumfreigabe für neue Termine.
 */
class CheckoutSettings extends Component
{
    public string $ageCheckText = '';
    public string $legalText = '';
    public string $privacyUrl = '';
    public string $defaultRoomReleaseMode = 'parallel';

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
    }

    public function save(): void
    {
        $this->validate([
            'ageCheckText'           => 'nullable|string|max:1000',
            'legalText'              => 'nullable|string|max:1000',
            'privacyUrl'             => 'nullable|url|max:255',
            'defaultRoomReleaseMode' => 'required|in:parallel,sequential',
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

        session()->flash('checkout_message', 'Einstellungen gespeichert.');
    }

    public function render()
    {
        return view('reservation::livewire.checkout-settings', [
            'defaultAge'   => CheckoutSetting::DEFAULT_AGE_TEXT,
            'defaultLegal' => CheckoutSetting::DEFAULT_LEGAL_TEXT,
        ])->layout('platform::layouts.app');
    }
}
