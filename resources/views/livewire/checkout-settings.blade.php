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
