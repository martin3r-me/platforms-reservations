{{-- Rückfrage vor No-Show und vor dem Zurücknehmen.
     Gehört zu Concerns\ChangesBookingStatus – wer den Trait einbindet, bindet
     auch dieses Partial ein.

     Als Dialog im Haus-Design statt wire:confirm: Das öffnet den Kasten des
     Browsers, der nach der Domain aussieht und nicht nach dem Programm. --}}
@php
    $sb = $this->statusBooking;
    $reopenZiel = $this->reopenTargetStatus($sb);
@endphp
<x-nx-modal size="sm" wire:model="statusModalShow">
    <x-slot name="header">
        <h3 class="m-0 text-base font-semibold leading-tight text-[color:var(--nx-text)]">
            @switch($statusAction)
                @case('no_show') Als No-Show markieren @break
                @case('cancel')  Buchung stornieren @break
                @default         Status zurücknehmen
            @endswitch
        </h3>
        @if ($sb)
            <p class="m-0 mt-1 text-xs text-[color:var(--nx-muted)]">
                {{ $sb->guest_name }} · {{ $sb->date->format('d.m.Y') }}@if ($sb->time_start) · {{ substr($sb->time_start, 0, 5) }} Uhr @endif
                @if ($sb->table) · Tisch {{ $sb->table->label }} @endif
            </p>
        @endif
    </x-slot>

    @if ($statusAction === 'no_show')
        <x-nx-callout variant="warning">
            Die Buchung zählt danach nicht mehr für <strong>Umsatz</strong>,
            <strong>Küche</strong> und <strong>Platzprüfung</strong>.
            Zurücknehmen geht über dasselbe Menü.
        </x-nx-callout>
    @elseif ($statusAction === 'cancel')
        {{-- Der einzige Wechsel ohne Rückweg im Menü, und der einzige, der
             den Platz wieder freigibt. Beides gehört vor den Klick. --}}
        <x-nx-callout variant="danger">
            Die Buchung fällt aus <strong>Umsatz</strong>, <strong>Küche</strong>
            und <strong>Platzprüfung</strong>, und der <strong>Platz wird wieder
            frei</strong> – er kann danach neu gebucht werden. Über das Menü gibt
            es keinen Weg zurück. Eine Rückzahlung löst das hier nicht aus.
        </x-nx-callout>
    @elseif ($statusAction === 'reopen')
        @if ($reopenZiel === \Platform\Reservation\Models\Booking::STATUS_CONFIRMED)
            <x-nx-callout variant="info">
                Die Bestellung ist bezahlt – die Buchung geht zurück auf
                <strong>Bestätigt</strong> und zählt wieder mit.
            </x-nx-callout>
        @else
            {{-- Der wichtigere der beiden Fälle: Wer „zurücknehmen" liest,
                 erwartet „bestätigt". Dass es ausstehend wird, muss vorher
                 dastehen und nicht hinterher auffallen. --}}
            <x-nx-callout variant="warning">
                Für diese Bestellung ist <strong>kein Zahlungseingang</strong> verbucht.
                Die Buchung geht deshalb auf <strong>Ausstehend</strong> – nicht auf
                Bestätigt. Bestätigt hieße hier „bezahlt", und das lässt sich von Hand
                nicht behaupten.
            </x-nx-callout>
        @endif
    @endif

    <x-slot name="footer">
        <x-nx-button wire:click="closeStatusModal">Abbrechen</x-nx-button>
        <x-nx-button :variant="in_array($statusAction, ['no_show', 'cancel'], true) ? 'danger' : 'primary'" wire:click="confirmStatusChange">
            @if ($statusAction === 'no_show')
                @svg('heroicon-o-user-minus', 'w-4 h-4')
                <span>Als No-Show markieren</span>
            @elseif ($statusAction === 'cancel')
                @svg('heroicon-o-x-mark', 'w-4 h-4')
                <span>Stornieren</span>
            @else
                @svg('heroicon-o-arrow-uturn-left', 'w-4 h-4')
                <span>Auf {{ $reopenZiel === \Platform\Reservation\Models\Booking::STATUS_CONFIRMED ? 'Bestätigt' : 'Ausstehend' }} setzen</span>
            @endif
        </x-nx-button>
    </x-slot>
</x-nx-modal>
