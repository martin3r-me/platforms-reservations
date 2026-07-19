<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Storno-Anfragen" icon="heroicon-o-arrow-uturn-left" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Storno-Anfragen'],
        ]" />
    </x-slot>

    <x-ui-page-container>
    <div class="pt-4 space-y-4">
        @php $currency = strtoupper((string) config('reservation.currency', 'EUR')); $sym = $currency === 'EUR' ? '€' : $currency; @endphp

        @if (session('cancel_message'))
            <div class="rounded-lg border border-[var(--ui-success)]/30 bg-[var(--ui-success-10)] p-3 text-sm text-[var(--ui-success)]">
                {{ session('cancel_message') }}
            </div>
        @endif

        <p class="text-sm text-[var(--ui-muted)] m-0">
            Vom Kunden angefragte Stornierungen (nur im Freigabe-Modus). <strong>Freigeben</strong> storniert die Bestellung und löst die Mollie-Rückerstattung aus; <strong>Ablehnen</strong> lässt die Bestellung bestätigt.
        </p>

        <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
            <div class="divide-y divide-[var(--ui-border)]/30">
                @forelse ($this->requests as $order)
                    <div wire:key="cxl-{{ $order->id }}" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-[var(--ui-secondary)]">{{ $order->customerName() ?: '—' }}</span>
                                <span class="font-mono text-[10px] text-[var(--ui-muted)]">{{ \Illuminate\Support\Str::limit($order->uuid, 13, '…') }}</span>
                            </div>
                            <p class="m-0 mt-0.5 text-xs text-[var(--ui-muted)]">
                                @if ($order->event) {{ $order->event->name }} · @endif
                                {{ number_format((float) $order->total_amount, 2, ',', '.') }} {{ $sym }}
                                · {{ $order->bookings->count() }} {{ $order->bookings->count() === 1 ? 'Pause' : 'Pausen' }}
                                @if ($order->cancellation_requested_at) · angefragt {{ $order->cancellation_requested_at->format('d.m.Y H:i') }} @endif
                                @if ($order->payment) · Zahlung: {{ $order->payment->status }} @endif
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <x-ui-confirm-button
                                action="approve"
                                :value="$order->id"
                                text="Freigeben &amp; erstatten"
                                confirmText="Storno freigeben und Zahlung erstatten?"
                                variant="danger"
                                size="sm" />
                            <x-ui-button variant="secondary-ghost" size="sm" wire:click="reject({{ $order->id }})" wire:confirm="Storno-Anfrage ablehnen? Die Bestellung bleibt bestätigt.">
                                Ablehnen
                            </x-ui-button>
                        </div>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center py-12 text-[var(--ui-muted)]">
                        @svg('heroicon-o-check-circle', 'w-8 h-8 mb-2 opacity-40')
                        <span class="text-sm">Keine offenen Storno-Anfragen.</span>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
    </x-ui-page-container>
</x-ui-page>
