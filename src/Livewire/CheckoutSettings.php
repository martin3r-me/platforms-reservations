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
    public bool $softTableCapacity = false;
    public ?int $maxGroupEmptyTable = null;

    // #522: zusätzlich angebotene Sprachen (kommagetrennt, DE ist immer dabei)
    public string $languagesCsv = '';

    // Basis-URL des Shop-Frontends (Allowlist für Zahlungs-Rücksprung)
    public string $guestFrontendUrl = '';

    // Absender (CRM-Comms-Channel) für Bestellbestätigungen – kein Default
    public ?int $confirmationChannelId = null;

    // Selbst-Storno
    public bool $cancellationEnabled = false;
    public ?int $cancellationDeadlineHours = null;
    public bool $cancellationRequiresApproval = false;

    // #520/#521: Anmeldefelder (required|optional|hidden)
    public string $fieldEmail = 'required';
    public string $fieldPhone = 'optional';
    public string $fieldNotes = 'optional';

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
        $this->softTableCapacity      = $setting->softTableCapacity();
        $this->maxGroupEmptyTable     = $setting->maxGroupEmptyTable();
        $this->languagesCsv           = implode(', ', array_filter($setting->languages(), fn ($l) => $l !== 'de'));
        $this->guestFrontendUrl       = (string) ($setting->guest_frontend_url ?? '');
        $this->confirmationChannelId  = $setting->confirmationChannelId();
        $this->cancellationEnabled           = $setting->cancellationEnabled();
        $this->cancellationDeadlineHours     = $setting->cancellationDeadlineHours();
        $this->cancellationRequiresApproval  = $setting->cancellationRequiresApproval();
        $this->fieldEmail             = $setting->fieldMode('email');
        $this->fieldPhone             = $setting->fieldMode('phone');
        $this->fieldNotes             = $setting->fieldMode('notes');

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
            'softTableCapacity'      => 'boolean',
            'maxGroupEmptyTable'     => 'nullable|integer|min:1|max:200',
            'guestFrontendUrl'       => 'nullable|url|max:255',
            'confirmationChannelId'  => 'nullable|integer',
            'cancellationEnabled'          => 'boolean',
            'cancellationDeadlineHours'    => 'nullable|integer|min:0|max:8760',
            'cancellationRequiresApproval' => 'boolean',
            'fieldEmail'             => 'required|in:required,optional,hidden',
            'fieldPhone'             => 'required|in:required,optional,hidden',
            'fieldNotes'             => 'required|in:required,optional,hidden',
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
            'soft_table_capacity'       => $this->softTableCapacity,
            'max_group_empty_table'     => $this->softTableCapacity ? $this->maxGroupEmptyTable : null,
            'languages'                 => collect(explode(',', $this->languagesCsv))
                ->map(fn ($l) => strtolower(trim($l)))
                ->filter()
                ->reject(fn ($l) => $l === 'de')
                ->unique()
                ->values()
                ->all(),
            'field_email'               => $this->fieldEmail,
            'field_phone'               => $this->fieldPhone,
            'field_notes'               => $this->fieldNotes,
            'guest_frontend_url'        => trim($this->guestFrontendUrl) ?: null,
            'confirmation_channel_id'   => $this->confirmationChannelId ?: null,
            'cancellation_enabled'           => $this->cancellationEnabled,
            'cancellation_deadline_hours'    => $this->cancellationEnabled ? $this->cancellationDeadlineHours : null,
            'cancellation_requires_approval' => $this->cancellationEnabled ? $this->cancellationRequiresApproval : false,
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

    /**
     * Aktive Postmark-E-Mail-Channels des Teams (für die Absender-Auswahl).
     * Defensiv: ohne CRM/Comms-Modul leere Liste.
     *
     * @return array<int, array{value:int, label:string}>
     */
    protected function emailChannelOptions(): array
    {
        if (!class_exists(\Platform\Crm\Models\CommsChannel::class)) {
            return [];
        }

        return \Platform\Crm\Models\CommsChannel::query()
            ->where('team_id', $this->getTeamId())
            ->where('type', 'email')
            ->where('provider', 'postmark')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => [
                'value' => $c->id,
                'label' => trim(($c->name ?: 'Absender') . ' · ' . $c->sender_identifier),
            ])
            ->all();
    }

    public function render()
    {
        return view('reservation::livewire.checkout-settings', [
            'defaultAge'    => CheckoutSetting::DEFAULT_AGE_TEXT,
            'defaultLegal'  => CheckoutSetting::DEFAULT_LEGAL_TEXT,
            'payReady'      => app(MolliePaymentService::class)->isEnabledForTeam($this->getTeamId()),
            'webhookUrl'    => route('reservation.api.payment.webhook'),
            'emailChannels' => $this->emailChannelOptions(),
        ])->layout('platform::layouts.app');
    }
}
