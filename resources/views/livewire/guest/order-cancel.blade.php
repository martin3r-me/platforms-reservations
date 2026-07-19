<div class="min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="mx-auto max-w-lg px-4 py-12">
        @php
            $order = $this->order;
            $currency = strtoupper((string) config('reservation.currency', 'EUR'));
            $fmt = fn ($v) => number_format((float) $v, 2, ',', '.') . ' ' . ($currency === 'EUR' ? '€' : $currency);
        @endphp

        @if (!$order)
            <div class="rounded-2xl border border-dashed border-gray-300 bg-white py-16 text-center dark:border-gray-700 dark:bg-gray-900">
                <div class="text-5xl mb-4">🔍</div>
                <h1 class="text-lg font-semibold dark:text-white">Bestellung nicht gefunden</h1>
                <a href="{{ route('reservation.guest.events.index') }}" class="mt-4 inline-block text-sm text-[var(--ui-primary)] hover:underline">Zur Terminübersicht</a>
            </div>

        {{-- Ergebnis nach Aktion --}}
        @elseif ($resultStatus === 'cancelled')
            <div class="rounded-2xl border border-[var(--ui-border)]/40 bg-white p-8 text-center shadow-sm dark:bg-gray-900">
                <div class="text-5xl mb-4">✅</div>
                <h1 class="text-xl font-bold text-[var(--ui-secondary)] m-0">Bestellung storniert</h1>
                <p class="mt-2 text-sm text-[var(--ui-muted)]">{{ $resultMessage }} Eine etwaige Zahlung wird erstattet.</p>
            </div>
        @elseif ($resultStatus === 'requested')
            <div class="rounded-2xl border border-[var(--ui-border)]/40 bg-white p-8 text-center shadow-sm dark:bg-gray-900">
                <div class="text-5xl mb-4">⏳</div>
                <h1 class="text-xl font-bold text-[var(--ui-secondary)] m-0">Storno angefragt</h1>
                <p class="mt-2 text-sm text-[var(--ui-muted)]">{{ $resultMessage }}</p>
            </div>
        @elseif (in_array($resultStatus, ['error', 'not_cancellable', 'already_cancelled'], true))
            <div class="rounded-2xl border border-[var(--ui-border)]/40 bg-white p-8 text-center shadow-sm dark:bg-gray-900">
                <div class="text-5xl mb-4">🚫</div>
                <h1 class="text-xl font-bold text-[var(--ui-secondary)] m-0">Storno nicht möglich</h1>
                <p class="mt-2 text-sm text-[var(--ui-muted)]">{{ $resultMessage }}</p>
            </div>

        {{-- Storno-Abfrage --}}
        @else
            <div class="rounded-2xl border border-[var(--ui-border)]/40 bg-white p-8 shadow-sm dark:bg-gray-900">
                <h1 class="text-xl font-bold text-[var(--ui-secondary)] m-0">Bestellung stornieren</h1>
                <p class="mt-1 text-sm text-[var(--ui-muted)]">
                    {{ $order->event?->name }}
                    @if ($order->bookings->first()?->date) · {{ $order->bookings->first()->date->format('d.m.Y') }} @endif
                </p>

                <div class="mt-4 rounded-xl bg-[var(--ui-muted-5)] p-4 text-sm">
                    <div class="flex justify-between"><span class="text-[var(--ui-muted)]">Bestellnr.</span><span class="font-mono text-xs">{{ $order->uuid }}</span></div>
                    <div class="mt-1 flex justify-between"><span class="text-[var(--ui-muted)]">Betrag</span><span class="font-semibold">{{ $fmt($order->total_amount) }}</span></div>
                    <div class="mt-1 flex justify-between"><span class="text-[var(--ui-muted)]">Status</span><span>{{ $order->status }}</span></div>
                </div>

                @if ($this->cancellable)
                    <p class="mt-4 text-sm text-[var(--ui-secondary)]">
                        Möchten Sie diese Bestellung wirklich stornieren?
                        @if ($this->deadline)
                            <br><span class="text-xs text-[var(--ui-muted)]">Storno möglich bis {{ $this->deadline->format('d.m.Y H:i') }} Uhr.</span>
                        @endif
                        @if ($this->settings->cancellationRequiresApproval())
                            <br><span class="text-xs text-[var(--ui-muted)]">Der Storno wird geprüft; die Rückerstattung erfolgt nach Freigabe.</span>
                        @endif
                    </p>
                    <button wire:click="confirmCancel" wire:loading.attr="disabled"
                        class="mt-5 w-full rounded-xl bg-[var(--ui-danger)] py-3 text-base font-bold text-white hover:opacity-90 disabled:opacity-60">
                        <span wire:loading.remove wire:target="confirmCancel">Jetzt stornieren</span>
                        <span wire:loading wire:target="confirmCancel">Wird storniert …</span>
                    </button>
                    <a href="{{ route('reservation.guest.events.index') }}" class="mt-3 block text-center text-xs text-[var(--ui-muted)] hover:underline">Doch nicht stornieren</a>
                @else
                    <div class="mt-4 rounded-xl border border-[var(--ui-border)]/40 p-4 text-sm text-[var(--ui-muted)]">
                        Ein Storno ist für diese Bestellung nicht (mehr) möglich
                        @if ($this->deadline) – die Frist endete am {{ $this->deadline->format('d.m.Y H:i') }} Uhr @endif.
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
