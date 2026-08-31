<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Alle Buchungen" icon="heroicon-o-calendar-days" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Alle Buchungen'],
        ]">
            <x-nx-button variant="primary" :href="route('reservation.bookings.create')">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neue Buchung</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
    <div class="space-y-5">

    @if (session('booking_message'))
        <div class="rounded-[8px] border border-[rgba(47,158,68,.3)] bg-[rgba(47,158,68,.08)] p-3 text-sm text-[color:var(--nx-success)]">{{ session('booking_message') }}</div>
    @endif
    @if (session('booking_error'))
        <div class="rounded-[8px] border border-[rgba(224,49,49,.3)] bg-[rgba(224,49,49,.08)] p-3 text-sm text-[color:var(--nx-danger)]">{{ session('booking_error') }}</div>
    @endif

    {{-- Filter: rahmenlos, luftig --}}
    <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs">
        <div class="flex flex-wrap items-center gap-1">
            @foreach (['' => 'Alle', 'pending' => 'Ausstehend', 'confirmed' => 'Bestätigt', 'cancelled' => 'Storniert', 'no_show' => 'No-Show', 'completed' => 'Abgeschlossen'] as $val => $label)
                <button type="button" wire:click="$set('filterStatus', '{{ $val }}')"
                    class="rounded-full px-2.5 py-1 transition-colors {{ $filterStatus === $val ? 'bg-[color:var(--nx-active)] font-medium text-[color:var(--nx-text)]' : 'text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]' }}">{{ $label }}</button>
            @endforeach
        </div>
        <div class="w-40">
            <x-ui-input-date name="filterDate" size="sm" wire:model.live="filterDate" />
        </div>
        <div class="ml-auto w-64">
            <x-ui-input-text name="search" size="sm" wire:model.live.debounce.300ms="search" placeholder="Suchen…" />
        </div>
    </div>

    {{-- Tabelle: rahmenlos, Hairlines --}}
    @include('reservation::partials.pinned-actions-style')

    <x-nx-table>
            <x-nx-table-header>
                <x-nx-table-header-cell>VA-Datum</x-nx-table-header-cell>
                <x-nx-table-header-cell>Veranstaltung</x-nx-table-header-cell>
                <x-nx-table-header-cell>Uhrzeit</x-nx-table-header-cell>
                <x-nx-table-header-cell>Tisch</x-nx-table-header-cell>
                <x-nx-table-header-cell>Gast</x-nx-table-header-cell>
                <x-nx-table-header-cell align="center">Personen</x-nx-table-header-cell>
                <x-nx-table-header-cell align="right">Bestellung</x-nx-table-header-cell>
                <x-nx-table-header-cell>Status</x-nx-table-header-cell>
                <x-nx-table-header-cell>Gebucht am</x-nx-table-header-cell>
                <x-nx-table-header-cell class="pp-pin"><span class="sr-only">Aktionen</span></x-nx-table-header-cell>
            </x-nx-table-header>
            <x-nx-table-body>
                @forelse ($this->bookings as $booking)
                    <x-nx-table-row wire:key="booking-{{ $booking->id }}" wire:click="openDetail({{ $booking->id }})" class="group cursor-pointer">
                        <x-nx-table-cell class="whitespace-nowrap tabular-nums text-[color:var(--nx-muted)]">{{ $booking->date->format('d.m.Y') }}</x-nx-table-cell>
                        <x-nx-table-cell>
                            <span class="font-medium text-[color:var(--nx-text)]">{{ $booking->event?->name ?? '—' }}</span>
                            @if ($booking->slot)
                                <span class="block text-xs text-[color:var(--nx-faint)]">{{ $booking->slot->name }}</span>
                            @endif
                        </x-nx-table-cell>
                        <x-nx-table-cell class="tabular-nums text-[color:var(--nx-muted)]">{{ $booking->time_start ? substr($booking->time_start, 0, 5) : '–' }}</x-nx-table-cell>
                        <x-nx-table-cell class="text-[color:var(--nx-muted)]">{{ $booking->zielortLabel() }}@if ($booking->zielortFehlt())<span class="ml-1 text-[11px] text-[color:var(--nx-faint)]">(gelöscht)</span>@endif</x-nx-table-cell>
                        <x-nx-table-cell>
                            <span class="font-medium text-[color:var(--nx-text)]">{{ $booking->guest_name }}</span>
                            {{-- Gedeckelt, weil eine E-Mail nicht umbricht: Ohne Deckel
                                 bestimmt die längste Adresse der Seite die Spaltenbreite und
                                 schiebt die Tabelle über den Kartenrand. Vollständig steht sie
                                 im Titel und im Detail-Fenster. --}}
                            @if ($booking->guest_email)
                                <span class="block max-w-[220px] truncate text-xs text-[color:var(--nx-faint)]"
                                    title="{{ $booking->guest_email }}">{{ $booking->guest_email }}</span>
                            @endif
                        </x-nx-table-cell>
                        <x-nx-table-cell align="center" class="tabular-nums text-[color:var(--nx-muted)]">{{ $booking->guest_count }}</x-nx-table-cell>
                        <x-nx-table-cell align="right">
                            @if ($booking->items_count > 0)
                                <span class="whitespace-nowrap tabular-nums text-[color:var(--nx-text)]">{{ $booking->items_count }} Pos. · {{ number_format($booking->total_amount, 2, ',', '.') }} €</span>
                            @else
                                <span class="text-[color:var(--nx-faint)]">–</span>
                            @endif
                        </x-nx-table-cell>
                        <x-nx-table-cell>
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
                            {{-- Woher die Buchung kam, gehört NICHT in den Status: Der sagt,
                                 wo sie im Ablauf steht. "onsite" setzt nur der manuelle Weg –
                                 dort wird an der Kasse bezahlt, nicht über Mollie. --}}
                            @if ($booking->payment_method === 'onsite')
                                <x-nx-badge variant="neutral" title="Manuell angelegt, Zahlung vor Ort">Vor Ort</x-nx-badge>
                            @endif
                        </x-nx-table-cell>
                        <x-nx-table-cell class="whitespace-nowrap text-[color:var(--nx-muted)]">
                            @if ($booking->created_at)
                                <span class="tabular-nums">{{ $booking->created_at->format('d.m.Y') }}</span>
                                <span class="block text-xs tabular-nums text-[color:var(--nx-faint)]">{{ $booking->created_at->format('H:i') }} Uhr</span>
                            @else
                                <span class="text-[color:var(--nx-faint)]">–</span>
                            @endif
                        </x-nx-table-cell>
                        {{-- pp-pin: bleibt am rechten Rand stehen, während der Rest der
                             Tabelle darunter wegscrollt. Zehn Spalten werden breiter als die
                             Karte – ein einziges „Abgeschlossen" verbreitert die Status-Spalte
                             für alle Zeilen –, und ohne das lägen ausgerechnet die Aktionen
                             jenseits des Randes. Siehe partials/pinned-actions-style. --}}
                        <x-nx-table-cell align="right" class="pp-pin">
                            {{-- Aktionen erscheinen beim Hover über die Zeile (Notion-Stil) --}}
                            {{-- Klick auf die Zeile öffnet Details; diese Aktionen stoppen daher den Zeilen-Klick --}}
                            <div class="flex items-center justify-end gap-0.5 opacity-0 transition-opacity duration-150 group-hover:opacity-100 focus-within:opacity-100"
                                @include('reservation::partials.booking-status-menu-state')>
                                @if ($booking->status === 'pending')
                                    <x-nx-button icon variant="ghost" wire:click.stop="confirmBooking({{ $booking->id }})" title="Bestätigen">
                                        @svg('heroicon-o-check', 'w-4 h-4')
                                    </x-nx-button>
                                @endif

                                @if ($booking->status === 'pending')
                                    <button type="button" wire:click.stop="askCancel({{ $booking->id }})" title="Stornieren"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-[6px] text-[color:var(--nx-danger)] transition-colors hover:bg-[rgba(224,49,49,.08)]">
                                        @svg('heroicon-o-x-mark', 'w-4 h-4')
                                    </button>
                                @endif

                                @if ($this->printingAvailable)
                                    <x-nx-button icon variant="ghost" wire:click.stop="openPrintModal({{ $booking->id }})" title="Bon drucken">
                                        @svg('heroicon-o-printer', 'w-4 h-4')
                                    </x-nx-button>
                                @endif

                                {{-- Statuswechsel im Menü statt als eigene Knöpfe: No-Show und
                                     Abgeschlossen werden selten geklickt, Bestätigen und Bon
                                     drucken dauernd – fünf Symbole nebeneinander machen die
                                     häufigen schwerer zu treffen.

                                     Bei stornierten Buchungen entfällt es: Wer abgesagt hat,
                                     kann weder fehlen noch teilnehmen. --}}
                                @if ($booking->status !== 'cancelled')
                                    @include('reservation::partials.booking-status-menu', ['booking' => $booking])
                                @endif
                            </div>
                        </x-nx-table-cell>
                    </x-nx-table-row>
                @empty
                    <tr>
                        <td colspan="10">
                            <div class="flex flex-col items-center justify-center py-14 text-[color:var(--nx-faint)]">
                                @svg('heroicon-o-inbox', 'w-8 h-8 mb-2 opacity-40')
                                <span class="text-xs">Keine Buchungen gefunden</span>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </x-nx-table-body>
        </x-nx-table>

    <div class="flex flex-wrap items-center justify-between gap-3 text-xs text-[color:var(--nx-faint)]">
        <span class="tabular-nums">
            @if ($this->bookings->hasPages())
                {{ $this->bookings->firstItem() }}–{{ $this->bookings->lastItem() }} von {{ $this->bookings->total() }} Buchungen
            @else
                {{ $this->bookings->total() }} {{ $this->bookings->total() === 1 ? 'Buchung' : 'Buchungen' }}
            @endif
        </span>
        {{ $this->bookings->links('reservation::partials.pagination') }}
    </div>

    @include('reservation::partials.booking-detail-modal')

    @include('reservation::partials.booking-print-modal')

    @include('reservation::partials.booking-status-modal')

    </div>
    </x-ui-page-container>
</x-ui-page>
