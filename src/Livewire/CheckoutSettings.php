<?php

namespace Platform\Reservation\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;
use Platform\Reservation\Models\CheckoutSetting;
use Platform\Reservation\Models\PaymentSetting;
use Platform\Reservation\Services\MolliePaymentService;
use Platform\Reservation\Support\PrintingBridge;

/**
 * Allgemeine Modul-Einstellungen: Standard-Raumfreigabe, Zahlung (Mollie)
 * und Checkout-Texte (18+, Rechtstext, Datenschutz-Link).
 */
class CheckoutSettings extends Component
{
    use WithFileUploads;

    // Beleg-Branding (Logo, Akzentfarbe, Fußzeile)
    public string $receiptAccentColor = '';
    public string $receiptFooterText = '';
    public $receiptLogo = null;

    public string $ageCheckText = '';
    public string $legalText = '';
    public string $privacyUrl = '';
    public string $defaultRoomReleaseMode = 'parallel';
    // Neue Auftraege automatisch drucken
    public bool $autoPrintEnabled = false;
    public ?int $autoPrintPrinterId = null;
    public ?int $autoPrintPrinterGroupId = null;
    /** printer | group – welches Ziel gewaehlt ist. */
    public string $autoPrintTarget = 'printer';

    public bool $softTableCapacity = false;
    public ?int $maxGroupEmptyTable = null;

    // #522: zusätzlich angebotene Sprachen (kommagetrennt, DE ist immer dabei)
    public string $languagesCsv = '';

    // Basis-URL des Shop-Frontends (Allowlist für Zahlungs-Rücksprung)
    public string $guestFrontendUrl = '';

    // Absender (CRM-Comms-Channel) für Bestellbestätigungen – kein Default
    public ?int $confirmationChannelId = null;

    // Aussteller-/Rechnungsangaben (Firmendaten fürs Beleg-PDF)
    /** @var array<string,?string> */
    public array $issuer = [];

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
        $this->issuer                 = $setting->issuer();
        $this->cancellationEnabled           = $setting->cancellationEnabled();
        $this->cancellationDeadlineHours     = $setting->cancellationDeadlineHours();
        $this->cancellationRequiresApproval  = $setting->cancellationRequiresApproval();
        $this->receiptAccentColor     = $setting->accentColor();
        $this->receiptFooterText      = (string) ($setting->receipt_footer_text ?? '');
        $this->fieldEmail             = $setting->fieldMode('email');
        $this->fieldPhone             = $setting->fieldMode('phone');
        $this->fieldNotes             = $setting->fieldMode('notes');

        $this->autoPrintEnabled        = (bool) $setting->auto_print_enabled;
        $this->autoPrintPrinterId      = $setting->auto_print_printer_id;
        $this->autoPrintPrinterGroupId = $setting->auto_print_printer_group_id;
        // Gruppe nur vorwaehlen, wenn auch eine hinterlegt ist.
        $this->autoPrintTarget         = $setting->auto_print_printer_group_id ? 'group' : 'printer';

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
            'autoPrintEnabled'       => 'boolean',
            'autoPrintPrinterId'     => 'nullable|integer',
            'autoPrintPrinterGroupId'=> 'nullable|integer',
            'maxGroupEmptyTable'     => 'nullable|integer|min:1|max:200',
            'guestFrontendUrl'       => 'nullable|url|max:255',
            'confirmationChannelId'  => 'nullable|integer',
            'issuer.email'           => 'nullable|email|max:255',
            'issuer.website'         => 'nullable|string|max:255',
            'cancellationEnabled'          => 'boolean',
            'cancellationDeadlineHours'    => 'nullable|integer|min:0|max:8760',
            'cancellationRequiresApproval' => 'boolean',
            'fieldEmail'             => 'required|in:required,optional,hidden',
            'fieldPhone'             => 'required|in:required,optional,hidden',
            'fieldNotes'             => 'required|in:required,optional,hidden',
            'receiptAccentColor'     => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'receiptFooterText'      => 'nullable|string|max:500',
            'payMode'                => 'required|in:test,live',
            'testApiKey'             => 'nullable|string|max:255',
            'liveApiKey'             => 'nullable|string|max:255',
        ], [
            'privacyUrl.url' => 'Bitte eine gültige URL angeben (inkl. https://).',
            'receiptAccentColor.regex' => 'Bitte eine Farbe als Hex-Wert angeben, z. B. #285567.',
        ]);

        $setting = CheckoutSetting::forTeam($this->getTeamId());
        $setting->fill([
            'age_check_text'            => trim($this->ageCheckText) ?: null,
            'legal_text'                => trim($this->legalText) ?: null,
            'privacy_url'               => trim($this->privacyUrl) ?: null,
            'default_room_release_mode' => $this->defaultRoomReleaseMode,
            'soft_table_capacity'       => $this->softTableCapacity,
            'auto_print_enabled'        => $this->autoPrintEnabled,
            // Immer nur EIN Ziel speichern – sonst bliebe beim Umschalten der
            // alte Wert stehen und es wuerde doppelt gedruckt.
            'auto_print_printer_id'     => $this->autoPrintEnabled && $this->autoPrintTarget === 'printer'
                ? $this->autoPrintPrinterId : null,
            'auto_print_printer_group_id' => $this->autoPrintEnabled && $this->autoPrintTarget === 'group'
                ? $this->autoPrintPrinterGroupId : null,
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
            'issuer'                    => collect(CheckoutSetting::ISSUER_FIELDS)
                ->mapWithKeys(fn ($f) => [$f => (trim((string) ($this->issuer[$f] ?? '')) ?: null)])
                ->filter()
                ->all(),
            'receipt_accent_color'      => trim($this->receiptAccentColor) ?: null,
            'receipt_footer_text'       => trim($this->receiptFooterText) ?: null,
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

        // Kein Flash mehr: Der Hinweis stand ganz oben auf einer langen Seite und
        // war nach dem Speichern unten gar nicht zu sehen. Stattdessen eine kurz
        // eingeblendete Meldung, unabhaengig vom Scrollstand.
        $this->dispatch('reservation-saved', message: 'Einstellungen gespeichert.');
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

    /** Aktuelle Einstellungen – für Logo-Vorschau und Zustand im Formular. */
    #[\Livewire\Attributes\Computed]
    public function setting(): CheckoutSetting
    {
        return CheckoutSetting::forTeam($this->getTeamId());
    }

    /**
     * Logo hochladen. Läuft über HasContextImage, also denselben Weg wie
     * Grundriss und Artikelbilder – inklusive Ersetzen des vorherigen Bildes.
     */
    public function updatedReceiptLogo(): void
    {
        $this->validate(['receiptLogo' => 'image|max:4096'], [
            'receiptLogo.image' => 'Bitte ein Bild hochladen (JPG, PNG oder WebP).',
            'receiptLogo.max'   => 'Das Logo ist zu groß (max. 4 MB).',
        ]);

        try {
            $this->setting->setContextImage(
                $this->receiptLogo,
                'reservation.receipt.logo',
                $this->getTeamId(),
                Auth::id(),
            );
        } catch (\Throwable $e) {
            report($e);
            $this->addError('receiptLogo', 'Logo konnte nicht gespeichert werden: ' . $e->getMessage());
        } finally {
            $this->receiptLogo = null;
            unset($this->setting);
        }
    }

    public function removeReceiptLogo(): void
    {
        $this->setting->clearContextImage($this->getTeamId());
        unset($this->setting);
    }

    /* --- Automatischer Bon-Druck --------------------------------------- */

    /** Ohne Druck-Modul wird der ganze Abschnitt ausgeblendet. */
    #[\Livewire\Attributes\Computed]
    public function printingAvailable(): bool
    {
        return PrintingBridge::available();
    }

    #[\Livewire\Attributes\Computed]
    public function printers(): \Illuminate\Support\Collection
    {
        return PrintingBridge::printers();
    }

    #[\Livewire\Attributes\Computed]
    public function printerGroups(): \Illuminate\Support\Collection
    {
        return PrintingBridge::printerGroups();
    }

    public function updatedAutoPrintTarget(): void
    {
        $this->autoPrintPrinterId      = null;
        $this->autoPrintPrinterGroupId = null;
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
