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

    <x-ui-page-container width="contained">
    <div class="space-y-5">
        @php $currency = strtoupper((string) config('reservation.currency', 'EUR')); $sym = $currency === 'EUR' ? '€' : $currency; @endphp

        @if (session('cancel_message'))
            <x-nx-callout variant="success">{{ session('cancel_message') }}</x-nx-callout>
        @endif

        <p class="m-0 text-sm text-[color:var(--nx-muted)]">
            Vom Kunden angefragte Stornierungen (nur im Freigabe-Modus). <strong class="text-[color:var(--nx-text)]">Freigeben</strong> storniert die Bestellung und löst die Mollie-Rückerstattung aus; <strong class="text-[color:var(--nx-text)]">Ablehnen</strong> lässt die Bestellung bestätigt.
        </p>

        <x-nx-card flush>
            <div>
                @forelse ($this->requests as $order)
                    <div wire:key="cxl-{{ $order->id }}" class="flex flex-wrap items-center justify-between gap-3 border-b border-[color:var(--nx-line)] px-4 py-3 last:border-0">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-[color:var(--nx-text)]">{{ $order->customerName() ?: '—' }}</span>
                                <span class="font-mono text-[10px] text-[color:var(--nx-faint)]">{{ \Illuminate\Support\Str::limit($order->uuid, 13, '…') }}</span>
                            </div>
                            <p class="m-0 mt-0.5 text-xs text-[color:var(--nx-muted)]">
                                @if ($order->event) {{ $order->event->name }} · @endif
                                {{ number_format((float) $order->total_amount, 2, ',', '.') }} {{ $sym }}
                                · {{ $order->bookings->count() }} {{ $order->bookings->count() === 1 ? 'Pause' : 'Pausen' }}
                                @if ($order->cancellation_requested_at) · angefragt {{ $order->cancellation_requested_at->format('d.m.Y H:i') }} @endif
                                @if ($order->payment) · Zahlung: {{ $order->payment->status }} @endif
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <x-nx-button variant="danger" wire:click="approve({{ $order->id }})" wire:confirm="Storno freigeben und Zahlung erstatten?">Freigeben &amp; erstatten</x-nx-button>
                            <x-nx-button variant="ghost" wire:click="reject({{ $order->id }})" wire:confirm="Storno-Anfrage ablehnen? Die Bestellung bleibt bestätigt.">Ablehnen</x-nx-button>
                        </div>
                    </div>
                @empty
                    <x-nx-empty icon="heroicon-o-check-circle">Keine offenen Storno-Anfragen.</x-nx-empty>
                @endforelse
            </div>
        </x-nx-card>
    </div>
    </x-ui-page-container>
</x-ui-page>
