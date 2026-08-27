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
                        <x-nx-table-cell align="right">
                            {{-- Aktionen erscheinen beim Hover über die Zeile (Notion-Stil) --}}
                            {{-- Klick auf die Zeile öffnet Details; diese Aktionen stoppen daher den Zeilen-Klick --}}
                            {{-- Der Alpine-Zustand sitzt am ganzen Aktionsblock, nicht nur am
                                 Menü: Solange das Menü offen ist, muss der Block sichtbar
                                 bleiben. Sonst blendet ihn das group-hover aus, sobald der
                                 Zeiger auf dem Weg zum Menüeintrag die Zeile verlässt – das
                                 Menü wäre offen und unsichtbar. --}}
                            <div class="flex items-center justify-end gap-0.5 opacity-0 transition-opacity duration-150 group-hover:opacity-100 focus-within:opacity-100"
                                x-data="{
                                    open: false,
                                    oben: 0,
                                    rechts: 0,
                                    auf() {
                                        /* Menue haengt frei am Fenster (siehe unten), seine
                                           Lage kommt daher beim Oeffnen aus dem Knopf. */
                                        const r = this.$refs.kebab.getBoundingClientRect();
                                        this.oben = r.bottom + 6;
                                        this.rechts = window.innerWidth - r.right;
                                        this.open = true;
                                    },
                                }"
                                :style="open ? { opacity: 1 } : {}"
                                @keydown.escape.window="open = false"
                                {{-- .capture, weil Scroll-Ereignisse nicht aufsteigen: Das
                                     Programm scrollt in einem inneren Kasten, nicht am Fenster.
                                     Ohne das bliebe das Menü beim Scrollen stehen, während die
                                     Zeile darunter wegwandert. --}}
                                @scroll.window.capture="open = false"
                                @resize.window="open = false">
                                @if ($booking->status === 'pending')
                                    <x-nx-button icon variant="ghost" wire:click.stop="confirmBooking({{ $booking->id }})" title="Bestätigen">
                                        @svg('heroicon-o-check', 'w-4 h-4')
                                    </x-nx-button>
                                @endif

                                {{-- Statuswechsel im Kebab-Menü. Nicht als eigene Knöpfe in der
                                     Zeile: No-Show und Abgeschlossen werden selten geklickt,
                                     Bestätigen und Bon drucken dauernd – fünf Symbole
                                     nebeneinander machen die häufigen schwerer zu treffen.

                                     Bei stornierten Buchungen entfällt das Menü: Wer abgesagt
                                     hat, kann weder fehlen noch teilnehmen. --}}
                                @if ($booking->status !== 'cancelled')
                                    <div @click.stop>
                                        <x-nx-button icon variant="ghost" type="button" x-ref="kebab"
                                            @click="open ? open = false : auf()" title="Status ändern">
                                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <circle cx="10" cy="4" r="1.4"/><circle cx="10" cy="10" r="1.4"/><circle cx="10" cy="16" r="1.4"/>
                                            </svg>
                                        </x-nx-button>

                                        {{-- "fixed" statt "absolute", anders als bei x-nx-dropdown:
                                             Die Tabelle steckt in einem Kasten mit
                                             "overflow-x-auto", und der beschneidet nach CSS auch
                                             senkrecht. Ein Menü in den unteren Zeilen wäre dort
                                             abgeschnitten. Frei am Fenster hängend ist es das
                                             nicht – der Preis ist die Lage von Hand (auf()) und
                                             das Schließen beim Scrollen.

                                             Aussehen und der Name "open" bewusst wie in
                                             x-nx-dropdown: Nur so passen die x-nx-dropdown-item
                                             darin, die selbst "open = false" setzen. --}}
                                        <div x-show="open" style="display:none" x-transition
                                            @click.outside="open = false"
                                            :style="{ top: oben + 'px', right: rechts + 'px' }"
                                            class="fixed z-50 w-56 rounded-[8px] border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] p-1 shadow-[var(--nx-shadow-pop)]">
                                            @if ($booking->status !== 'no_show')
                                                <x-nx-dropdown-item wire:click="askNoShow({{ $booking->id }})">
                                                    @svg('heroicon-o-user-minus', 'w-4 h-4') <span>No-Show</span>
                                                </x-nx-dropdown-item>
                                            @endif
                                            @if ($booking->status !== 'completed')
                                                <x-nx-dropdown-item wire:click="markCompleted({{ $booking->id }})">
                                                    @svg('heroicon-o-check-circle', 'w-4 h-4') <span>Abgeschlossen</span>
                                                </x-nx-dropdown-item>
                                            @endif
                                            @if (in_array($booking->status, ['no_show', 'completed'], true))
                                                <x-nx-dropdown-divider />
                                                {{-- Nicht "Zurück auf Bestätigt": Wohin es geht, hängt
                                                     an der Bestellung – bei unbezahlten Shop-Buchungen
                                                     auf ausstehend. Die Rückfrage sagt es. --}}
                                                <x-nx-dropdown-item wire:click="askReopen({{ $booking->id }})">
                                                    @svg('heroicon-o-arrow-uturn-left', 'w-4 h-4') <span>Zurücknehmen</span>
                                                </x-nx-dropdown-item>
                                            @endif
                                        </div>
                                    </div>
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
                            {{-- Ein Bundle ist EINE Position mit seinem Preis, darunter der
                                 Inhalt. Ohne die Aufbereitung stuenden hier die internen
                                 Aufteilungsbetraege: "2× BIER à 5,54 €" und "1× BIER à 5,53 €"
                                 fuer dieselben drei Biere. --}}
                            @foreach ($this->detailBlocks() as $block)
                                <div wire:key="detail-block-{{ $loop->index }}" class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                                    <div class="min-w-0">
                                        <span class="text-[color:var(--nx-text)]">
                                            <span class="font-semibold tabular-nums">{{ $block['quantity'] }}×</span>
                                            {{ $block['name'] }}
                                        </span>
                                        @if ($block['is_bundle'] && $block['contents'])
                                            <p class="m-0 text-xs text-[color:var(--nx-muted)]">
                                                {{ \Platform\Reservation\Support\BookingItemsPresenter::contentsLabel($block['contents']) }}
                                            </p>
                                        @endif
                                        @foreach ($block['notes'] as $note)
                                            <p class="m-0 text-xs text-[color:var(--nx-muted)]">{{ $note }}</p>
                                        @endforeach
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <span class="whitespace-nowrap tabular-nums text-[color:var(--nx-text)]">{{ number_format($block['total'], 2, ',', '.') }} €</span>
                                        {{-- Beim Bundle kein Einzelpreis und kein Steuersatz:
                                             Es hat weder einen sinnvollen Einzelpreis noch EINEN Satz. --}}
                                        @unless ($block['is_bundle'])
                                            <span class="block text-[11px] tabular-nums text-[color:var(--nx-muted)]">
                                                à {{ number_format($block['unit_price'], 2, ',', '.') }} € · {{ rtrim(rtrim(number_format($block['tax_rate'], 2, '.', ''), '0'), '.') }} %
                                            </span>
                                        @endunless
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

    @include('reservation::partials.booking-print-modal')

    @include('reservation::partials.booking-status-modal')

    </div>
    </x-ui-page-container>
</x-ui-page>
