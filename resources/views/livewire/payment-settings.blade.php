<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Zahlungen" icon="heroicon-o-credit-card" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Zahlungen'],
        ]" />
    </x-slot>

    <x-ui-page-container>
    <div class="pt-4 space-y-4 max-w-2xl">

    @if (session('payment_message'))
        <div class="rounded-lg border border-[var(--ui-success)]/30 bg-[var(--ui-success-10)] p-3 text-sm text-[var(--ui-success)]">
            {{ session('payment_message') }}
        </div>
    @endif

    {{-- Status --}}
    <div class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm p-4 flex items-center gap-3">
        @if ($isReady)
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--ui-success-10)] text-[var(--ui-success)]">
                @svg('heroicon-o-check', 'w-5 h-5')
            </span>
            <div>
                <p class="m-0 text-sm font-semibold text-[var(--ui-secondary)]">Mollie ist aktiv ({{ $mode === 'live' ? 'Live' : 'Test' }})</p>
                <p class="m-0 text-xs text-[var(--ui-muted)]">Gäste zahlen im Checkout über Mollie. Buchungen werden erst nach Zahlungseingang bestätigt.</p>
            </div>
        @else
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--ui-muted-10)] text-[var(--ui-muted)]">
                @svg('heroicon-o-pause', 'w-5 h-5')
            </span>
            <div>
                <p class="m-0 text-sm font-semibold text-[var(--ui-secondary)]">Mollie ist nicht aktiv</p>
                <p class="m-0 text-xs text-[var(--ui-muted)]">Ohne aktiven Schlüssel läuft der Checkout im Demo-Modus (ohne echte Zahlung).</p>
            </div>
        @endif
    </div>

    <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
            @svg('heroicon-o-credit-card', 'w-4 h-4 text-[var(--ui-muted)]')
            <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Mollie</h2>
        </div>
        <div class="p-5 space-y-4">
            <label class="flex items-center gap-2 text-sm text-[var(--ui-secondary)] cursor-pointer">
                <input wire:model="enabled" type="checkbox" class="rounded border-[var(--ui-border)]" />
                Mollie-Zahlungen aktivieren
            </label>

            <x-ui-input-select
                name="mode"
                label="Modus"
                :options="[
                    ['value' => 'test', 'label' => 'Test (Sandbox)'],
                    ['value' => 'live', 'label' => 'Live (echte Zahlungen)'],
                ]"
                wire:model="mode"
            />

            <div>
                <x-ui-input-text
                    type="password"
                    name="testApiKey"
                    label="Test-API-Key"
                    wire:model="testApiKey"
                    :placeholder="$testKeySet ? '•••••••• (gespeichert – zum Ändern neuen Key eingeben)' : 'test_...'"
                    autocomplete="off"
                />
            </div>

            <div>
                <x-ui-input-text
                    type="password"
                    name="liveApiKey"
                    label="Live-API-Key"
                    wire:model="liveApiKey"
                    :placeholder="$liveKeySet ? '•••••••• (gespeichert – zum Ändern neuen Key eingeben)' : 'live_...'"
                    autocomplete="off"
                />
            </div>

            <div class="flex justify-end">
                <x-ui-button variant="primary" size="sm" wire:click="save">Speichern</x-ui-button>
            </div>
        </div>
    </section>

    {{-- Webhook-Hinweis --}}
    <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm p-4">
        <h3 class="m-0 text-sm font-semibold text-[var(--ui-secondary)]">Webhook-URL</h3>
        <p class="mt-1 text-xs text-[var(--ui-muted)]">
            Diese URL ist im Mollie-Dashboard hinterlegt bzw. wird automatisch je Zahlung übergeben –
            Mollie meldet darüber den Zahlungsstatus zurück.
        </p>
        <code class="mt-2 block break-all rounded-lg bg-[var(--ui-muted-5)] px-3 py-2 text-xs text-[var(--ui-secondary)]">{{ $webhookUrl }}</code>
        <p class="mt-2 text-[11px] text-[var(--ui-muted)]">
            Hinweis: Für echte Webhooks muss die Seite öffentlich erreichbar sein (auf localhost erhält Mollie keinen Callback).
        </p>
    </section>

    </div>
    </x-ui-page-container>
</x-ui-page>
