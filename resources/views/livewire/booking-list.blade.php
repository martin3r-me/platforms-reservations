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
                <x-nx-table-header-cell><span class="sr-only">Aktionen</span></x-nx-table-header-cell>
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
                        <x-nx-table-cell class="text-[color:var(--nx-muted)]">{{ $booking->table?->label }}</x-nx-table-cell>
                        <x-nx-table-cell>
                            <span class="font-medium text-[color:var(--nx-text)]">{{ $booking->guest_name }}</span>
                            @if ($booking->guest_email)
                                <span class="block text-xs text-[color:var(--nx-faint)]">{{ $booking->guest_email }}</span>
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
                        </x-nx-table-cell>
                        <x-nx-table-cell class="whitespace-nowrap text-[color:var(--nx-muted)]">
                            @if ($booking->created_at)
                                <span class="tabular-nums">{{ $booking->created_at->format('d.m.Y') }}</span>
                                <span class="block text-xs tabular-nums text-[color:var(--nx-faint)]">{{ $booking->created_at->format('H:i') }} Uhr</span>
                            @else
                                <span class="text-[color:var(--nx-faint)]">–</span>
                            @endif
                        </x-nx-table-cell>
                        <x-nx-table-cell align="right">
                            {{-- Aktionen erscheinen beim Hover über die Zeile (Notion-Stil) --}}
                            {{-- Klick auf die Zeile öffnet Details; diese Aktionen stoppen daher den Zeilen-Klick --}}
                            <div class="flex items-center justify-end gap-0.5 opacity-0 transition-opacity duration-150 group-hover:opacity-100 focus-within:opacity-100">
                                @if ($booking->status === 'pending')
                                    <x-nx-button icon variant="ghost" wire:click.stop="confirmBooking({{ $booking->id }})" title="Bestätigen">
                                        @svg('heroicon-o-check', 'w-4 h-4')
                                    </x-nx-button>
                                @endif
                                @if ($this->printingAvailable)
                                    <x-nx-button icon variant="ghost" wire:click.stop="openPrintModal({{ $booking->id }})" title="Bon drucken">
                                        @svg('heroicon-o-printer', 'w-4 h-4')
                                    </x-nx-button>
                                @endif
                                @if ($booking->status === 'pending')
                                    <button type="button" wire:click.stop="cancelBooking({{ $booking->id }})" wire:confirm="Wirklich stornieren?" title="Stornieren"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-[6px] text-[color:var(--nx-danger)] transition-colors hover:bg-[rgba(224,49,49,.08)]">
                                        @svg('heroicon-o-x-mark', 'w-4 h-4')
                                    </button>
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

    <div class="flex items-center justify-between gap-3 text-xs text-[color:var(--nx-faint)]">
        <span class="tabular-nums">{{ $this->bookings->total() }} Buchungen</span>
        <div>{{ $this->bookings->links() }}</div>
    </div>

    {{-- Detail-Modal: Buchung mit Bestellpositionen --}}
    <x-nx-modal size="md" wire:model="showDetail">
        <x-slot name="header">
            <h3 class="m-0 text-base font-semibold leading-tight text-[color:var(--nx-text)]">
                Buchung {{ $this->detailBooking?->guest_name }}
            </h3>
            @if ($this->detailBooking)
                <p class="m-0 mt-1 text-xs text-[color:var(--nx-muted)]">
                    {{ $this->detailBooking->date->format('d.m.Y') }}@if ($this->detailBooking->time_start) · {{ substr($this->detailBooking->time_start, 0, 5) }} Uhr @endif
                    @if ($this->detailBooking->table) · Tisch {{ $this->detailBooking->table->label }} @endif
                </p>
            @endif
        </x-slot>

        @if ($this->detailBooking)
            @php $detail = $this->detailBooking; @endphp
            <div class="space-y-4">
                {{-- Kontext --}}
                <div class="space-y-1 rounded-[8px] border border-[color:var(--nx-line)] bg-[color:var(--nx-bg)] p-3 text-sm">
                    @if ($detail->event)
                        <p class="m-0 text-[color:var(--nx-text)]">
                            <span class="font-medium">{{ $detail->event->name }}</span>
                            @if ($detail->slot) · {{ $detail->slot->displayLabel() }} @endif
                            @if ($detail->table?->floorPlan) · {{ $detail->table->floorPlan->name }} @endif
                        </p>
                    @endif
                    <p class="m-0 text-[color:var(--nx-muted)]">
                        {{ $detail->guest_count }} {{ $detail->guest_count === 1 ? 'Person' : 'Personen' }}
                        @if ($detail->guest_email) · {{ $detail->guest_email }} @endif
                        @if ($detail->guest_phone) · {{ $detail->guest_phone }} @endif
                    </p>
                    @if ($detail->payment_method)
                        <p class="m-0 text-[color:var(--nx-muted)]">Zahlart: {{ ['card' => 'Karte', 'paypal' => 'PayPal', 'applepay' => 'Apple Pay'][$detail->payment_method] ?? $detail->payment_method }}</p>
                    @endif
                    @if ($detail->notes)
                        <p class="m-0 text-[color:var(--nx-muted)]">Anmerkung: {{ $detail->notes }}</p>
                    @endif
                </div>

                {{-- Bestellpositionen --}}
                @if ($detail->items->isEmpty())
                    <div class="flex flex-col items-center justify-center py-6 text-[color:var(--nx-faint)]">
                        @svg('heroicon-o-inbox', 'w-6 h-6 mb-1 opacity-40')
                        <span class="text-xs">Keine Vorbestellung – nur Tischreservierung</span>
                    </div>
                @else
                    <section class="overflow-hidden rounded-[8px] border border-[color:var(--nx-line)]">
                        <div class="divide-y divide-[color:var(--nx-line)]">
                            @foreach ($detail->items as $item)
                                <div wire:key="detail-item-{{ $item->id }}" class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                                    <div class="min-w-0">
                                        <span class="text-[color:var(--nx-text)]">
                                            <span class="font-semibold tabular-nums">{{ $item->quantity }}×</span>
                                            {{ $item->menuItem?->name ?? 'Gelöschter Artikel' }}
                                        </span>
                                        @if ($item->notes)
                                            <p class="m-0 text-xs text-[color:var(--nx-muted)]">{{ $item->notes }}</p>
                                        @endif
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <span class="whitespace-nowrap tabular-nums text-[color:var(--nx-text)]">{{ number_format($item->quantity * $item->unit_price, 2, ',', '.') }} €</span>
                                        <span class="block text-[11px] tabular-nums text-[color:var(--nx-muted)]">
                                            à {{ number_format($item->unit_price, 2, ',', '.') }} € · {{ rtrim(rtrim($item->tax_rate, '0'), '.') }} %
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="flex justify-between border-t border-[color:var(--nx-line)] bg-[color:var(--nx-bg)] px-3 py-2 text-sm font-semibold text-[color:var(--nx-text)]">
                            <span>Gesamt</span>
                            <span class="whitespace-nowrap tabular-nums">{{ number_format($detail->total_amount, 2, ',', '.') }} €</span>
                        </div>
                    </section>
                @endif
            </div>
        @endif

        <x-slot name="footer">
            @if ($this->printingAvailable && $this->detailBooking)
                <x-nx-button wire:click="openPrintModal({{ $this->detailBooking->id }})">
                    @svg('heroicon-o-printer', 'w-4 h-4')
                    <span>Bon drucken</span>
                </x-nx-button>
            @endif
            <x-nx-button wire:click="$set('showDetail', false)">Schließen</x-nx-button>
        </x-slot>
    </x-nx-modal>

    {{-- Bon drucken: Drucker/Gruppe wählen --}}
    <x-nx-modal size="sm" wire:model="printModalShow">
        <x-slot name="header">
            <h3 class="m-0 text-base font-semibold leading-tight text-[color:var(--nx-text)]">Bon drucken</h3>
            <p class="m-0 mt-1 text-xs text-[color:var(--nx-muted)]">Buchung als Beleg an einen Drucker senden</p>
        </x-slot>

        <div class="space-y-4">
            {{-- Ziel: Einzeldrucker oder Gruppe --}}
            <div class="inline-flex overflow-hidden rounded-[8px] border border-[color:var(--nx-line-strong)]">
                <button type="button" wire:click="$set('printTarget', 'printer')" class="px-3 py-1.5 text-sm transition-colors {{ $printTarget === 'printer' ? 'bg-[color:var(--nx-accent)] text-[color:var(--nx-on-accent)]' : 'text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)]' }}">Drucker</button>
                <button type="button" wire:click="$set('printTarget', 'group')" class="px-3 py-1.5 text-sm transition-colors {{ $printTarget === 'group' ? 'bg-[color:var(--nx-accent)] text-[color:var(--nx-on-accent)]' : 'text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)]' }}">Gruppe</button>
            </div>

            @if ($printTarget === 'printer')
                @if ($this->printers->isEmpty())
                    <p class="m-0 text-sm text-[color:var(--nx-muted)]">Kein Drucker verfügbar.</p>
                @else
                    <x-ui-input-select
                        name="selectedPrinterId"
                        label="Drucker"
                        :options="$this->printers->map(fn ($p) => ['value' => $p->id, 'label' => $p->name])->values()->all()"
                        :nullable="true"
                        nullLabel="– wählen –"
                        wire:model="selectedPrinterId"
                    />
                @endif
            @else
                @if ($this->printerGroups->isEmpty())
                    <p class="m-0 text-sm text-[color:var(--nx-muted)]">Keine Drucker-Gruppe verfügbar.</p>
                @else
                    <x-ui-input-select
                        name="selectedPrinterGroupId"
                        label="Drucker-Gruppe"
                        :options="$this->printerGroups->map(fn ($g) => ['value' => $g->id, 'label' => $g->name])->values()->all()"
                        :nullable="true"
                        nullLabel="– wählen –"
                        wire:model="selectedPrinterGroupId"
                    />
                @endif
            @endif
        </div>

        <x-slot name="footer">
            <x-nx-button wire:click="closePrintModal">Abbrechen</x-nx-button>
            <x-nx-button variant="primary" wire:click="printBookingConfirm">
                @svg('heroicon-o-printer', 'w-4 h-4')
                <span>Drucken</span>
            </x-nx-button>
        </x-slot>
    </x-nx-modal>

    </div>
    </x-ui-page-container>
</x-ui-page>
