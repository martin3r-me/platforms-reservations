{{-- Detail-Fenster einer Buchung: Kontext, Bestellpositionen, Summe.
     Gehört zu Concerns\ShowsBookingDetail – wer den Trait einbindet, bindet
     auch dieses Partial ein. Setzt zusätzlich PrintsBookingReceipt voraus
     (Fußzeile bietet „Bon drucken" an). --}}
@php
    $detail = $this->detailBooking;
    $sym = ($c = strtoupper((string) config('reservation.currency', 'EUR'))) === 'EUR' ? '€' : $c;
@endphp
<x-nx-modal size="md" wire:model="showDetail">
    <x-slot name="header">
        <h3 class="m-0 text-base font-semibold leading-tight text-[color:var(--nx-text)]">
            Buchung {{ $detail?->guest_name }}
        </h3>
        @if ($detail)
            <p class="m-0 mt-1 text-xs text-[color:var(--nx-muted)]">
                {{ $detail->date->format('d.m.Y') }}@if ($detail->time_start) · {{ substr($detail->time_start, 0, 5) }} Uhr @endif
                @if ($detail->zielortLabel()) · {{ $detail->zielortLabel() }}@if ($detail->zielortFehlt())<span class="ml-1 text-[11px] text-[color:var(--nx-faint)]">(gelöscht)</span>@endif @endif
            </p>
        @endif
    </x-slot>

    @if ($detail)
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
                    <p class="m-0 text-[color:var(--nx-muted)]">Zahlart: {{ ['card' => 'Karte', 'paypal' => 'PayPal', 'applepay' => 'Apple Pay', 'onsite' => 'Vor Ort'][$detail->payment_method] ?? $detail->payment_method }}</p>
                @endif
                @if ($detail->notes)
                    <p class="m-0 text-[color:var(--nx-muted)]">Anmerkung: {{ $detail->notes }}</p>
                @endif
            </div>

            {{-- Teilweise zurueckgegangenes Geld.

                 Nur DIESER Fall: Eine volle Erstattung storniert die Buchung,
                 die steht dann ohnehin als storniert da. Eine teilweise laesst
                 alles bestehen - und dann ist das hier die einzige Stelle, an
                 der jemand ueberhaupt erfaehrt, dass Geld zurueckgegangen ist. --}}
            @if ($detail->order?->payment?->refunded_amount > 0 && $detail->status !== \Platform\Reservation\Models\Booking::STATUS_CANCELLED)
                <x-nx-callout variant="warning">
                    Es wurden bereits
                    <strong>{{ number_format((float) $detail->order->payment->refunded_amount, 2, ',', '.') }} €</strong>
                    von {{ number_format((float) $detail->order->payment->amount, 2, ',', '.') }} € zurückerstattet.
                    Die Buchung besteht weiter – bitte prüfen, was davon noch geliefert wird.
                </x-nx-callout>
            @endif

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
                                    <span class="whitespace-nowrap tabular-nums text-[color:var(--nx-text)]">{{ number_format($block['total'], 2, ',', '.') }} {{ $sym }}</span>
                                    {{-- Beim Bundle kein Einzelpreis und kein Steuersatz:
                                         Es hat weder einen sinnvollen Einzelpreis noch EINEN Satz. --}}
                                    @unless ($block['is_bundle'])
                                        <span class="block text-[11px] tabular-nums text-[color:var(--nx-muted)]">
                                            à {{ number_format($block['unit_price'], 2, ',', '.') }} {{ $sym }} · {{ rtrim(rtrim(number_format($block['tax_rate'], 2, '.', ''), '0'), '.') }} %
                                        </span>
                                    @endunless
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="flex justify-between border-t border-[color:var(--nx-line)] bg-[color:var(--nx-bg)] px-3 py-2 text-sm font-semibold text-[color:var(--nx-text)]">
                        <span>Gesamt</span>
                        <span class="whitespace-nowrap tabular-nums">{{ number_format($detail->total_amount, 2, ',', '.') }} {{ $sym }}</span>
                    </div>
                </section>
            @endif
        </div>
    @endif

    <x-slot name="footer">
        @if ($this->printingAvailable && $detail)
            <x-nx-button wire:click="openPrintModal({{ $detail->id }})">
                @svg('heroicon-o-printer', 'w-4 h-4')
                <span>Bon drucken</span>
            </x-nx-button>
        @endif
        <x-nx-button wire:click="closeDetail">Schließen</x-nx-button>
    </x-slot>
</x-nx-modal>
