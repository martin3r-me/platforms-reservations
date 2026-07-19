<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Alle Buchungen" icon="heroicon-o-calendar-days" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Alle Buchungen'],
        ]">
            <x-ui-button variant="primary" size="sm" :href="route('reservation.bookings.create')">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neue Buchung</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
    <div class="pt-4 space-y-4">

    @if (session('booking_message'))
        <div class="rounded-lg border border-[var(--ui-success)]/30 bg-[var(--ui-success-10)] p-3 text-sm text-[var(--ui-success)]">{{ session('booking_message') }}</div>
    @endif
    @if (session('booking_error'))
        <div class="rounded-lg border border-[var(--ui-danger)]/30 bg-[var(--ui-danger-10)] p-3 text-sm text-[var(--ui-danger)]">{{ session('booking_error') }}</div>
    @endif

    <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
        {{-- Karten-Header --}}
        <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
            @svg('heroicon-o-calendar-days', 'w-4 h-4 text-[var(--ui-muted)]')
            <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Buchungen</h2>
            <span class="ml-auto text-[11px] text-[var(--ui-muted)]">{{ $this->bookings->total() }}</span>
        </div>

        {{-- Filter --}}
        <div class="flex flex-wrap items-center gap-1.5 border-b border-[var(--ui-border)]/30 px-4 py-2 text-[11px]">
            <span class="text-[var(--ui-muted)]">Status:</span>
            @foreach (['' => 'Alle', 'pending' => 'Ausstehend', 'confirmed' => 'Bestätigt', 'cancelled' => 'Storniert', 'no_show' => 'No-Show', 'completed' => 'Abgeschlossen'] as $val => $label)
                <button type="button" wire:click="$set('filterStatus', '{{ $val }}')"
                    class="rounded-full px-2.5 py-0.5 transition-colors {{ $filterStatus === $val ? 'bg-[var(--ui-primary)] font-medium text-white' : 'text-[var(--ui-muted)] hover:bg-[var(--ui-muted-5)]' }}">{{ $label }}</button>
            @endforeach
            <span class="ml-3 text-[var(--ui-muted)]">Datum:</span>
            <div class="w-40">
                <x-ui-input-date name="filterDate" size="sm" wire:model.live="filterDate" />
            </div>
            <div class="ml-auto w-56">
                <x-ui-input-text name="search" size="sm" wire:model.live.debounce.300ms="search" placeholder="Name oder E-Mail suchen…" />
            </div>
        </div>

        {{-- Tabelle --}}
        <x-ui-table compact="true">
            <x-ui-table-header>
                <x-ui-table-header-cell compact="true">Datum</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Veranstaltung</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Uhrzeit</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Tisch</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Gast</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true" align="center">Personen</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true" align="right">Bestellung</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Gebucht am</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Aktionen</x-ui-table-header-cell>
            </x-ui-table-header>
            <x-ui-table-body>
                @forelse ($this->bookings as $booking)
                    <x-ui-table-row compact="true" wire:key="booking-{{ $booking->id }}">
                        <x-ui-table-cell compact="true">{{ $booking->date->format('d.m.Y') }}</x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="font-medium text-[var(--ui-secondary)]">{{ $booking->event?->name ?? '—' }}</span>
                            @if ($booking->slot)
                                <span class="block text-xs text-[var(--ui-muted)]">{{ $booking->slot->name }}</span>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">{{ $booking->time_start ? substr($booking->time_start, 0, 5) : '–' }}</x-ui-table-cell>
                        <x-ui-table-cell compact="true">{{ $booking->table?->label }}</x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="font-medium text-[var(--ui-secondary)]">{{ $booking->guest_name }}</span>
                            @if ($booking->guest_email)
                                <span class="block text-xs text-[var(--ui-muted)]">{{ $booking->guest_email }}</span>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true" align="center">{{ $booking->guest_count }}</x-ui-table-cell>
                        <x-ui-table-cell compact="true" align="right">
                            @if ($booking->items_count > 0)
                                <button wire:click="openDetail({{ $booking->id }})" type="button"
                                    class="inline-flex items-center gap-1 whitespace-nowrap text-[var(--ui-primary)] hover:underline">
                                    <span class="tabular-nums">{{ $booking->items_count }} Pos. · {{ number_format($booking->total_amount, 2, ',', '.') }} €</span>
                                </button>
                            @else
                                <span class="text-[var(--ui-muted)]">–</span>
                            @endif
                        </x-ui-table-cell>
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
                            @if ($booking->created_at)
                                <span class="whitespace-nowrap tabular-nums text-[var(--ui-secondary)]">{{ $booking->created_at->format('d.m.Y') }}</span>
                                <span class="block text-xs text-[var(--ui-muted)] tabular-nums">{{ $booking->created_at->format('H:i') }} Uhr</span>
                            @else
                                <span class="text-[var(--ui-muted)]">–</span>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <div class="flex flex-wrap gap-1.5">
                                @if ($booking->status === 'pending')
                                    <x-ui-button variant="success" size="sm" wire:click="confirmBooking({{ $booking->id }})">Bestätigen</x-ui-button>
                                @endif
                                <x-ui-button variant="secondary-outline" size="sm" wire:click="openDetail({{ $booking->id }})">Details</x-ui-button>
                                @if ($this->printingAvailable)
                                    <x-ui-button variant="secondary-outline" size="sm" :iconOnly="true" wire:click="openPrintModal({{ $booking->id }})" title="Bon drucken">
                                        @svg('heroicon-o-printer', 'w-4 h-4')
                                    </x-ui-button>
                                @endif
                                @if ($booking->status === 'pending')
                                    <div class="shrink-0">
                                        <x-ui-confirm-button
                                            action="cancelBooking"
                                            :value="$booking->id"
                                            text=""
                                            confirmText="Wirklich stornieren?"
                                            variant="danger-outline"
                                            size="sm"
                                            :icon="svg('heroicon-o-x-mark', 'w-4 h-4')->toHtml()"
                                        />
                                    </div>
                                @endif
                            </div>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @empty
                    <tr>
                        <td colspan="10">
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

    {{-- Detail-Modal: Buchung mit Bestellpositionen --}}
    <x-ui-modal size="md" wire:model="showDetail">
        <x-slot name="header">
            <div class="flex items-center gap-3">
                <div class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-[var(--ui-primary-10)] flex-shrink-0">
                    @svg('heroicon-o-clipboard-document-list', 'w-5 h-5 text-[var(--ui-primary)]')
                </div>
                <div class="min-w-0">
                    <h3 class="text-base font-semibold text-[var(--ui-secondary)] m-0 leading-tight">
                        Buchung {{ $this->detailBooking?->guest_name }}
                    </h3>
                    @if ($this->detailBooking)
                        <p class="text-[12px] text-[var(--ui-muted)] m-0 mt-0.5">
                            {{ $this->detailBooking->date->format('d.m.Y') }}@if ($this->detailBooking->time_start) · {{ substr($this->detailBooking->time_start, 0, 5) }} Uhr @endif
                            @if ($this->detailBooking->table) · Tisch {{ $this->detailBooking->table->label }} @endif
                        </p>
                    @endif
                </div>
            </div>
        </x-slot>

        @if ($this->detailBooking)
            @php $detail = $this->detailBooking; @endphp
            <div class="space-y-4">
                {{-- Kontext --}}
                <div class="rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 p-3 text-sm space-y-1">
                    @if ($detail->event)
                        <p class="m-0 text-[var(--ui-secondary)]">
                            <span class="font-medium">{{ $detail->event->name }}</span>
                            @if ($detail->slot) · {{ $detail->slot->displayLabel() }} @endif
                            @if ($detail->table?->floorPlan) · {{ $detail->table->floorPlan->name }} @endif
                        </p>
                    @endif
                    <p class="m-0 text-[var(--ui-muted)]">
                        {{ $detail->guest_count }} {{ $detail->guest_count === 1 ? 'Person' : 'Personen' }}
                        @if ($detail->guest_email) · {{ $detail->guest_email }} @endif
                        @if ($detail->guest_phone) · {{ $detail->guest_phone }} @endif
                    </p>
                    @if ($detail->payment_method)
                        <p class="m-0 text-[var(--ui-muted)]">Zahlart: {{ ['card' => 'Karte', 'paypal' => 'PayPal', 'applepay' => 'Apple Pay'][$detail->payment_method] ?? $detail->payment_method }}</p>
                    @endif
                    @if ($detail->notes)
                        <p class="m-0 text-[var(--ui-muted)]">Anmerkung: {{ $detail->notes }}</p>
                    @endif
                </div>

                {{-- Bestellpositionen --}}
                @if ($detail->items->isEmpty())
                    <div class="flex flex-col items-center justify-center py-6 text-[var(--ui-muted)]">
                        @svg('heroicon-o-inbox', 'w-6 h-6 mb-1 opacity-40')
                        <span class="text-xs">Keine Vorbestellung – nur Tischreservierung</span>
                    </div>
                @else
                    <section class="rounded-lg border border-[var(--ui-border)]/40 overflow-hidden">
                        <div class="divide-y divide-[var(--ui-border)]/30">
                            @foreach ($detail->items as $item)
                                <div wire:key="detail-item-{{ $item->id }}" class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                                    <div class="min-w-0">
                                        <span class="text-[var(--ui-secondary)]">
                                            <span class="font-semibold tabular-nums">{{ $item->quantity }}×</span>
                                            {{ $item->menuItem?->name ?? 'Gelöschter Artikel' }}
                                        </span>
                                        @if ($item->notes)
                                            <p class="text-xs text-[var(--ui-muted)] m-0">{{ $item->notes }}</p>
                                        @endif
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <span class="whitespace-nowrap tabular-nums text-[var(--ui-secondary)]">{{ number_format($item->quantity * $item->unit_price, 2, ',', '.') }} €</span>
                                        <span class="block text-[11px] text-[var(--ui-muted)] tabular-nums">
                                            à {{ number_format($item->unit_price, 2, ',', '.') }} € · {{ rtrim(rtrim($item->tax_rate, '0'), '.') }} %
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="flex justify-between border-t border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)] px-3 py-2 text-sm font-semibold text-[var(--ui-secondary)]">
                            <span>Gesamt</span>
                            <span class="whitespace-nowrap tabular-nums">{{ number_format($detail->total_amount, 2, ',', '.') }} €</span>
                        </div>
                    </section>
                @endif
            </div>
        @endif

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                @if ($this->printingAvailable && $this->detailBooking)
                    <x-ui-button variant="secondary-outline" size="sm" wire:click="openPrintModal({{ $this->detailBooking->id }})">
                        @svg('heroicon-o-printer', 'w-4 h-4')
                        <span>Bon drucken</span>
                    </x-ui-button>
                @endif
                <x-ui-button variant="secondary-outline" size="sm" wire:click="$set('showDetail', false)">Schließen</x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>

    {{-- Bon drucken: Drucker/Gruppe wählen --}}
    <x-ui-modal size="sm" wire:model="printModalShow">
        <x-slot name="header">
            <div class="flex items-center gap-3">
                <div class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-[var(--ui-primary-10)] flex-shrink-0">
                    @svg('heroicon-o-printer', 'w-5 h-5 text-[var(--ui-primary)]')
                </div>
                <div class="min-w-0">
                    <h3 class="text-base font-semibold text-[var(--ui-secondary)] m-0 leading-tight">Bon drucken</h3>
                    <p class="text-[12px] text-[var(--ui-muted)] m-0 mt-0.5">Buchung als Beleg an einen Drucker senden</p>
                </div>
            </div>
        </x-slot>

        <div class="space-y-4">
            {{-- Ziel: Einzeldrucker oder Gruppe --}}
            <div class="inline-flex overflow-hidden rounded-lg border border-[var(--ui-border)]">
                <button type="button" wire:click="$set('printTarget', 'printer')" class="px-3 py-1.5 text-sm {{ $printTarget === 'printer' ? 'bg-[var(--ui-primary)] text-white' : 'text-[var(--ui-secondary)]' }}">Drucker</button>
                <button type="button" wire:click="$set('printTarget', 'group')" class="px-3 py-1.5 text-sm {{ $printTarget === 'group' ? 'bg-[var(--ui-primary)] text-white' : 'text-[var(--ui-secondary)]' }}">Gruppe</button>
            </div>

            @if ($printTarget === 'printer')
                @if ($this->printers->isEmpty())
                    <p class="text-sm text-[var(--ui-muted)] m-0">Kein Drucker verfügbar.</p>
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
                    <p class="text-sm text-[var(--ui-muted)] m-0">Keine Drucker-Gruppe verfügbar.</p>
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
            <div class="flex justify-end gap-2">
                <x-ui-button variant="secondary-outline" size="sm" wire:click="closePrintModal">Abbrechen</x-ui-button>
                <x-ui-button variant="primary" size="sm" wire:click="printBookingConfirm">
                    @svg('heroicon-o-printer', 'w-4 h-4')
                    <span>Drucken</span>
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>

    </div>
    </x-ui-page-container>
</x-ui-page>
