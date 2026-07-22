<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="PausePlus" icon="heroicon-o-home" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Dashboard'],
        ]">
            <x-nx-button variant="primary" :href="route('reservation.events.index')" wire:navigate>
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Termin anlegen</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
    <div class="space-y-6">

    {{-- Kennzahlen --}}
    <x-nx-stat-grid>
        <x-nx-stat label="Offene Buchungen" :value="(string) $this->stats->pending_bookings" hint="warten auf Bestätigung"
            icon="heroicon-o-inbox" accent="var(--nx-warning)" :href="route('reservation.bookings.index')" wire:navigate />
        <x-nx-stat label="Kommende Termine" :value="(string) $this->stats->upcoming_events"
            icon="heroicon-o-ticket" accent="var(--nx-accent)" :href="route('reservation.events.index')" wire:navigate />
        <x-nx-stat label="Umsatz im Monat" :value="number_format($this->stats->month_revenue, 2, ',', '.') . ' €'" :hint="now()->locale('de')->isoFormat('MMMM Y')"
            icon="heroicon-o-banknotes" accent="var(--nx-success)" :href="route('reservation.finance.index')" wire:navigate />
        <x-nx-stat label="Freigegebene Artikel" :value="$this->stats->approved_items . ' / ' . $this->stats->total_items" hint="Vier-Augen-Freigabe"
            icon="heroicon-o-rectangle-stack" accent="var(--nx-info)" :href="route('reservation.menu.index')" wire:navigate />
    </x-nx-stat-grid>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {{-- Nächste Termine --}}
        <x-nx-card flush>
            <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
                @svg('heroicon-o-ticket', 'w-4 h-4 text-[color:var(--nx-muted)]')
                <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-muted)]">Nächste Termine</h2>
                <a href="{{ route('reservation.events.index') }}" wire:navigate class="ml-auto text-xs text-[color:var(--nx-muted)] transition-colors hover:text-[color:var(--nx-text)]">Alle</a>
            </div>
            <div>
                @forelse ($this->upcomingEvents as $event)
                    <a href="{{ route('reservation.events.dashboard', $event->id) }}" wire:navigate wire:key="dash-event-{{ $event->id }}"
                        class="flex items-center justify-between gap-3 border-b border-[color:var(--nx-line)] px-4 py-2.5 transition-colors last:border-0 hover:bg-[color:var(--nx-hover)]">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="truncate text-sm font-medium text-[color:var(--nx-text)]">{{ $event->name }}</span>
                                @if ($event->status->value !== 'published')
                                    <x-nx-badge>Entwurf</x-nx-badge>
                                @endif
                            </div>
                            <p class="m-0 mt-0.5 text-xs text-[color:var(--nx-muted)]">
                                {{ $event->date->locale('de')->isoFormat('dd, D. MMM') }}
                                @if ($event->venue) · {{ $event->venue->name }} @endif
                                · {{ $event->bookings_count }} {{ $event->bookings_count === 1 ? 'Buchung' : 'Buchungen' }}
                            </p>
                        </div>
                        @svg('heroicon-o-chevron-right', 'w-4 h-4 shrink-0 text-[color:var(--nx-faint)]')
                    </a>
                @empty
                    <x-nx-empty icon="heroicon-o-ticket">
                        Keine kommenden Termine
                        <x-slot name="action">
                            <a href="{{ route('reservation.events.index') }}" wire:navigate class="text-xs text-[color:var(--nx-text)] hover:underline">Termin anlegen</a>
                        </x-slot>
                    </x-nx-empty>
                @endforelse
            </div>
        </x-nx-card>

        {{-- Neueste Buchungen --}}
        <x-nx-card flush>
            <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
                @svg('heroicon-o-calendar-days', 'w-4 h-4 text-[color:var(--nx-muted)]')
                <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-muted)]">Neueste Buchungen</h2>
                <a href="{{ route('reservation.bookings.index') }}" wire:navigate class="ml-auto text-xs text-[color:var(--nx-muted)] transition-colors hover:text-[color:var(--nx-text)]">Alle</a>
            </div>
            <div>
                @forelse ($this->recentBookings as $booking)
                    <div wire:key="dash-booking-{{ $booking->id }}" class="flex items-center justify-between gap-3 border-b border-[color:var(--nx-line)] px-4 py-2.5 last:border-0">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="truncate text-sm font-medium text-[color:var(--nx-text)]">{{ $booking->guest_name }}</span>
                                @php
                                    [$statusLabel, $statusVariant] = [
                                        'pending'   => ['Ausstehend', 'warning'],
                                        'confirmed' => ['Bestätigt', 'success'],
                                        'cancelled' => ['Storniert', 'danger'],
                                        'no_show'   => ['No-Show', 'neutral'],
                                        'completed' => ['Abgeschlossen', 'info'],
                                    ][$booking->status] ?? [ucfirst($booking->status), 'neutral'];
                                @endphp
                                <x-nx-badge :variant="$statusVariant">{{ $statusLabel }}</x-nx-badge>
                            </div>
                            <p class="m-0 mt-0.5 text-xs text-[color:var(--nx-muted)]">
                                {{ $booking->date->format('d.m.Y') }}
                                @if ($booking->event) · {{ $booking->event->name }} @endif
                                @if ($booking->table) · Tisch {{ $booking->table->label }} @endif
                                · {{ $booking->guest_count }} P.
                            </p>
                        </div>
                        @if ($booking->items_count > 0)
                            <span class="shrink-0 whitespace-nowrap text-xs font-semibold tabular-nums text-[color:var(--nx-text)]">
                                {{ number_format($booking->total_amount, 2, ',', '.') }} €
                            </span>
                        @endif
                    </div>
                @empty
                    <x-nx-empty icon="heroicon-o-inbox">Noch keine Buchungen</x-nx-empty>
                @endforelse
            </div>
        </x-nx-card>
    </div>

    </div>
    </x-ui-page-container>
</x-ui-page>
