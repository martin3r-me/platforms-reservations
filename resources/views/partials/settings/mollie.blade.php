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
