{{-- Auslastung eines Raums in EINER Pause: Balken, Zahlen, Tische.

     Steht bewusst in der Pausen-Karte und nicht oben bei den Kennzahlen:
     Gezeigt wird je Pause, eine Zahl ueber den ganzen Abend gaebe es nicht.

     Ob der Raum zur naechsten Pause wieder leer ist, entscheidet die
     Tischbindung des Termins. Gehoert der Tisch dem Gast den ganzen Abend,
     steht unter dem Balken eine zweite Zahl. --}}
@props(['raeume' => []])

@if (! empty($raeume))
    <div class="space-y-4 border-b border-[color:var(--nx-line)] px-4 py-4">
        @foreach ($raeume as $raum)
            @php
                // Ab 90 % wird es eng, ab 100 % ist zu. Der Farbwechsel spart
                // das Nachrechnen, wenn waehrend des Abends jemand fragt, ob
                // noch etwas geht.
                $ton = $raum['percent'] >= 100
                    ? 'var(--nx-success)'
                    : ($raum['percent'] >= 90 ? 'var(--nx-warning)' : 'var(--nx-accent)');
            @endphp

            @php
                // Ein geschlossener Raum wird blasser dargestellt. Er steht
                // trotzdem da: Dass er zu ist, ist die Information.
                $zu = ! ($raum['open'] ?? true);
            @endphp

            <div wire:key="auslastung-{{ $loop->index }}" @class(['opacity-60' => $zu])>
                <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                    <span class="text-xs font-medium text-[color:var(--nx-text)]">{{ $raum['name'] }}</span>
                    @if ($zu)
                        <span class="inline-flex items-center gap-1 rounded-full border border-[color:var(--nx-line)] px-1.5 py-0.5 text-[10px] font-medium text-[color:var(--nx-muted)]">
                            @svg('heroicon-o-lock-closed', 'w-3 h-3')
                            <span>noch geschlossen</span>
                        </span>
                    @endif
                    <span class="text-xs tabular-nums text-[color:var(--nx-muted)]">
                        {{ $raum['booked'] }} von {{ $raum['seats'] }} Plätzen belegt
                    </span>
                    <span class="ml-auto text-xs font-semibold tabular-nums" style="color:{{ $ton }}">
                        {{ $raum['percent'] }} %
                    </span>
                </div>

                <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-[color:var(--nx-bg)]">
                    <div class="h-full rounded-full transition-all"
                        style="width:{{ min(100, $raum['percent']) }}%; background:{{ $ton }}"></div>
                </div>

                <p class="m-0 mt-1.5 text-[11px] text-[color:var(--nx-faint)]">
                    {{ $raum['free'] }} {{ $raum['free'] === 1 ? 'Platz' : 'Plätze' }} frei
                    @if (! empty($raum['hinweis'])) · {{ $raum['hinweis'] }} @endif
                </p>

                {{-- Zweite Zahl, aber nur wenn sie eine andere ist.

                     Gehört der Tisch dem Gast den ganzen Abend, kann ein Platz
                     belegt sein, ohne dass in DIESER Pause etwas bestellt wurde.
                     Für die Kapazität zählt „belegt", für die Küche „bestellt" –
                     ohne diesen Satz leitet jemand aus der Auslastung eine
                     Menge ab, die es nicht gibt.

                     Bei einer Pause und bei Bindung an die Pause sind beide
                     Zahlen gleich; dann steht hier nichts. --}}
                @if (($raum['ordered'] ?? $raum['booked']) !== $raum['booked'])
                    <p class="m-0 mt-1 text-[11px] text-[color:var(--nx-muted)]">
                        @if ($raum['ordered'] > 0)
                            Davon {{ $raum['ordered'] }} mit Bestellung in dieser Pause.
                            {{ $raum['booked'] - $raum['ordered'] === 1 ? 'Der andere Platz ist' : 'Die anderen ' . ($raum['booked'] - $raum['ordered']) . ' Plätze sind' }}
                            belegt, weil die Gäste ihren Tisch den ganzen Abend behalten.
                        @else
                            In dieser Pause hat niemand bestellt – die Plätze sind belegt,
                            weil die Gäste ihren Tisch den ganzen Abend behalten.
                        @endif
                    </p>
                @endif

                {{-- Tische einzeln. Wer am Abend einen Platz sucht, will nicht
                     die Prozentzahl, sondern welcher Tisch noch etwas hergibt. --}}
                <div class="mt-2.5 flex flex-wrap gap-1.5">
                    @foreach ($raum['tables'] as $tisch)
                        @php
                            $stil = match ($tisch['status']) {
                                'voll'      => 'border-color:var(--nx-success); color:var(--nx-success);',
                                'teilweise' => 'border-color:var(--nx-accent); color:var(--nx-accent);',
                                'gesperrt'  => 'border-color:var(--nx-line); color:var(--nx-faint); text-decoration:line-through;',
                                default     => 'border-color:var(--nx-line); color:var(--nx-muted);',
                            };
                        @endphp
                        <span class="inline-flex items-center gap-1 rounded-[6px] border px-1.5 py-0.5 text-[11px] tabular-nums"
                            style="{{ $stil }}"
                            title="{{ $tisch['label'] }} · {{ $tisch['status'] === 'gesperrt' ? 'für diesen Termin gesperrt' : $tisch['booked'] . ' von ' . $tisch['capacity'] . ' Plätzen belegt' . (($tisch['ordered'] ?? $tisch['booked']) !== $tisch['booked'] ? ', davon ' . $tisch['ordered'] . ' mit Bestellung in dieser Pause' : '') }}">
                            <span>{{ $tisch['label'] }}</span>
                            @if ($tisch['status'] === 'gesperrt')
                                <span class="opacity-70">gesperrt</span>
                            @else
                                <span class="opacity-70">{{ $tisch['booked'] }}/{{ $tisch['capacity'] }}</span>
                            @endif
                        </span>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
@endif
