<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Buchungen" icon="heroicon-o-calendar-days" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Buchungen'],
        ]">
            <x-ui-button variant="primary" size="sm" :href="route('reservation.bookings.create')">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neue Buchung</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
    <div class="pt-4 space-y-4">

    {{-- Filter --}}
    <div class="flex flex-wrap items-end gap-2">
        <div class="w-44">
            <x-ui-input-date name="filterDate" size="sm" wire:model.live="filterDate" />
        </div>
        <div class="w-44">
            <x-ui-input-select
                name="filterStatus"
                size="sm"
                :options="[
                    ['value' => 'pending', 'label' => 'Ausstehend'],
                    ['value' => 'confirmed', 'label' => 'Bestätigt'],
                    ['value' => 'cancelled', 'label' => 'Storniert'],
                    ['value' => 'no_show', 'label' => 'No-Show'],
                    ['value' => 'completed', 'label' => 'Abgeschlossen'],
                ]"
                :nullable="true"
                nullLabel="Alle Status"
                wire:model.live="filterStatus"
            />
        </div>
        <div class="min-w-[200px] flex-1">
            <x-ui-input-text name="search" size="sm" wire:model.live.debounce.300ms="search" placeholder="Name oder E-Mail suchen…" />
        </div>
    </div>

    {{-- Tabelle --}}
    <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
        <x-ui-table compact="true">
            <x-ui-table-header>
                <x-ui-table-header-cell compact="true">Datum</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Uhrzeit</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Tisch</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Gast</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true" align="center">Personen</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Aktionen</x-ui-table-header-cell>
            </x-ui-table-header>
            <x-ui-table-body>
                @forelse ($this->bookings as $booking)
                    <x-ui-table-row compact="true" wire:key="booking-{{ $booking->id }}">
                        <x-ui-table-cell compact="true">{{ $booking->date->format('d.m.Y') }}</x-ui-table-cell>
                        <x-ui-table-cell compact="true">{{ $booking->time_start }}</x-ui-table-cell>
                        <x-ui-table-cell compact="true">{{ $booking->table?->label }}</x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="font-medium text-[var(--ui-secondary)]">{{ $booking->guest_name }}</span>
                            @if ($booking->guest_email)
                                <span class="block text-xs text-[var(--ui-muted)]">{{ $booking->guest_email }}</span>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true" align="center">{{ $booking->guest_count }}</x-ui-table-cell>
                        <x-ui-table-cell compact="true">
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
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <div class="flex gap-1.5">
                                @if ($booking->status === 'pending')
                                    <x-ui-button variant="success" size="sm" wire:click="confirmBooking({{ $booking->id }})">Bestätigen</x-ui-button>
                                    <x-ui-confirm-button
                                        action="cancelBooking"
                                        :value="$booking->id"
                                        text="Stornieren"
                                        confirmText="Buchung wirklich stornieren?"
                                        variant="danger"
                                        size="sm"
                                    />
                                @elseif ($booking->status === 'confirmed')
                                    <x-ui-button variant="info" size="sm" wire:click="markCompleted({{ $booking->id }})">Abschließen</x-ui-button>
                                    <x-ui-button variant="secondary-outline" size="sm" wire:click="markNoShow({{ $booking->id }})">No-Show</x-ui-button>
                                @endif
                            </div>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @empty
                    <tr>
                        <td colspan="7">
                            <div class="flex flex-col items-center justify-center py-8 text-[var(--ui-muted)]">
                                @svg('heroicon-o-inbox', 'w-8 h-8 mb-2 opacity-40')
                                <span class="text-xs">Keine Buchungen gefunden</span>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </x-ui-table-body>
        </x-ui-table>
    </section>

    <div>
        {{ $this->bookings->links() }}
    </div>

    </div>
    </x-ui-page-container>
</x-ui-page>
