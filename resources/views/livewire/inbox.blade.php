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

    <x-ui-page-container width="contained">
    <div class="space-y-5">
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
            <x-nx-callout variant="success">{{ session('inbox_message') }}</x-nx-callout>
        @endif

        {{-- Filter + Bulk (rahmenlos) --}}
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs">
            <div class="flex items-center gap-1">
                <button type="button" wire:click="$set('unseenOnly', true)"
                    class="rounded-full px-2.5 py-1 transition-colors {{ $unseenOnly ? 'bg-[color:var(--nx-active)] font-medium text-[color:var(--nx-text)]' : 'text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]' }}">Ungesehen ({{ $this->unseenCount }})</button>
                <button type="button" wire:click="$set('unseenOnly', false)"
                    class="rounded-full px-2.5 py-1 transition-colors {{ !$unseenOnly ? 'bg-[color:var(--nx-active)] font-medium text-[color:var(--nx-text)]' : 'text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]' }}">Alle</button>
            </div>
            <div class="ml-auto flex items-center gap-2">
                @if (count($selected))
                    <span class="text-[color:var(--nx-muted)]">{{ count($selected) }} ausgewählt</span>
                    <x-nx-button variant="primary" wire:click="markSelectedSeen">Ausgewählte als gesehen</x-nx-button>
                @endif
                @if ($this->unseenCount > 0)
                    <x-nx-button wire:click="markAllSeen" wire:confirm="Alle als gesehen markieren?">Alle als gesehen</x-nx-button>
                @endif
            </div>
        </div>

        <x-nx-card flush>
            <div>
                @forelse ($this->entries as $order)
                    @php [$label, $variant, $icon] = $typeMap[$order->inboxType()] ?? ['Vorgang', 'neutral', 'heroicon-o-bell']; @endphp
                    <div wire:key="inbox-{{ $order->id }}" class="flex flex-wrap items-center gap-3 border-b border-[color:var(--nx-line)] px-4 py-3 last:border-0 {{ $order->seen_at ? '' : 'bg-[color:var(--nx-accent-soft)]' }}">
                        <input type="checkbox" wire:model.live="selected" value="{{ $order->id }}" class="h-4 w-4 rounded-[4px] accent-[var(--nx-accent)]" />

                        <div class="w-40 shrink-0">
                            <x-nx-badge :variant="$variant">{{ $label }}</x-nx-badge>
                        </div>

                        <div class="min-w-0 flex-1">
                            <span class="text-sm font-medium text-[color:var(--nx-text)]">{{ $order->customerName() ?: '—' }}</span>
                            @if (!$order->seen_at)<span class="ml-1 inline-block h-2 w-2 rounded-full bg-[color:var(--nx-accent)]" title="ungesehen"></span>@endif
                            <p class="m-0 mt-0.5 text-xs text-[color:var(--nx-muted)]">
                                @if ($order->event){{ $order->event->name }} · @endif
                                {{ number_format((float) $order->total_amount, 2, ',', '.') }} {{ $sym }}
                                · {{ $order->bookings->count() }} {{ $order->bookings->count() === 1 ? 'Pause' : 'Pausen' }}
                                @if ($order->payment) · Zahlung: {{ $order->payment->status }} @endif
                                · {{ $order->updated_at?->diffForHumans() }}
                            </p>
                        </div>

                        <div class="flex shrink-0 flex-wrap items-center justify-end gap-1.5">
                            @if ($order->inboxType() === 'cancellation_requested')
                                <x-nx-button variant="danger" wire:click="approveCancellation({{ $order->id }})" wire:confirm="Storno freigeben und Zahlung erstatten?">Freigeben &amp; erstatten</x-nx-button>
                                <x-nx-button variant="ghost" wire:click="rejectCancellation({{ $order->id }})" wire:confirm="Storno-Anfrage ablehnen?">Ablehnen</x-nx-button>
                            @endif
                            @if (!$order->seen_at)
                                <x-nx-button wire:click="markSeen({{ $order->id }})">Gesehen</x-nx-button>
                            @else
                                <span class="text-[11px] text-[color:var(--nx-faint)]">gesehen {{ $order->seen_at->format('d.m. H:i') }}</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <x-nx-empty icon="heroicon-o-check-circle">{{ $unseenOnly ? 'Alles gesehen – nichts Neues im Posteingang.' : 'Keine Vorgänge.' }}</x-nx-empty>
                @endforelse
            </div>
        </x-nx-card>
    </div>
    </x-ui-page-container>
</x-ui-page>
