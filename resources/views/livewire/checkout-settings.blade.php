<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Einstellungen" icon="heroicon-o-cog-6-tooth" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Einstellungen'],
        ]">
            <x-ui-button variant="primary" size="sm" wire:click="save">Speichern</x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
    <div class="pt-4 space-y-4 max-w-2xl">

    @if (session('checkout_message'))
        <div class="rounded-lg border border-[var(--ui-success)]/30 bg-[var(--ui-success-10)] p-3 text-sm text-[var(--ui-success)]">
            {{ session('checkout_message') }}
        </div>
    @endif

    {{-- Termine --}}
    <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
            @svg('heroicon-o-ticket', 'w-4 h-4 text-[var(--ui-muted)]')
            <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Termine</h2>
        </div>
        <div class="p-5">
            <x-ui-input-select
                name="defaultRoomReleaseMode"
                label="Standard-Raumfreigabe (Vorauswahl bei neuen Terminen)"
                :options="[
                    ['value' => 'parallel', 'label' => 'Parallel (alle Räume offen)'],
                    ['value' => 'sequential', 'label' => 'Sequentiell (Raum 2 nach Füllung von Raum 1)'],
                ]"
                wire:model="defaultRoomReleaseMode"
            />
            <p class="mt-1 text-[11px] text-[var(--ui-muted)]">Beim Anlegen eines Termins kann die Freigabe weiterhin einzeln geändert werden.</p>

            <label class="mt-4 flex items-start gap-2 text-sm text-[var(--ui-secondary)] cursor-pointer">
                <input wire:model.live="softTableCapacity" type="checkbox" class="mt-0.5 rounded border-[var(--ui-border)]" />
                <span>
                    Weiche Tisch-Kapazität (Großgruppen)
                    <span class="block text-[11px] text-[var(--ui-muted)]">Eine Gruppe, die nicht in die freien Plätze passt, darf einen <strong>komplett leeren</strong> Tisch über die Platzzahl hinaus belegen (z. B. Stehtische). Teilbelegte Tische bleiben für zu große Gruppen gesperrt.</span>
                </span>
            </label>

            @if ($softTableCapacity)
                <div class="mt-3 ml-6 max-w-xs">
                    <x-ui-input-text type="number" name="maxGroupEmptyTable" label="Max. Gruppe auf leerem Tisch (leer = unbegrenzt)" size="sm" wire:model="maxGroupEmptyTable" placeholder="z. B. 12" errorKey="maxGroupEmptyTable" />
                    <p class="mt-1 text-[11px] text-[var(--ui-muted)]">Deckelt, wie viele Personen einen leeren Tisch über die Platzzahl hinaus belegen dürfen.</p>
                </div>
            @endif
        </div>
    </section>

    {{-- Anmeldefelder (Gast-Checkout) --}}
    <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
            @svg('heroicon-o-identification', 'w-4 h-4 text-[var(--ui-muted)]')
            <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Anmeldefelder im Gast-Checkout</h2>
        </div>
        <div class="p-5 space-y-4">
            <p class="text-[11px] text-[var(--ui-muted)] m-0">Steuert je Feld, ob es im Gast-Checkout abgefragt wird. <strong>Name</strong> und <strong>Personenzahl</strong> sind immer Pflicht.</p>
            @php
                $fieldModeOptions = [
                    ['value' => 'required', 'label' => 'Pflicht'],
                    ['value' => 'optional', 'label' => 'Optional'],
                    ['value' => 'hidden',   'label' => 'Ausgeblendet'],
                ];
            @endphp
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-ui-input-select name="fieldEmail" label="E-Mail" :options="$fieldModeOptions" wire:model="fieldEmail" />
                <x-ui-input-select name="fieldPhone" label="Rufnummer" :options="$fieldModeOptions" wire:model="fieldPhone" />
                <x-ui-input-select name="fieldNotes" label="Anmerkungen" :options="$fieldModeOptions" wire:model="fieldNotes" />
            </div>
            <p class="text-[11px] text-[var(--ui-muted)] m-0">Hinweis: Wird die E-Mail ausgeblendet oder optional gesetzt, kann für diese Bestellung keine automatische Bestätigungs-E-Mail versendet werden.</p>
        </div>
    </section>

    {{-- Shop-Sprachen (#522) --}}
    <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
            @svg('heroicon-o-language', 'w-4 h-4 text-[var(--ui-muted)]')
            <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Shop-Sprachen</h2>
        </div>
        <div class="p-5">
            <x-ui-input-text name="languagesCsv" label="Zusätzliche Sprachen (Codes, kommagetrennt)" size="sm" wire:model="languagesCsv" placeholder="en, fr" errorKey="languagesCsv" />
            <p class="mt-1 text-[11px] text-[var(--ui-muted)]"><strong>Deutsch</strong> ist Basis-/Standardsprache und immer aktiv. Zusätzliche Sprachen z. B. <code>en, fr</code>. Übersetzungen der Speisen, Kategorien, Allergene und Checkout-Texte pflegst du je Objekt (auch per MCP); fehlt eine Übersetzung, wird Deutsch angezeigt.</p>

            <div class="mt-4">
                <x-ui-input-text name="guestFrontendUrl" label="Shop-Frontend-URL (für Zahlungs-Rücksprung)" size="sm" wire:model="guestFrontendUrl" placeholder="https://culinaria.pauseplus.de" errorKey="guestFrontendUrl" />
                <p class="mt-1 text-[11px] text-[var(--ui-muted)]">Basis-URL des externen Shops. Nach der Zahlung darf Mollie nur auf eine <code>redirect_url</code> mit <strong>diesem Origin</strong> zurückspringen (Schutz vor offenen Weiterleitungen). Ohne Eintrag wird eine vom Frontend übergebene Rücksprung-URL abgelehnt und die In-App-Seite genutzt.</p>
            </div>
        </div>
    </section>

    {{-- Rechnungsangaben (Aussteller) --}}
    <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
            @svg('heroicon-o-building-office-2', 'w-4 h-4 text-[var(--ui-muted)]')
            <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Rechnungsangaben (Aussteller)</h2>
        </div>
        <div class="p-5 space-y-3">
            <p class="text-[11px] text-[var(--ui-muted)] m-0">Diese Firmendaten erscheinen auf Beleg und Bewirtungsbeleg (USt-IdNr/Steuernummer nach Bedarf).</p>
            <x-ui-input-text name="issuer.name" label="Firmenname" size="sm" wire:model="issuer.name" placeholder="Musterkatering GmbH" />
            <x-ui-form-grid :cols="3" :gap="3">
                <div class="sm:col-span-2"><x-ui-input-text name="issuer.street" label="Straße & Nr." size="sm" wire:model="issuer.street" /></div>
                <x-ui-input-text name="issuer.zip" label="PLZ" size="sm" wire:model="issuer.zip" />
            </x-ui-form-grid>
            <x-ui-form-grid :cols="3" :gap="3">
                <div class="sm:col-span-2"><x-ui-input-text name="issuer.city" label="Ort" size="sm" wire:model="issuer.city" /></div>
                <x-ui-input-text name="issuer.country" label="Land" size="sm" wire:model="issuer.country" placeholder="DE" />
            </x-ui-form-grid>
            <x-ui-form-grid :cols="2" :gap="3">
                <x-ui-input-text name="issuer.vat_id" label="USt-IdNr" size="sm" wire:model="issuer.vat_id" placeholder="DE123456789" />
                <x-ui-input-text name="issuer.tax_number" label="Steuernummer" size="sm" wire:model="issuer.tax_number" />
            </x-ui-form-grid>
            <x-ui-form-grid :cols="3" :gap="3">
                <x-ui-input-text name="issuer.register_court" label="Registergericht" size="sm" wire:model="issuer.register_court" placeholder="Amtsgericht Wuppertal" />
                <x-ui-input-text name="issuer.register_number" label="HRB-Nr." size="sm" wire:model="issuer.register_number" placeholder="8727" />
                <x-ui-input-text name="issuer.managing_directors" label="Vertreten durch" size="sm" wire:model="issuer.managing_directors" placeholder="Max Muster & …" />
            </x-ui-form-grid>
            <x-ui-form-grid :cols="4" :gap="3">
                <x-ui-input-text name="issuer.email" label="E-Mail" size="sm" wire:model="issuer.email" errorKey="issuer.email" />
                <x-ui-input-text name="issuer.phone" label="Telefon" size="sm" wire:model="issuer.phone" />
                <x-ui-input-text name="issuer.fax" label="Telefax" size="sm" wire:model="issuer.fax" />
                <x-ui-input-text name="issuer.website" label="Website" size="sm" wire:model="issuer.website" />
            </x-ui-form-grid>
        </div>
    </section>

    {{-- Bestellbestätigung (E-Mail-Absender) --}}
    <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
            @svg('heroicon-o-envelope', 'w-4 h-4 text-[var(--ui-muted)]')
            <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Bestellbestätigung (E-Mail)</h2>
        </div>
        <div class="p-5">
            @if (count($emailChannels))
                <x-ui-input-select
                    name="confirmationChannelId"
                    label="Absender für Bestellbestätigungen"
                    :options="$emailChannels"
                    :nullable="true"
                    nullLabel="— kein Versand —"
                    wire:model="confirmationChannelId"
                />
                <p class="mt-1 text-[11px] text-[var(--ui-muted)]">Wähle den Postmark-Absender (aus dem CRM), über den die „Vielen Dank für Ihre Bestellung"-Mail verschickt wird. <strong>Ohne Auswahl wird keine Bestätigung versendet</strong> (kein Standard-Absender).</p>
            @else
                <p class="text-[11px] text-[var(--ui-muted)] m-0">Es sind keine aktiven Postmark-E-Mail-Absender im CRM vorhanden. Lege zuerst im CRM einen E-Mail-Channel (Provider Postmark) an – dann kannst du ihn hier auswählen.</p>
            @endif
        </div>
    </section>

    {{-- Selbst-Storno --}}
    <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
            @svg('heroicon-o-arrow-uturn-left', 'w-4 h-4 text-[var(--ui-muted)]')
            <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Stornierung durch Kunden</h2>
        </div>
        <div class="p-5 space-y-4">
            <label class="flex items-start gap-2 text-sm text-[var(--ui-secondary)] cursor-pointer">
                <input wire:model.live="cancellationEnabled" type="checkbox" class="mt-0.5 rounded border-[var(--ui-border)]" />
                <span>
                    Selbst-Storno erlauben
                    <span class="block text-[11px] text-[var(--ui-muted)]">Kunden erhalten in der Bestätigungs-Mail einen Storno-Link. Innerhalb der Frist wird die Bestellung storniert und die Zahlung über Mollie erstattet.</span>
                </span>
            </label>

            @if ($cancellationEnabled)
                <div class="ml-6 max-w-xs">
                    <x-ui-input-text type="number" name="cancellationDeadlineHours" label="Frist: Stunden vor Veranstaltung" size="sm" wire:model="cancellationDeadlineHours" placeholder="z. B. 72" errorKey="cancellationDeadlineHours" />
                    <p class="mt-1 text-[11px] text-[var(--ui-muted)]">Bis wie viele Stunden vor dem Veranstaltungsdatum ein Storno möglich ist. Leer = keine Frist.</p>
                </div>
                <label class="ml-6 flex items-start gap-2 text-sm text-[var(--ui-secondary)] cursor-pointer">
                    <input wire:model="cancellationRequiresApproval" type="checkbox" class="mt-0.5 rounded border-[var(--ui-border)]" />
                    <span>
                        Storno erst nach Freigabe
                        <span class="block text-[11px] text-[var(--ui-muted)]">Standard: aus – der Klick storniert sofort und löst die Rückerstattung aus. Aktiv: der Kunde fragt nur an, das Team gibt frei (dann erst Rückerstattung).</span>
                    </span>
                </label>
            @endif
        </div>
    </section>

    {{-- Zahlung (Mollie) --}}
    <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
            @svg('heroicon-o-credit-card', 'w-4 h-4 text-[var(--ui-muted)]')
            <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Zahlung (Mollie)</h2>
            @if ($payReady)
                <span class="ml-auto inline-flex items-center gap-1 rounded-full bg-[var(--ui-success-10)] px-2 py-0.5 text-[11px] font-medium text-[var(--ui-success)]">
                    @svg('heroicon-o-check', 'w-3.5 h-3.5') aktiv ({{ $payMode === 'live' ? 'Live' : 'Test' }})
                </span>
            @else
                <span class="ml-auto text-[11px] text-[var(--ui-muted)]">nicht aktiv – Checkout im Demo-Modus</span>
            @endif
        </div>
        <div class="p-5 space-y-4">
            <label class="flex items-center gap-2 text-sm text-[var(--ui-secondary)] cursor-pointer">
                <input wire:model="payEnabled" type="checkbox" class="rounded border-[var(--ui-border)]" />
                Mollie-Zahlungen aktivieren
            </label>

            <x-ui-input-select
                name="payMode"
                label="Modus"
                :options="[
                    ['value' => 'test', 'label' => 'Test (Sandbox)'],
                    ['value' => 'live', 'label' => 'Live (echte Zahlungen)'],
                ]"
                wire:model="payMode"
            />

            <x-ui-input-text
                type="password"
                name="testApiKey"
                label="Test-API-Key"
                wire:model="testApiKey"
                :placeholder="$testKeySet ? '•••••••• (gespeichert – zum Ändern neuen Key eingeben)' : 'test_...'"
                autocomplete="off"
            />
            <x-ui-input-text
                type="password"
                name="liveApiKey"
                label="Live-API-Key"
                wire:model="liveApiKey"
                :placeholder="$liveKeySet ? '•••••••• (gespeichert – zum Ändern neuen Key eingeben)' : 'live_...'"
                autocomplete="off"
            />

            <div>
                <p class="m-0 text-[12px] font-medium text-[var(--ui-muted)]">Webhook-URL (im Mollie-Dashboard)</p>
                <code class="mt-1 block break-all rounded-lg bg-[var(--ui-muted-5)] px-3 py-2 text-xs text-[var(--ui-secondary)]">{{ $webhookUrl }}</code>
                <p class="mt-1 text-[11px] text-[var(--ui-muted)]">Muss öffentlich erreichbar sein (auf localhost erhält Mollie keinen Callback).</p>
            </div>
        </div>
    </section>

    {{-- Gast-Checkout --}}
    <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
            @svg('heroicon-o-document-text', 'w-4 h-4 text-[var(--ui-muted)]')
            <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Texte im Gast-Checkout</h2>
        </div>
        <div class="p-5 space-y-5">
            <div>
                <x-ui-input-textarea
                    name="ageCheckText"
                    label="18+-Hinweis (erscheint nur bei alkoholischen Artikeln)"
                    wire:model="ageCheckText"
                    rows="3"
                    :placeholder="$defaultAge"
                />
                <p class="mt-1 text-[11px] text-[var(--ui-muted)]">Leer lassen = Standardtext wird verwendet.</p>
            </div>

            <div>
                <x-ui-input-textarea
                    name="legalText"
                    label="Pflicht-Bestätigung (Checkbox vor dem Bezahlen)"
                    wire:model="legalText"
                    rows="3"
                    :placeholder="$defaultLegal"
                />
                <p class="mt-1 text-[11px] text-[var(--ui-muted)]">Leer lassen = Standardtext wird verwendet.</p>
            </div>

            <x-ui-input-text
                type="url"
                name="privacyUrl"
                label="Link zur Datenschutzerklärung (optional)"
                wire:model="privacyUrl"
                placeholder="https://…"
            />
        </div>
    </section>

    <div class="flex justify-end">
        <x-ui-button variant="primary" size="sm" wire:click="save">Speichern</x-ui-button>
    </div>

    </div>
    </x-ui-page-container>
</x-ui-page>
