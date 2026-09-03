<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Bestellwege" icon="heroicon-o-arrow-trending-down" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Bestellwege'],
        ]" />
    </x-slot>

    <x-ui-page-container width="contained">
    @php
        $currency = strtoupper((string) config('reservation.currency', 'EUR'));
        $sym      = $currency === 'EUR' ? '€' : $currency;
        $summe    = $this->summe;
    @endphp

    <div class="space-y-5">

    {{-- Zeitraum: dieselbe Leiste wie in der Artikel-Auswertung und im Export --}}
    @include('reservation::partials.zeitraum-leiste', [
        'presets' => \Platform\Reservation\Livewire\CheckoutStats::presets(),
        'aktiv'   => $activePreset,
    ])

    {{-- Kennzahlen. Der Zeitraum meint das ENDE des Bestellwegs, nicht das
         Datum der Veranstaltung: Wer im Mai für ein Konzert im September
         bestellt, zählt in den Mai. Danach fragt man hier auch – es geht um das
         Verhalten im Bestellweg, nicht um den Abend. --}}
    <div class="grid grid-cols-2 gap-x-4 gap-y-4 border-y border-[color:var(--nx-line)] py-4 sm:grid-cols-4">
        <div>
            <div class="text-2xl font-bold leading-none tabular-nums text-[color:var(--nx-text)]">{{ $summe['gesamt'] }}</div>
            <div class="mt-1.5 text-xs text-[color:var(--nx-muted)]">Bestellwege</div>
        </div>
        <div>
            <div class="text-2xl font-bold leading-none tabular-nums" style="color:var(--nx-success)">{{ $summe['bestellt'] }}</div>
            <div class="mt-1.5 text-xs text-[color:var(--nx-muted)]">bestellt</div>
        </div>
        <div>
            <div class="text-2xl font-bold leading-none tabular-nums text-[color:var(--nx-text)]">{{ $summe['abgebrochen'] }}</div>
            <div class="mt-1.5 text-xs text-[color:var(--nx-muted)]">abgebrochen</div>
        </div>
        <div>
            <div class="text-2xl font-bold leading-none tabular-nums text-[color:var(--nx-text)]">{{ number_format($summe['quote'], 1, ',', '.') }} %</div>
            <div class="mt-1.5 text-xs text-[color:var(--nx-muted)]">kommen durch</div>
        </div>
    </div>

    @if ($summe['gesamt'] === 0)
        <x-nx-card>
            <x-nx-empty icon="heroicon-o-arrow-trending-down">
                In diesem Zeitraum wurde kein Bestellweg beendet.
            </x-nx-empty>
        </x-nx-card>
    @else
        {{-- Wo abgebrochen wird --}}
        <x-nx-card flush>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-[color:var(--nx-line)] px-4 py-3">
                @svg('heroicon-o-arrow-trending-down', 'w-4 h-4 shrink-0 text-[color:var(--nx-muted)]')
                <h2 class="m-0 text-sm font-semibold text-[color:var(--nx-text)]">Wo abgebrochen wird</h2>
                @if ($this->schlimmster)
                    <span class="text-xs text-[color:var(--nx-faint)]">
                        Am häufigsten bei „{{ $this->schlimmster['label'] }}" –
                        {{ number_format($this->schlimmster['anteil'], 1, ',', '.') }} % aller Abbrüche
                    </span>
                @endif
            </div>

            @foreach ($this->schritte as $schritt)
                <div wire:key="schritt-{{ $schritt['step'] }}" class="border-b border-[color:var(--nx-line)] px-4 py-3 last:border-0">
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="text-sm font-medium text-[color:var(--nx-text)]">{{ $schritt['label'] }}</span>
                        <span class="shrink-0 whitespace-nowrap text-xs tabular-nums text-[color:var(--nx-muted)]">
                            {{ $schritt['anzahl'] }} {{ $schritt['anzahl'] === 1 ? 'Abbruch' : 'Abbrüche' }} ·
                            {{ number_format($schritt['anteil'], 1, ',', '.') }} %
                        </span>
                    </div>

                    <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-[color:var(--nx-bg)]">
                        <div class="h-full rounded-full transition-all"
                            style="width:{{ min(100, $schritt['anteil']) }}%; background:var(--nx-accent)"></div>
                    </div>

                    <p class="m-0 mt-1.5 text-[11px] text-[color:var(--nx-faint)]">
                        @if ($schritt['warenkorb'] > 0)
                            {{ number_format($schritt['warenkorb'], 2, ',', '.') }} {{ $sym }} lagen im Korb ·
                        @endif
                        {{-- „0 Sek." las sich wie ein Messfehler. Es ist keiner:
                             So sieht ein Bestellweg aus, der sich genau einmal
                             gemeldet hat und nie wieder – die Dauer zählt vom
                             ersten bis zum letzten Lebenszeichen. Der Satz sagt
                             das jetzt, statt eine Null hinzustellen. --}}
                        @if ($schritt['dauer'] < 60)
                            sofort weg – im Schnitt keine Minute bis zum Abbruch
                        @else
                            im Schnitt {{ round($schritt['dauer'] / 60) }} Min. bis zum Abbruch
                        @endif
                    </p>
                </div>
            @endforeach

            {{-- Der Absatz, ohne den jemand aus dem Balken einen Prozentsatz
                 herausliest, den es nicht gibt.

                 Die Prozentzahl ist der Anteil AN ALLEN ABBRÜCHEN, nicht die
                 Quote derer, die den Schritt erreicht haben. Diese zweite Zahl
                 wäre hier eine Erfindung: Ein Bestellweg mit einer Pause hat
                 vier Schritte, einer mit zweien fünf, und den Schritt „Pause"
                 gibt es im ersten Fall gar nicht. Ein gemeinsamer Nenner sähe
                 nur so aus, als wäre er einer. --}}
            <p class="m-0 border-t border-[color:var(--nx-line)] px-4 py-2.5 text-[11px] text-[color:var(--nx-faint)]">
                Der Prozentwert ist der Anteil an allen Abbrüchen – nicht die Quote derer,
                die den Schritt erreicht haben. Der Betrag im Korb ist kein entgangener
                Umsatz: Ob der Gast ihn bezahlt hätte, weiß niemand.
            </p>
        </x-nx-card>

        {{-- Termine --}}
        <x-nx-card flush>
            <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
                @svg('heroicon-o-ticket', 'w-4 h-4 text-[color:var(--nx-muted)]')
                <h2 class="m-0 text-sm font-semibold text-[color:var(--nx-text)]">Termine mit den meisten Abbrüchen</h2>
            </div>

            @foreach ($this->termine as $termin)
                <div wire:key="termin-{{ $loop->index }}" class="flex items-center justify-between gap-3 border-b border-[color:var(--nx-line)] px-4 py-2.5 last:border-0">
                    <div class="min-w-0">
                        <span class="truncate text-sm font-medium text-[color:var(--nx-text)]">{{ $termin['name'] }}</span>
                        <p class="m-0 mt-0.5 text-xs text-[color:var(--nx-muted)]">
                            @if ($termin['datum'])
                                {{ \Carbon\CarbonImmutable::parse($termin['datum'])->locale('de')->isoFormat('dd, D. MMM Y') }} ·
                            @endif
                            {{ $termin['bestellt'] }} bestellt · {{ number_format($termin['quote'], 1, ',', '.') }} % kommen durch
                        </p>
                    </div>
                    <div class="shrink-0 whitespace-nowrap text-right" style="line-height:1rem">
                        <span class="block text-xs font-semibold tabular-nums text-[color:var(--nx-text)]">{{ $termin['abgebrochen'] }}</span>
                        <span class="block text-[11px] text-[color:var(--nx-faint)]">Abbrüche</span>
                    </div>
                </div>
            @endforeach

            {{-- Sortiert nach der ZAHL, nicht nach der Quote: Ein Termin mit
                 einem Bestellweg und einem Abbruch hätte 100 % und stünde ganz
                 oben, ohne dass daran etwas zu sehen wäre. --}}
            <p class="m-0 border-t border-[color:var(--nx-line)] px-4 py-2.5 text-[11px] text-[color:var(--nx-faint)]">
                Sortiert nach der Zahl der Abbrüche, nicht nach der Quote – ein Termin mit
                einem einzigen Bestellweg stünde sonst ganz oben.
            </p>
        </x-nx-card>
    @endif

    </div>
    </x-ui-page-container>
</x-ui-page>
