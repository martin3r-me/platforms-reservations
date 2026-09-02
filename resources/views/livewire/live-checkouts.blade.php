{{-- Gerade im Bestellweg.

     Der Rahmen mit wire:poll steht IMMER, die Karte darin nur, wenn etwas
     läuft. Anders herum ginge es nicht: Verschwände auch der Rahmen, hörte
     das Nachladen auf, und die Karte käme nie zurück.

     Ein leerer Kasten „0 Gäste im Bestellweg" fehlt hier bewusst. Er stünde an
     den meisten Tagen den ganzen Tag da und sagte nichts. --}}
<div wire:poll.15s>
    @php
        $currency = strtoupper((string) config('reservation.currency', 'EUR'));
        $sym      = $currency === 'EUR' ? '€' : $currency;
        $summe    = $this->summe;
    @endphp

    @if ($this->laufende->isNotEmpty())
        <x-nx-card flush>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-[color:var(--nx-line)] px-4 py-3">
                {{-- Punkt statt Symbol: dasselbe Zeichen wie am Zugangs-Knopf
                     oben, und es sagt in einem Pixel, was der Kasten ist –
                     etwas, das jetzt gerade passiert. Farbe per style, damit
                     sie nicht am CSS-Build der Wirtsanwendung hängt. --}}
                <span class="h-2 w-2 shrink-0 rounded-full" style="background: var(--nx-success)"></span>
                <h2 class="m-0 text-sm font-semibold text-[color:var(--nx-text)]">Gerade im Bestellweg</h2>
                <span class="text-xs tabular-nums text-[color:var(--nx-faint)]">
                    {{ $summe['anzahl'] }} {{ $summe['anzahl'] === 1 ? 'Vorgang' : 'Vorgänge' }}
                    @if ($summe['gaeste'] > 0)
                        · {{ $summe['gaeste'] }} {{ $summe['gaeste'] === 1 ? 'Gast' : 'Gäste' }}
                    @endif
                    @if ($summe['warenkorb'] > 0)
                        · {{ number_format($summe['warenkorb'], 2, ',', '.') }} {{ $sym }} in den Warenkörben
                    @endif
                </span>
                <span class="ml-auto text-[11px] text-[color:var(--nx-faint)]">aktualisiert sich alle 15 Sek.</span>
            </div>

            {{-- Die Zeile ist ein Knopf, wenn es etwas zu sehen gibt.

                 Ein Knopf, der ein leeres Fenster oeffnet, waere eine
                 Sackgasse - also ist er bei leerem Warenkorb abgeschaltet und
                 sieht auch so aus. w-full und text-left, weil ein <button>
                 sonst weder die Breite noch die Ausrichtung der Zeile erbt. --}}
            @foreach ($this->laufende as $vorgang)
                @php($hatKorb = $vorgang->hatWarenkorb())
                <button type="button" wire:key="live-{{ $vorgang->id }}"
                    @if ($hatKorb) wire:click="warenkorbZeigen({{ $vorgang->id }})" @else disabled @endif
                    @class([
                        'flex w-full items-center justify-between gap-3 border-b border-[color:var(--nx-line)] px-4 py-2.5 text-left last:border-0',
                        'transition-colors hover:bg-[color:var(--nx-hover)]' => $hatKorb,
                        'cursor-default' => ! $hatKorb,
                    ])>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            {{-- Der Schritt IST die Zeile. Wer hier hersieht, will
                                 wissen, wo jemand steht – nicht, wer er ist. Einen
                                 Namen gibt es hier auch gar nicht: Er wird erst im
                                 vorletzten Schritt erfragt und bewusst nicht
                                 gespeichert. --}}
                            <x-nx-badge :variant="$vorgang->fastFertig() ? 'success' : 'neutral'">{{ $vorgang->schrittLabel() }}</x-nx-badge>
                            @if ($vorgang->slot)
                                <span class="truncate text-sm text-[color:var(--nx-text)]">{{ $vorgang->slot->displayLabel() }}</span>
                            @endif
                        </div>
                        <p class="m-0 mt-0.5 text-xs text-[color:var(--nx-muted)]">
                            @if ($vorgang->party_size)
                                {{ $vorgang->party_size }} {{ $vorgang->party_size === 1 ? 'Gast' : 'Gäste' }} ·
                            @endif
                            @if ($vorgang->items_count > 0)
                                {{ $vorgang->items_count }} {{ $vorgang->items_count === 1 ? 'Position' : 'Positionen' }} ·
                                {{ number_format((float) $vorgang->cart_total, 2, ',', '.') }} {{ $sym }}
                            @else
                                Warenkorb noch leer
                            @endif
                        </p>
                    </div>

                    {{-- Wie weit, und wie lange schon.

                         „Sitzplatz" allein sagt nicht, ob jemand fast fertig
                         ist: Bei einem Termin mit einer Pause ist das der
                         zweite von vier Schritten, bei zweien der dritte von
                         fünf. Beide Zahlen kommen vom Shop – hier wird nichts
                         nachgerechnet, sonst liefen zwei Fassungen derselben
                         Schritt-Liste auseinander.

                         Die Dauer zählt ab dem ERSTEN Lebenszeichen, nicht ab
                         dem letzten: Die Frage lautet „wie lange hängt der
                         schon?".

                         Feste Zeilenhöhe und block, wie in den Karten der
                         Startseite: Ein inline-Element erbt hier sonst eine 24px
                         hohe Zeilenbox und schiebt die Zeile auseinander. --}}
                    <div class="shrink-0 whitespace-nowrap text-right" style="line-height:1rem">
                        @if ($vorgang->fortschritt())
                            <span class="block text-xs tabular-nums text-[color:var(--nx-muted)]">{{ $vorgang->fortschritt() }}</span>
                        @endif
                        <span class="block text-[11px] tabular-nums text-[color:var(--nx-faint)]">
                            @if ($vorgang->dauerMinuten() < 1)
                                gerade eben
                            @else
                                seit {{ $vorgang->dauerMinuten() }} Min.
                            @endif
                        </span>
                    </div>
                </button>
            @endforeach

            {{-- Der wichtigste Satz der Karte.

                 Ohne ihn liest jemand „Sitzplatz · 4 Gäste" und hält vier Plätze
                 für weg. Ein Bestellweg hält nichts frei – vergeben wird erst
                 beim Bestellen, und zwei Gäste können bis dahin denselben Tisch
                 im Blick haben. Aus demselben Grund steht der gewählte Tisch
                 hier gar nicht erst: Er wäre die interessanteste Zahl und
                 genau deshalb die gefährlichste. --}}
            <p class="m-0 px-4 py-2.5 text-[11px] text-[color:var(--nx-faint)]">
                Nichts davon ist reserviert – ein laufender Bestellweg hält keinen Platz frei.
            </p>
        </x-nx-card>
    @endif

    {{-- Was im Korb liegt.

         Steht ausserhalb der Karte: Laeuft der Vorgang waehrend des Hinsehens
         aus oder wird er bestellt, verschwindet die Karte - das Fenster soll
         dann sagen, was passiert ist, statt mitzuverschwinden. --}}
    <x-nx-modal size="sm" wire:model="showWarenkorb">
        <x-slot name="header">Warenkorb</x-slot>

        @if ($this->offener)
            <div class="text-sm">
                <p class="m-0 text-[color:var(--nx-muted)]">
                    {{ $this->offener->schrittLabel() }}
                    @if ($this->offener->fortschritt()) · {{ $this->offener->fortschritt() }} @endif
                    @if ($this->offener->party_size) · {{ $this->offener->party_size }} {{ $this->offener->party_size === 1 ? 'Gast' : 'Gäste' }} @endif
                </p>

                @foreach ($this->warenkorb as $abschnitt)
                    <div wire:key="korb-{{ $loop->index }}" class="mt-4">
                        @if ($abschnitt['pause'])
                            <p class="m-0 mb-1.5 text-xs font-semibold text-[color:var(--nx-muted)]">{{ $abschnitt['pause'] }}</p>
                        @endif
                        @foreach ($abschnitt['zeilen'] as $zeile)
                            <div class="flex items-baseline gap-2 border-b border-[color:var(--nx-line)] py-1.5 last:border-0">
                                <span class="w-8 shrink-0 tabular-nums text-[color:var(--nx-muted)]">{{ $zeile['menge'] }} ×</span>
                                <span class="text-[color:var(--nx-text)]">{{ $zeile['name'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @endforeach

                <p class="m-0 mt-4 tabular-nums text-[color:var(--nx-text)]">
                    Zwischensumme {{ number_format((float) $this->offener->cart_total, 2, ',', '.') }} {{ $sym }}
                </p>

                {{-- Der Satz muss hier stehen, nicht nur auf der Karte.

                     Im Fenster sieht es aus wie eine Bestellung: Positionen,
                     Mengen, eine Summe. Es ist keine. Der Gast kann alles noch
                     ändern, und die Summe ist die des Shops – der Beleg rechnet
                     Bundles in ihre Bestandteile auf und kann davon abweichen. --}}
                <p class="m-0 mt-2 text-[11px] text-[color:var(--nx-faint)]">
                    Nichts davon ist bestellt – der Gast steht noch im Bestellweg
                    und kann alles ändern.
                </p>
            </div>
        @else
            <p class="m-0 text-sm text-[color:var(--nx-muted)]">
                Dieser Bestellweg läuft nicht mehr – der Gast hat entweder bestellt
                oder das Fenster geschlossen.
            </p>
        @endif

        <x-slot name="footer">
            <x-nx-button wire:click="warenkorbSchliessen()">Schließen</x-nx-button>
        </x-slot>
    </x-nx-modal>
</div>
