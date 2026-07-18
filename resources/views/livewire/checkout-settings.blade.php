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
