<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Einstellungen" icon="heroicon-o-cog-6-tooth" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Einstellungen'],
        ]">
            <x-nx-button variant="primary" wire:click="save">Speichern</x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
    <div class="max-w-2xl space-y-5">

    @if (session('checkout_message'))
        <x-nx-callout variant="success">{{ session('checkout_message') }}</x-nx-callout>
    @endif

    {{-- Termine --}}
    <x-nx-card flush>
        <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
            @svg('heroicon-o-ticket', 'w-4 h-4 text-[color:var(--nx-muted)]')
            <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Termine</h2>
        </div>
        <div class="p-5">
            <x-nx-input-select
                name="defaultRoomReleaseMode"
                label="Standard-Raumfreigabe (Vorauswahl bei neuen Terminen)"
                :options="[
                    ['value' => 'parallel', 'label' => 'Parallel (alle Räume offen)'],
                    ['value' => 'sequential', 'label' => 'Sequentiell (Raum 2 nach Füllung von Raum 1)'],
                ]"
                wire:model="defaultRoomReleaseMode"
            />
            <p class="mt-1 text-[11px] text-[color:var(--nx-muted)]">Beim Anlegen eines Termins kann die Freigabe weiterhin einzeln geändert werden.</p>

            <label class="mt-4 flex items-start gap-2 text-sm text-[color:var(--nx-text)] cursor-pointer">
                <input wire:model.live="softTableCapacity" type="checkbox" class="mt-0.5 rounded-[4px] accent-[var(--nx-accent)]" />
                <span>
                    Weiche Tisch-Kapazität (Großgruppen)
                    <span class="block text-[11px] text-[color:var(--nx-muted)]">Eine Gruppe, die nicht in die freien Plätze passt, darf einen <strong>komplett leeren</strong> Tisch über die Platzzahl hinaus belegen (z. B. Stehtische). Teilbelegte Tische bleiben für zu große Gruppen gesperrt.</span>
                </span>
            </label>

            @if ($softTableCapacity)
                <div class="mt-3 ml-6 max-w-xs">
                    <x-nx-input-text type="number" name="maxGroupEmptyTable" label="Max. Gruppe auf leerem Tisch (leer = unbegrenzt)" size="sm" wire:model="maxGroupEmptyTable" placeholder="z. B. 12" errorKey="maxGroupEmptyTable" />
                    <p class="mt-1 text-[11px] text-[color:var(--nx-muted)]">Deckelt, wie viele Personen einen leeren Tisch über die Platzzahl hinaus belegen dürfen.</p>
                </div>
            @endif
        </div>
    </x-nx-card>

    {{-- Anmeldefelder (Gast-Checkout) --}}
    <x-nx-card flush>
        <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
            @svg('heroicon-o-identification', 'w-4 h-4 text-[color:var(--nx-muted)]')
            <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Anmeldefelder im Gast-Checkout</h2>
        </div>
        <div class="p-5 space-y-4">
            <p class="text-[11px] text-[color:var(--nx-muted)] m-0">Steuert je Feld, ob es im Gast-Checkout abgefragt wird. <strong>Name</strong> und <strong>Personenzahl</strong> sind immer Pflicht.</p>
            @php
                $fieldModeOptions = [
                    ['value' => 'required', 'label' => 'Pflicht'],
                    ['value' => 'optional', 'label' => 'Optional'],
                    ['value' => 'hidden',   'label' => 'Ausgeblendet'],
                ];
            @endphp
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-nx-input-select name="fieldEmail" label="E-Mail" :options="$fieldModeOptions" wire:model="fieldEmail" />
                <x-nx-input-select name="fieldPhone" label="Rufnummer" :options="$fieldModeOptions" wire:model="fieldPhone" />
                <x-nx-input-select name="fieldNotes" label="Anmerkungen" :options="$fieldModeOptions" wire:model="fieldNotes" />
            </div>
            <p class="text-[11px] text-[color:var(--nx-muted)] m-0">Hinweis: Wird die E-Mail ausgeblendet oder optional gesetzt, kann für diese Bestellung keine automatische Bestätigungs-E-Mail versendet werden.</p>
        </div>
    </x-nx-card>

    {{-- Shop-Sprachen (#522) --}}
    <x-nx-card flush>
        <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
            @svg('heroicon-o-language', 'w-4 h-4 text-[color:var(--nx-muted)]')
            <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Shop-Sprachen</h2>
        </div>
        <div class="p-5">
            <x-nx-input-text name="languagesCsv" label="Zusätzliche Sprachen (Codes, kommagetrennt)" size="sm" wire:model="languagesCsv" placeholder="en, fr" errorKey="languagesCsv" />
            <p class="mt-1 text-[11px] text-[color:var(--nx-muted)]"><strong>Deutsch</strong> ist Basis-/Standardsprache und immer aktiv. Zusätzliche Sprachen z. B. <code>en, fr</code>. Übersetzungen der Speisen, Kategorien, Allergene und Checkout-Texte pflegst du je Objekt (auch per MCP); fehlt eine Übersetzung, wird Deutsch angezeigt.</p>

            <div class="mt-4">
                <x-nx-input-text name="guestFrontendUrl" label="Shop-Frontend-URL (für Zahlungs-Rücksprung)" size="sm" wire:model="guestFrontendUrl" placeholder="https://culinaria.pauseplus.de" errorKey="guestFrontendUrl" />
                <p class="mt-1 text-[11px] text-[color:var(--nx-muted)]">Basis-URL des externen Shops. Nach der Zahlung darf Mollie nur auf eine <code>redirect_url</code> mit <strong>diesem Origin</strong> zurückspringen (Schutz vor offenen Weiterleitungen). Ohne Eintrag wird eine vom Frontend übergebene Rücksprung-URL abgelehnt und die In-App-Seite genutzt.</p>
            </div>
        </div>
    </x-nx-card>

    {{-- Rechnungsangaben (Aussteller) --}}
    <x-nx-card flush>
        <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
            @svg('heroicon-o-building-office-2', 'w-4 h-4 text-[color:var(--nx-muted)]')
            <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Rechnungsangaben (Aussteller)</h2>
        </div>
        <div class="p-5 space-y-3">
            <p class="text-[11px] text-[color:var(--nx-muted)] m-0">Diese Firmendaten erscheinen auf Beleg und Bewirtungsbeleg (USt-IdNr/Steuernummer nach Bedarf).</p>
            <x-nx-input-text name="issuer.name" label="Firmenname" size="sm" wire:model="issuer.name" placeholder="Musterkatering GmbH" />
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="sm:col-span-2"><x-nx-input-text name="issuer.street" label="Straße & Nr." size="sm" wire:model="issuer.street" /></div>
                <x-nx-input-text name="issuer.zip" label="PLZ" size="sm" wire:model="issuer.zip" />
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="sm:col-span-2"><x-nx-input-text name="issuer.city" label="Ort" size="sm" wire:model="issuer.city" /></div>
                <x-nx-input-text name="issuer.country" label="Land" size="sm" wire:model="issuer.country" placeholder="DE" />
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <x-nx-input-text name="issuer.vat_id" label="USt-IdNr" size="sm" wire:model="issuer.vat_id" placeholder="DE123456789" />
                <x-nx-input-text name="issuer.tax_number" label="Steuernummer" size="sm" wire:model="issuer.tax_number" />
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <x-nx-input-text name="issuer.register_court" label="Registergericht" size="sm" wire:model="issuer.register_court" placeholder="Amtsgericht Wuppertal" />
                <x-nx-input-text name="issuer.register_number" label="HRB-Nr." size="sm" wire:model="issuer.register_number" placeholder="8727" />
                <x-nx-input-text name="issuer.managing_directors" label="Vertreten durch" size="sm" wire:model="issuer.managing_directors" placeholder="Max Muster & …" />
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                <x-nx-input-text name="issuer.email" label="E-Mail" size="sm" wire:model="issuer.email" errorKey="issuer.email" />
                <x-nx-input-text name="issuer.phone" label="Telefon" size="sm" wire:model="issuer.phone" />
                <x-nx-input-text name="issuer.fax" label="Telefax" size="sm" wire:model="issuer.fax" />
                <x-nx-input-text name="issuer.website" label="Website" size="sm" wire:model="issuer.website" />
            </div>
        </div>
    </x-nx-card>

    {{-- Bestellbestätigung (E-Mail-Absender) --}}
    <x-nx-card flush>
        <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
            @svg('heroicon-o-envelope', 'w-4 h-4 text-[color:var(--nx-muted)]')
            <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Bestellbestätigung (E-Mail)</h2>
        </div>
        <div class="p-5">
            @if (count($emailChannels))
                <x-nx-input-select
                    name="confirmationChannelId"
                    label="Absender für Bestellbestätigungen"
                    :options="$emailChannels"
                    :nullable="true"
                    nullLabel="— kein Versand —"
                    wire:model="confirmationChannelId"
                />
                <p class="mt-1 text-[11px] text-[color:var(--nx-muted)]">Wähle den Postmark-Absender (aus dem CRM), über den die „Vielen Dank für Ihre Bestellung"-Mail verschickt wird. <strong>Ohne Auswahl wird keine Bestätigung versendet</strong> (kein Standard-Absender).</p>
            @else
                <p class="text-[11px] text-[color:var(--nx-muted)] m-0">Es sind keine aktiven Postmark-E-Mail-Absender im CRM vorhanden. Lege zuerst im CRM einen E-Mail-Channel (Provider Postmark) an – dann kannst du ihn hier auswählen.</p>
            @endif
        </div>
    </x-nx-card>

    {{-- Selbst-Storno --}}
    <x-nx-card flush>
        <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
            @svg('heroicon-o-arrow-uturn-left', 'w-4 h-4 text-[color:var(--nx-muted)]')
            <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Stornierung durch Kunden</h2>
        </div>
        <div class="p-5 space-y-4">
            <label class="flex items-start gap-2 text-sm text-[color:var(--nx-text)] cursor-pointer">
                <input wire:model.live="cancellationEnabled" type="checkbox" class="mt-0.5 rounded-[4px] accent-[var(--nx-accent)]" />
                <span>
                    Selbst-Storno erlauben
                    <span class="block text-[11px] text-[color:var(--nx-muted)]">Kunden erhalten in der Bestätigungs-Mail einen Storno-Link. Innerhalb der Frist wird die Bestellung storniert und die Zahlung über Mollie erstattet.</span>
                </span>
            </label>

            @if ($cancellationEnabled)
                <div class="ml-6 max-w-xs">
                    <x-nx-input-text type="number" name="cancellationDeadlineHours" label="Frist: Stunden vor Veranstaltung" size="sm" wire:model="cancellationDeadlineHours" placeholder="z. B. 72" errorKey="cancellationDeadlineHours" />
                    <p class="mt-1 text-[11px] text-[color:var(--nx-muted)]">Bis wie viele Stunden vor dem Veranstaltungsdatum ein Storno möglich ist. Leer = keine Frist.</p>
                </div>
                <label class="ml-6 flex items-start gap-2 text-sm text-[color:var(--nx-text)] cursor-pointer">
                    <input wire:model="cancellationRequiresApproval" type="checkbox" class="mt-0.5 rounded-[4px] accent-[var(--nx-accent)]" />
                    <span>
                        Storno erst nach Freigabe
                        <span class="block text-[11px] text-[color:var(--nx-muted)]">Standard: aus – der Klick storniert sofort und löst die Rückerstattung aus. Aktiv: der Kunde fragt nur an, das Team gibt frei (dann erst Rückerstattung).</span>
                    </span>
                </label>
            @endif
        </div>
    </x-nx-card>

    {{-- Zahlung (Mollie) --}}
    <x-nx-card flush>
        <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
            @svg('heroicon-o-credit-card', 'w-4 h-4 text-[color:var(--nx-muted)]')
            <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Zahlung (Mollie)</h2>
            @if ($payReady)
                <span class="ml-auto inline-flex items-center gap-1 rounded-full bg-[rgba(47,158,68,.12)] px-2 py-0.5 text-[11px] font-medium text-[color:var(--nx-success)]">
                    @svg('heroicon-o-check', 'w-3.5 h-3.5') aktiv ({{ $payMode === 'live' ? 'Live' : 'Test' }})
                </span>
            @else
                <span class="ml-auto text-[11px] text-[color:var(--nx-muted)]">nicht aktiv – Checkout im Demo-Modus</span>
            @endif
        </div>
        <div class="p-5 space-y-4">
            <label class="flex items-center gap-2 text-sm text-[color:var(--nx-text)] cursor-pointer">
                <input wire:model="payEnabled" type="checkbox" class="rounded-[4px] accent-[var(--nx-accent)]" />
                Mollie-Zahlungen aktivieren
            </label>

            <x-nx-input-select
                name="payMode"
                label="Modus"
                :options="[
                    ['value' => 'test', 'label' => 'Test (Sandbox)'],
                    ['value' => 'live', 'label' => 'Live (echte Zahlungen)'],
                ]"
                wire:model="payMode"
            />

            <x-nx-input-text
                type="password"
                name="testApiKey"
                label="Test-API-Key"
                wire:model="testApiKey"
                :placeholder="$testKeySet ? '•••••••• (gespeichert – zum Ändern neuen Key eingeben)' : 'test_...'"
                autocomplete="off"
            />
            <x-nx-input-text
                type="password"
                name="liveApiKey"
                label="Live-API-Key"
                wire:model="liveApiKey"
                :placeholder="$liveKeySet ? '•••••••• (gespeichert – zum Ändern neuen Key eingeben)' : 'live_...'"
                autocomplete="off"
            />

            <div>
                <p class="m-0 text-[12px] font-medium text-[color:var(--nx-muted)]">Webhook-URL (im Mollie-Dashboard)</p>
                <code class="mt-1 block break-all rounded-[8px] bg-[color:var(--nx-bg)] px-3 py-2 text-xs text-[color:var(--nx-text)]">{{ $webhookUrl }}</code>
                <p class="mt-1 text-[11px] text-[color:var(--nx-muted)]">Muss öffentlich erreichbar sein (auf localhost erhält Mollie keinen Callback).</p>
            </div>
        </div>
    </x-nx-card>

    {{-- Gast-Checkout --}}
    <x-nx-card flush>
        <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
            @svg('heroicon-o-document-text', 'w-4 h-4 text-[color:var(--nx-muted)]')
            <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Texte im Gast-Checkout</h2>
        </div>
        <div class="p-5 space-y-5">
            <div>
                <x-nx-input-textarea
                    name="ageCheckText"
                    label="18+-Hinweis (erscheint nur bei alkoholischen Artikeln)"
                    wire:model="ageCheckText"
                    rows="3"
                    :placeholder="$defaultAge"
                />
                <p class="mt-1 text-[11px] text-[color:var(--nx-muted)]">Leer lassen = Standardtext wird verwendet.</p>
            </div>

            <div>
                <x-nx-input-textarea
                    name="legalText"
                    label="Pflicht-Bestätigung (Checkbox vor dem Bezahlen)"
                    wire:model="legalText"
                    rows="3"
                    :placeholder="$defaultLegal"
                />
                <p class="mt-1 text-[11px] text-[color:var(--nx-muted)]">Leer lassen = Standardtext wird verwendet.</p>
            </div>

            <x-nx-input-text
                type="url"
                name="privacyUrl"
                label="Link zur Datenschutzerklärung (optional)"
                wire:model="privacyUrl"
                placeholder="https://…"
            />
        </div>
    </x-nx-card>

    <div class="flex justify-end">
        <x-nx-button variant="primary" size="sm" wire:click="save">Speichern</x-nx-button>
    </div>

    </div>
    </x-ui-page-container>
</x-ui-page>
