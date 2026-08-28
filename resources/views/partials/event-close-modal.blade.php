{{-- Rückfrage vor „Abend abschließen".

     Der Dialog nennt beide Zahlen: was gesetzt wird und was liegen bleibt.
     Ohne das wäre nicht zu sehen, dass ausstehende Buchungen bewusst
     unangetastet bleiben – und genau die sind die unbezahlten. --}}
@php
    $zuSchliessen = $this->offeneBestaetigte;
    $bleibenOffen = $this->offeneAusstehende;
    $inZukunft    = $this->event->date?->isFuture() ?? false;
@endphp
<x-nx-modal size="sm" wire:model="showCloseEventModal">
    <x-slot name="header">
        <h3 class="m-0 text-base font-semibold leading-tight text-[color:var(--nx-text)]">Abend abschließen</h3>
        <p class="m-0 mt-1 text-xs text-[color:var(--nx-muted)]">
            {{ $this->event->name }}@if ($this->event->date) · {{ $this->event->date->format('d.m.Y') }}@endif
        </p>
    </x-slot>

    <div class="space-y-3">
        @if ($zuSchliessen === 0)
            <x-nx-callout variant="info">
                Es ist keine bestätigte Buchung mehr offen – hier gibt es nichts abzuschließen.
            </x-nx-callout>
        @else
            <x-nx-callout variant="info">
                <strong>{{ $zuSchliessen }}</strong> {{ $zuSchliessen === 1 ? 'bestätigte Buchung wird' : 'bestätigte Buchungen werden' }}
                auf <strong>Abgeschlossen</strong> gesetzt. Am Umsatz ändert das nichts –
                abgeschlossene Buchungen zählen wie bestätigte.
                Einzelne lassen sich danach über das Menü in der Zeile zurücknehmen.
            </x-nx-callout>

            {{-- Die eigentliche Warnung: Wer den Knopf drückt, bevor die
                 Fehlenden markiert sind, hat sie hinterher als „abgeschlossen"
                 in der Liste stehen und sieht nicht mehr, wer wirklich da war. --}}
            <x-nx-callout variant="warning">
                Vorher die <strong>No-Shows markieren</strong>. Was jetzt bestätigt ist,
                gilt danach als erschienen.
            </x-nx-callout>

            @if ($bleibenOffen > 0)
                <x-nx-callout variant="warning">
                    <strong>{{ $bleibenOffen }}</strong> {{ $bleibenOffen === 1 ? 'ausstehende Buchung bleibt' : 'ausstehende Buchungen bleiben' }}
                    unverändert – dort ist keine Zahlung verbucht.
                </x-nx-callout>
            @endif

            @if ($inZukunft)
                <x-nx-callout variant="warning">
                    Der Termin liegt noch <strong>in der Zukunft</strong>. Sicher, dass der Abend schon vorbei ist?
                </x-nx-callout>
            @endif
        @endif
    </div>

    <x-slot name="footer">
        <x-nx-button wire:click="closeCloseEventModal">Abbrechen</x-nx-button>
        @if ($zuSchliessen > 0)
            <x-nx-button variant="primary" wire:click="confirmCloseEvent">
                @svg('heroicon-o-check-circle', 'w-4 h-4')
                <span>{{ $zuSchliessen }} {{ $zuSchliessen === 1 ? 'Buchung' : 'Buchungen' }} abschließen</span>
            </x-nx-button>
        @endif
    </x-slot>
</x-nx-modal>
