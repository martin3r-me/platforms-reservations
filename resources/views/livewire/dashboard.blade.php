<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="PausePlus" icon="heroicon-o-home" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Dashboard'],
        ]">
            <div class="flex items-center gap-2">
                @if (\Illuminate\Support\Facades\Route::has('reservation.guest.events.index'))
                    <x-ui-button variant="secondary-outline" size="sm" :href="route('reservation.guest.events.index')" target="_blank">
                        @svg('heroicon-o-globe-alt', 'w-4 h-4')
                        <span>Gast-Übersicht</span>
                    </x-ui-button>
                @endif
                <x-ui-button variant="primary" size="sm" :href="route('reservation.events.index')" wire:navigate>
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    <span>Termin anlegen</span>
                </x-ui-button>
            </div>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
    <div class="pt-4 space-y-4">

    {{-- Kennzahlen --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([
            [
                'label' => 'Offene Buchungen',
                'value' => (string) $this->stats->pending_bookings,
                'hint'  => 'warten auf Bestätigung',
                'icon'  => 'heroicon-o-inbox',
                'color' => 'var(--ui-warning)',
                'href'  => route('reservation.bookings.index'),
            ],
            [
                'label' => 'Kommende Termine',
                'value' => (string) $this->stats->upcoming_events,
                'hint'  => null,
                'icon'  => 'heroicon-o-ticket',
                'color' => 'var(--ui-primary)',
                'href'  => route('reservation.events.index'),
            ],
            [
                'label' => 'Umsatz im Monat',
                'value' => number_format($this->stats->month_revenue, 2, ',', '.') . ' €',
                'hint'  => now()->locale('de')->isoFormat('MMMM Y'),
                'icon'  => 'heroicon-o-banknotes',
                'color' => 'var(--ui-success)',
                'href'  => route('reservation.finance.index'),
            ],
            [
                'label' => 'Freigegebene Artikel',
                'value' => $this->stats->approved_items . ' / ' . $this->stats->total_items,
                'hint'  => 'Vier-Augen-Freigabe',
                'icon'  => 'heroicon-o-rectangle-stack',
                'color' => 'var(--ui-info)',
                'href'  => route('reservation.menu.index'),
            ],
        ] as $tile)
            <a href="{{ $tile['href'] }}" wire:navigate wire:key="tile-{{ $loop->index }}"
                class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm p-3 transition hover:shadow-md">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)]">{{ $tile['label'] }}</span>
                    @svg($tile['icon'], 'w-4 h-4 shrink-0', ['style' => 'color: ' . $tile['color']])
                </div>
                <p class="m-0 mt-1 whitespace-nowrap text-lg font-bold tabular-nums text-[var(--ui-secondary)]">{{ $tile['value'] }}</p>
                @if ($tile['hint'])
                    <p class="m-0 mt-0.5 text-[11px] text-[var(--ui-muted)]">{{ $tile['hint'] }}</p>
                @endif
            </a>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {{-- Nächste Termine --}}
        <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
                @svg('heroicon-o-ticket', 'w-4 h-4 text-[var(--ui-muted)]')
                <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Nächste Termine</h2>
                <a href="{{ route('reservation.events.index') }}" wire:navigate class="ml-auto text-[11px] text-[var(--ui-primary)] hover:underline">Alle</a>
            </div>
            <div class="divide-y divide-[var(--ui-border)]/30">
                @forelse ($this->upcomingEvents as $event)
                    <div wire:key="dash-event-{{ $event->id }}" class="flex items-center justify-between gap-3 px-4 py-2.5 hover:bg-[var(--ui-muted-5)] transition-colors">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-[var(--ui-secondary)] truncate">{{ $event->name }}</span>
                                @if ($event->status !== 'published')
                                    <x-ui-badge variant="muted" size="xs">Entwurf</x-ui-badge>
                                @endif
                            </div>
                            <p class="text-xs text-[var(--ui-muted)] m-0 mt-0.5">
                                {{ $event->date->locale('de')->isoFormat('dd, D. MMM') }}
                                @if ($event->venue) · {{ $event->venue->name }} @endif
                                · {{ $event->bookings_count }} {{ $event->bookings_count === 1 ? 'Buchung' : 'Buchungen' }}
                            </p>
                        </div>
                        @if ($event->bookings_count > 0)
                            <x-ui-button variant="secondary-outline" size="sm" :href="route('reservation.events.orders', $event->id)" wire:navigate>
                                @svg('heroicon-o-fire', 'w-4 h-4')
                                <span>Küche</span>
                            </x-ui-button>
                        @endif
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center py-8 text-[var(--ui-muted)]">
                        @svg('heroicon-o-ticket', 'w-8 h-8 mb-2 opacity-40')
                        <span class="text-xs">Keine kommenden Termine</span>
                        <a href="{{ route('reservation.events.index') }}" wire:navigate class="mt-1 text-[11px] text-[var(--ui-primary)] hover:underline">Termin anlegen</a>
                    </div>
                @endforelse
            </div>
        </section>

        {{-- Neueste Buchungen --}}
        <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
                @svg('heroicon-o-calendar-days', 'w-4 h-4 text-[var(--ui-muted)]')
                <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Neueste Buchungen</h2>
                <a href="{{ route('reservation.bookings.index') }}" wire:navigate class="ml-auto text-[11px] text-[var(--ui-primary)] hover:underline">Alle</a>
            </div>
            <div class="divide-y divide-[var(--ui-border)]/30">
                @forelse ($this->recentBookings as $booking)
                    <div wire:key="dash-booking-{{ $booking->id }}" class="flex items-center justify-between gap-3 px-4 py-2.5 hover:bg-[var(--ui-muted-5)] transition-colors">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-[var(--ui-secondary)] truncate">{{ $booking->guest_name }}</span>
                                @php
                                    [$statusLabel, $statusVariant] = [
                                        'pending'   => ['Ausstehend', 'warning'],
                                        'confirmed' => ['Bestätigt', 'success'],
                                        'cancelled' => ['Storniert', 'danger'],
                                        'no_show'   => ['No-Show', 'muted'],
                                        'completed' => ['Abgeschlossen', 'info'],
                                    ][$booking->status] ?? [ucfirst($booking->status), 'muted'];
                                @endphp
                                <x-ui-badge :variant="$statusVariant" size="xs">{{ $statusLabel }}</x-ui-badge>
                            </div>
                            <p class="text-xs text-[var(--ui-muted)] m-0 mt-0.5">
                                {{ $booking->date->format('d.m.Y') }}
                                @if ($booking->event) · {{ $booking->event->name }} @endif
                                @if ($booking->table) · Tisch {{ $booking->table->label }} @endif
                                · {{ $booking->guest_count }} P.
                            </p>
                        </div>
                        @if ($booking->items_count > 0)
                            <span class="shrink-0 whitespace-nowrap text-xs font-semibold tabular-nums text-[var(--ui-secondary)]">
                                {{ number_format($booking->total_amount, 2, ',', '.') }} €
                            </span>
                        @endif
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center py-8 text-[var(--ui-muted)]">
                        @svg('heroicon-o-inbox', 'w-8 h-8 mb-2 opacity-40')
                        <span class="text-xs">Noch keine Buchungen</span>
                    </div>
                @endforelse
            </div>
        </section>
    </div>

    </div>
    </x-ui-page-container>
</x-ui-page>
