<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Posteingang" icon="heroicon-o-inbox" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Posteingang'],
        ]" />
    </x-slot>

    <x-ui-page-container>
    <div class="pt-4 space-y-4">
        @php
            $currency = strtoupper((string) config('reservation.currency', 'EUR'));
            $sym = $currency === 'EUR' ? '€' : $currency;
            $typeMap = [
                'new'                     => ['Neue Bestellung', 'success', 'heroicon-o-shopping-bag'],
                'cancellation_requested'  => ['Storno-Anfrage', 'warning', 'heroicon-o-question-mark-circle'],
                'cancelled'               => ['Storniert', 'danger', 'heroicon-o-x-circle'],
                'payment_failed'          => ['Zahlung fehlgeschlagen', 'danger', 'heroicon-o-exclamation-triangle'],
            ];
        @endphp

        @if (session('inbox_message'))
            <div class="rounded-lg border border-[var(--ui-success)]/30 bg-[var(--ui-success-10)] p-3 text-sm text-[var(--ui-success)]">{{ session('inbox_message') }}</div>
        @endif

        <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
            {{-- Karten-Header --}}
            <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
                @svg('heroicon-o-inbox', 'w-4 h-4 text-[var(--ui-muted)]')
                <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Posteingang</h2>
                <span class="ml-auto text-[11px] text-[var(--ui-muted)]">{{ $this->entries->count() }}</span>
            </div>

            {{-- Filter + Bulk --}}
            <div class="flex flex-wrap items-center gap-1.5 border-b border-[var(--ui-border)]/30 px-4 py-2 text-[11px]">
                <span class="text-[var(--ui-muted)]">Filter:</span>
                <button type="button" wire:click="$set('unseenOnly', true)"
                    class="rounded-full px-2.5 py-0.5 transition-colors {{ $unseenOnly ? 'bg-[var(--ui-primary)] font-medium text-white' : 'text-[var(--ui-muted)] hover:bg-[var(--ui-muted-5)]' }}">Ungesehen ({{ $this->unseenCount }})</button>
                <button type="button" wire:click="$set('unseenOnly', false)"
                    class="rounded-full px-2.5 py-0.5 transition-colors {{ !$unseenOnly ? 'bg-[var(--ui-primary)] font-medium text-white' : 'text-[var(--ui-muted)] hover:bg-[var(--ui-muted-5)]' }}">Alle</button>

                <div class="ml-auto flex items-center gap-2">
                    @if (count($selected))
                        <span class="text-[var(--ui-muted)]">{{ count($selected) }} ausgewählt</span>
                        <button type="button" wire:click="markSelectedSeen" class="rounded-full bg-[var(--ui-primary)] px-2.5 py-0.5 font-medium text-white">Ausgewählte als gesehen</button>
                    @endif
                    @if ($this->unseenCount > 0)
                        <button type="button" wire:click="markAllSeen" wire:confirm="Alle als gesehen markieren?" class="rounded-full border border-[var(--ui-border)] px-2.5 py-0.5 text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]">Alle als gesehen</button>
                    @endif
                </div>
            </div>

            {{-- Zeilen --}}
            <div class="divide-y divide-[var(--ui-border)]/30">
                @forelse ($this->entries as $order)
                    @php [$label, $variant, $icon] = $typeMap[$order->inboxType()] ?? ['Vorgang', 'muted', 'heroicon-o-bell']; @endphp
                    <div wire:key="inbox-{{ $order->id }}" class="flex flex-wrap items-center gap-3 px-4 py-3 {{ $order->seen_at ? '' : 'bg-[var(--ui-primary-10)]/40' }}">
                        <input type="checkbox" wire:model.live="selected" value="{{ $order->id }}" class="rounded border-[var(--ui-border)]" />

                        <div class="w-40 shrink-0">
                            <x-ui-badge :variant="$variant" size="xs">{{ $label }}</x-ui-badge>
                        </div>

                        <div class="min-w-0 flex-1">
                            <span class="text-sm font-medium text-[var(--ui-secondary)]">{{ $order->customerName() ?: '—' }}</span>
                            @if (!$order->seen_at)<span class="ml-1 inline-block h-2 w-2 rounded-full bg-[var(--ui-primary)]" title="ungesehen"></span>@endif
                            <p class="m-0 mt-0.5 text-xs text-[var(--ui-muted)]">
                                @if ($order->event){{ $order->event->name }} · @endif
                                {{ number_format((float) $order->total_amount, 2, ',', '.') }} {{ $sym }}
                                · {{ $order->bookings->count() }} {{ $order->bookings->count() === 1 ? 'Pause' : 'Pausen' }}
                                @if ($order->payment) · Zahlung: {{ $order->payment->status }} @endif
                                · {{ $order->updated_at?->diffForHumans() }}
                            </p>
                        </div>

                        <div class="flex shrink-0 flex-wrap items-center justify-end gap-1.5">
                            @if ($order->inboxType() === 'cancellation_requested')
                                <x-ui-button variant="danger" size="sm" wire:click="approveCancellation({{ $order->id }})" wire:confirm="Storno freigeben und Zahlung erstatten?">Freigeben &amp; erstatten</x-ui-button>
                                <x-ui-button variant="secondary-ghost" size="sm" wire:click="rejectCancellation({{ $order->id }})" wire:confirm="Storno-Anfrage ablehnen?">Ablehnen</x-ui-button>
                            @endif
                            @if (!$order->seen_at)
                                <x-ui-button variant="secondary-outline" size="sm" wire:click="markSeen({{ $order->id }})">Gesehen</x-ui-button>
                            @else
                                <span class="text-[11px] text-[var(--ui-muted)]">gesehen {{ $order->seen_at->format('d.m. H:i') }}</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center py-12 text-[var(--ui-muted)]">
                        @svg('heroicon-o-check-circle', 'w-8 h-8 mb-2 opacity-40')
                        <span class="text-sm">{{ $unseenOnly ? 'Alles gesehen – nichts Neues im Posteingang.' : 'Keine Vorgänge.' }}</span>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
    </x-ui-page-container>
</x-ui-page>
