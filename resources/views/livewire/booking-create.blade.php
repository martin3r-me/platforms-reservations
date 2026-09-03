<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Neue Buchung" icon="heroicon-o-plus-circle" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Buchungen', 'href' => route('reservation.bookings.index')],
            ['label' => 'Neue Buchung'],
        ]" />
    </x-slot>

    <x-ui-page-container width="contained">
    <div class="max-w-2xl space-y-5">

    {{-- Schrittfolge. Farben über die nx-Variablen und nicht über Indigo-Klassen:
         Sonst hängt die Leiste an einer Farbe, die mit dem Thema nicht wandert. --}}
    @php ($schritte = ['Termin', 'Gast', 'Artikel', 'Bestätigung'])
    <div class="flex items-center">
        @foreach ($schritte as $i => $label)
            @php ($nr = $i + 1)
            @php ($fertig = $step > $nr)
            @php ($aktiv = $step === $nr)
            <div class="flex shrink-0 items-center gap-2">
                <span
                    class="flex h-6 w-6 items-center justify-center rounded-full text-[11px] font-semibold"
                    @if ($fertig)
                        style="background: var(--nx-success); color: #fff;"
                    @elseif ($aktiv)
                        style="background: var(--nx-accent); color: var(--nx-on-accent);"
                    @else
                        style="background: var(--nx-hover); color: var(--nx-muted);"
                    @endif
                >
                    @if ($fertig)
                        @svg('heroicon-o-check', 'w-3.5 h-3.5')
                    @else
                        {{ $nr }}
                    @endif
                </span>
                <span class="text-xs {{ $aktiv ? 'font-medium text-[color:var(--nx-text)]' : 'text-[color:var(--nx-muted)]' }}">
                    {{ $label }}
                </span>
            </div>
            @if ($i < count($schritte) - 1)
                <div class="mx-3 h-px flex-1" style="background: var(--nx-line);"></div>
            @endif
        @endforeach
    </div>

    @if ($bookingError)
        <x-nx-callout variant="danger">{{ $bookingError }}</x-nx-callout>
    @endif

    {{-- Schritt 1: Termin, Pause, Tisch --}}
    @if ($step === 1)
        <x-nx-card flush>
            <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
                @svg('heroicon-o-calendar-days', 'w-4 h-4 text-[color:var(--nx-muted)]')
                <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Termin & Pause</h2>
            </div>
            <div class="space-y-4 p-5">
                @if ($this->events->isEmpty())
                    <x-nx-callout variant="warning">
                        Kein anstehender Termin vorhanden. Eine Buchung hängt immer an einem Termin –
                        bitte zuerst einen anlegen.
                    </x-nx-callout>
                @else
                    <x-nx-input-select
                        name="eventId"
                        label="Termin"
                        required
                        nullable
                        nullLabel="– Termin wählen –"
                        :options="$this->events->map(fn ($e) => [
                            'value' => $e->id,
                            'label' => $e->date?->format('d.m.Y') . ' · ' . $e->name
                                . ($e->status->value === 'closed' ? ' (Bestellschluss)' : '')
                                . ($e->status->value === 'draft' ? ' (Entwurf)' : ''),
                        ])->values()->all()"
                        wire:model.live="eventId"
                    />

                    @if ($this->event)
                        @if ($this->slots->isEmpty())
                            <x-nx-callout variant="warning">
                                Dieser Termin hat noch keine Pause. Ohne Pause lässt sich nicht buchen.
                            </x-nx-callout>
                        @else
                            <x-nx-input-select
                                name="slotId"
                                label="Pause"
                                required
                                nullable
                                nullLabel="– Pause wählen –"
                                :options="$this->slots->map(fn ($s) => [
                                    'value' => $s->id,
                                    'label' => $s->displayLabel(),
                                ])->values()->all()"
                                wire:model.live="slotId"
                            />
                        @endif

                        {{-- Personen als Auswahl statt als Zahlenfeld, wie im Shop:
                             Für vier Personen soll niemand tippen oder an einem
                             Pfeilchen ziehen. Größere Gruppen über "+", dann kommt
                             das Feld. --}}
                        @php ($schnell = collect([1, 2, 3, 4])->filter(fn ($z) => $z <= $this->maxGuests))
                        {{-- Hier läuft die Anfrage bewusst mit: An der Personenzahl hängt,
                             welche Tische noch passen. Die Markierung wartet aber nicht
                             darauf, die macht Alpine sofort. --}}
                        <div x-data="{ frei: {{ $guestCount > $schnell->max() ? 'true' : 'false' }}, anzahl: @js($guestCount) }">
                            <span class="mb-1 block text-xs font-medium text-[color:var(--nx-text)]">Personen</span>
                            <div class="flex flex-wrap items-center gap-2">
                                @foreach ($schnell as $z)
                                    <button
                                        type="button"
                                        x-on:click="frei = false; anzahl = {{ $z }}; $wire.$set('guestCount', {{ $z }})"
                                        wire:key="pers-{{ $z }}"
                                        class="h-9 w-9 rounded-[6px] border border-[color:var(--nx-line-strong)] text-sm font-medium tabular-nums text-[color:var(--nx-text)] transition-colors hover:bg-[color:var(--nx-hover)]"
                                        :style="anzahl === {{ $z }}
                                            ? { borderColor: 'var(--nx-accent)', background: 'var(--nx-accent-soft)' }
                                            : {}"
                                    >{{ $z }}</button>
                                @endforeach

                                <button
                                    type="button"
                                    x-on:click="frei = true"
                                    :class="frei ? 'border-[color:var(--nx-accent)] bg-[color:var(--nx-accent-soft)]' : ''"
                                    class="h-9 w-9 rounded-[6px] border border-[color:var(--nx-line-strong)] text-sm font-medium text-[color:var(--nx-text)] transition-colors hover:bg-[color:var(--nx-hover)]"
                                    title="Größere Gruppe"
                                >+</button>

                                <span class="text-[11px] text-[color:var(--nx-muted)]">max. {{ $this->maxGuests }} Personen</span>
                            </div>

                            <div x-show="frei" style="display: none;" class="mt-2 max-w-[10rem]"
                                x-on:input="anzahl = Number($event.target.value)">
                                <x-nx-input-number name="guestCount" wire:model.live="guestCount" min="1" :max="$this->maxGuests" />
                            </div>
                            @error('guestCount') <p class="mt-1 text-xs text-[color:var(--nx-danger)]">{{ $message }}</p> @enderror
                        </div>

                        {{-- Tisch mit freien Plätzen. Volle Tische stehen dabei, aber
                             gesperrt: Sonst sucht jemand einen Tisch, der einfach fehlt. --}}
                        @if ($this->slot)
                            @if ($this->tables->isEmpty() && $this->stations->isEmpty())
                                <x-nx-callout variant="warning">
                                    Dem Termin ist weder ein Raum mit Tischen noch eine Abholstation zugeordnet.
                                </x-nx-callout>
                            @elseif ($this->tables->isNotEmpty())
                                {{-- Die Markierung passiert im Browser, nicht auf dem Server.
                                     $set mit false als drittem Argument setzt die Eigenschaft
                                     nur örtlich; mitgeschickt wird sie mit dem nächsten
                                     Klick auf "Weiter". Vorher lief pro Tischklick eine
                                     Anfrage, und die Markierung erschien erst mit der
                                     Antwort – bei einem Dutzend Tischen fühlte sich das
                                     zäh an, obwohl der Server nichts zu rechnen hatte. --}}
                                {{-- Anders als vorher laeuft die Wahl ueber die Komponente
                                     ($wire.chooseTable) statt nur ueber $set im Browser: Tisch und
                                     Station schliessen einander aus, und dieses Aufraeumen gehoert
                                     an EINE Stelle. Die Verzoegerung ist eine Anfrage ohne Rechnung. --}}
                                <div x-data="{ gewaehlt: @js($tableId ? 'tisch-'.$tableId : ($stationId ? 'station-'.$stationId : null)) }">
                                    <span class="mb-1 block text-xs font-medium text-[color:var(--nx-text)]">Tisch <span class="text-[color:var(--nx-danger)]">*</span></span>
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        @foreach ($this->tables as $zeile)
                                            @php ($t = $zeile['table'])
                                            @php ($frei = $zeile['free'])
                                            @php ($moeglich = $zeile['bookable'])
                                            <button
                                                type="button"
                                                @if ($moeglich)
                                                    x-on:click="gewaehlt = 'tisch-{{ $t->id }}'; $wire.chooseTable({{ $t->id }})"
                                                @else
                                                    disabled
                                                @endif
                                                wire:key="tisch-{{ $t->id }}"
                                                @class([
                                                    'flex items-center justify-between rounded-[6px] border px-3 py-2 text-left transition-colors',
                                                    'border-[color:var(--nx-line-strong)] hover:bg-[color:var(--nx-hover)]' => $moeglich,
                                                    'cursor-not-allowed border-[color:var(--nx-line)] opacity-50' => ! $moeglich,
                                                ])
                                                @if ($moeglich)
                                                    {{-- :style als Objekt, nicht als Zeichenkette: Eine
                                                         Zeichenkette ersetzt das ganze style-Attribut. --}}
                                                    :style="gewaehlt === 'tisch-{{ $t->id }}'
                                                        ? { borderColor: 'var(--nx-accent)', background: 'var(--nx-accent-soft)' }
                                                        : {}"
                                                @endif
                                            >
                                                <span class="text-sm font-medium text-[color:var(--nx-text)]">{{ $t->label }}</span>
                                                <span class="whitespace-nowrap text-[11px] tabular-nums text-[color:var(--nx-muted)]">
                                                    {{ $frei }} / {{ $t->capacity }} frei
                                                </span>
                                            </button>
                                        @endforeach
                                    </div>
                                    {{-- Die Meldung verschwindet, sobald ein Tisch angeklickt ist.
                                         Ohne x-show blieb sie stehen: Die Auswahl läuft im Browser,
                                         es kommt also keine Antwort, die sie wegräumt. --}}
                                    @error('tableId')
                                        <p x-show="! gewaehlt" class="mt-1 text-xs text-[color:var(--nx-danger)]">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endif

                            {{-- Abholstationen: der zweite moegliche Ort, nicht der zweite Schritt.

                                 Steht unter den Tischen und nicht daneben, weil beides dieselbe
                                 Frage beantwortet - wohin kommt die Bestellung. Gewaehlt wird
                                 genau eines; ein Klick hier nimmt die Tischwahl zurueck. --}}
                            @if ($this->stations->isNotEmpty())
                                <div class="mt-3">
                                    <span class="mb-1 block text-xs font-medium text-[color:var(--nx-text)]">
                                        Oder Abholstation
                                    </span>
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        @foreach ($this->stations as $zeile)
                                            {{-- Wie oben: eine Zuweisung je Zeile, kein Rohblock.
                                                 Und in KOMMENTAREN dieser Datei darf der schliessende
                                                 Rohblock-Befehl nicht vorkommen - Blade sucht die
                                                 Bloecke, bevor es Kommentare entfernt, und findet ihn
                                                 auch dort. Genau daran ist der erste Anlauf
                                                 gescheitert. --}}
                                            @php ($st = $zeile['station'])
                                            @php ($frei = $zeile['frei'])
                                            @php ($moeglich = $zeile['buchbar'])
                                            <button
                                                type="button"
                                                @if ($moeglich)
                                                    wire:click="chooseStation({{ $st->id }})"
                                                @else
                                                    disabled
                                                @endif
                                                wire:key="station-{{ $st->id }}"
                                                @class([
                                                    'flex items-center justify-between rounded-[6px] border px-3 py-2 text-left transition-colors',
                                                    'border-[color:var(--nx-line-strong)] hover:bg-[color:var(--nx-hover)]' => $moeglich,
                                                    'cursor-not-allowed border-[color:var(--nx-line)] opacity-50' => ! $moeglich,
                                                ])
                                                @if ($stationId === $st->id)
                                                    style="border-color:var(--nx-accent); background:var(--nx-accent-soft)"
                                                @endif
                                            >
                                                <span class="text-sm font-medium text-[color:var(--nx-text)]">{{ $st->name }}</span>
                                                <span class="whitespace-nowrap text-[11px] tabular-nums text-[color:var(--nx-muted)]">
                                                    {{-- „unbegrenzt" als Wort: Eine leere Stelle liesse
                                                         offen, ob die Zahl fehlt oder keine gilt. --}}
                                                    @if ($frei === null)
                                                        unbegrenzt
                                                    @else
                                                        {{ $frei }} frei
                                                    @endif
                                                </span>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endif
                    @endif
                @endif
            </div>
            <div class="flex justify-end border-t border-[color:var(--nx-line)] px-4 py-3">
                <x-nx-button variant="primary" wire:click="nextStep" :disabled="$this->events->isEmpty()">
                    <span>Weiter</span>
                    @svg('heroicon-o-arrow-right', 'w-4 h-4')
                </x-nx-button>
            </div>
        </x-nx-card>
    @endif

    {{-- Schritt 2: Gast --}}
    @if ($step === 2)
        <x-nx-card flush>
            <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
                @svg('heroicon-o-user', 'w-4 h-4 text-[color:var(--nx-muted)]')
                <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Gast</h2>
            </div>
            <div class="space-y-4 p-5">
                {{-- Getrennt wie im Shop: Der Auftrag hält Vor- und Nachnamen einzeln,
                     und Anschreiben wie Beleg brauchen beide. --}}
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-nx-input-text name="guestFirstName" label="Vorname" wire:model="guestFirstName" required autocomplete="given-name" />
                    <x-nx-input-text name="guestLastName" label="Nachname" wire:model="guestLastName" required autocomplete="family-name" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-nx-input-text name="guestEmail" label="E-Mail" type="email" wire:model="guestEmail" autocomplete="email" />
                    <x-nx-input-text name="guestPhone" label="Telefon" type="tel" wire:model="guestPhone" autocomplete="tel" />
                </div>

                <x-nx-input-textarea name="notes" label="Anmerkungen" rows="3" wire:model="notes" />
            </div>
            <div class="flex items-center justify-between border-t border-[color:var(--nx-line)] px-4 py-3">
                <x-nx-button wire:click="prevStep">
                    @svg('heroicon-o-arrow-left', 'w-4 h-4')
                    <span>Zurück</span>
                </x-nx-button>
                <x-nx-button variant="primary" wire:click="nextStep">
                    <span>Weiter</span>
                    @svg('heroicon-o-arrow-right', 'w-4 h-4')
                </x-nx-button>
            </div>
        </x-nx-card>
    @endif

    {{-- Schritt 3: Artikel.

         Die Mengen führt der Browser, nicht der Server: Vorher lief pro Klick auf
         "+" eine Anfrage, und die Zahl änderte sich erst mit der Antwort. Jetzt
         springt sie sofort, und die Auswahl wird gebündelt nachgeschickt – wer
         fünfmal tippt, löst eine Anfrage aus, nicht fünf.

         Die SUMME rechnet weiter der Server. Sie hängt an der Auflösung der
         Bundles in Bestandteile und an der cent-genauen Verteilung des
         Bundle-Preises; das im Browser nachzubauen wäre die zweite Fassung
         derselben Rechnung. Solange sie noch unterwegs ist, steht sie blass. --}}
    @if ($step === 3)
        <div x-data="{
            mengen: @js($selectedItems),
            timer: null,
            unterwegs: false,
            menge(id) { return this.mengen[id] ?? 0 },
            aendern(id, schritt) {
                const neu = (this.mengen[id] ?? 0) + schritt;

                if (neu <= 0) { delete this.mengen[id]; } else { this.mengen[id] = neu; }

                this.unterwegs = true;
                clearTimeout(this.timer);
                this.timer = setTimeout(() => {
                    this.$wire.$set('selectedItems', this.mengen).then(() => this.unterwegs = false);
                }, 350);
            },
        }">
        <x-nx-card flush>
            <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
                @svg('heroicon-o-shopping-bag', 'w-4 h-4 text-[color:var(--nx-muted)]')
                <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Artikel</h2>
                <span class="ml-auto text-[11px] text-[color:var(--nx-muted)]">
                    {{ $this->event?->name }}@if ($this->slot) · {{ $this->slot->displayLabel() }}@endif
                </span>
            </div>

            @forelse ($this->availableMenuItems->groupBy('category_id') as $categoryId => $items)
                @php ($category = $items->first()->category)
                <div wire:key="cat-{{ $categoryId }}">
                    <div class="border-b border-[color:var(--nx-line)] bg-[color:var(--nx-hover)] px-4 py-1.5">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-[color:var(--nx-muted)]">
                            {{ $category?->name ?? 'Ohne Kategorie' }}
                        </span>
                    </div>

                    @foreach ($items as $item)
                        <div class="flex items-center gap-3 border-b border-[color:var(--nx-line)] px-4 py-2.5" wire:key="item-{{ $item->id }}">
                            <div class="min-w-0 flex-1">
                                <span class="text-sm font-medium text-[color:var(--nx-text)]">{{ $item->name }}</span>
                                @if ($item->description)
                                    <p class="m-0 text-[11px] text-[color:var(--nx-muted)]">{{ $item->description }}</p>
                                @endif
                                {{-- Sichtbar machen, dass hier mehrere Artikel drinstecken: sie
                                     landen als getrennte Positionen auf dem Küchenzettel. --}}
                                @if ($item->isBundle())
                                    <p class="m-0 text-[11px] text-[color:var(--nx-muted)]">
                                        Bundle:
                                        {{ $item->components
                                            ->map(fn ($c) => ((int) ($c->pivot->quantity ?? 1) > 1 ? $c->pivot->quantity . '× ' : '') . $c->name)
                                            ->implode(' + ') }}
                                    </p>
                                @endif
                            </div>

                            <span class="shrink-0 whitespace-nowrap text-sm tabular-nums text-[color:var(--nx-text)]">
                                {{ number_format($item->price, 2, ',', '.') }} €
                            </span>

                            <div class="flex shrink-0 items-center gap-1">
                                <span x-show="menge({{ $item->id }}) > 0" style="display: none;" class="flex items-center gap-1">
                                    <x-nx-button icon variant="ghost" x-on:click="aendern({{ $item->id }}, -1)" title="Weniger">
                                        @svg('heroicon-o-minus', 'w-4 h-4')
                                    </x-nx-button>
                                    <span class="w-5 text-center text-sm font-medium tabular-nums text-[color:var(--nx-text)]"
                                        x-text="menge({{ $item->id }})"></span>
                                </span>
                                <x-nx-button icon x-on:click="aendern({{ $item->id }}, 1)" title="Mehr">
                                    @svg('heroicon-o-plus', 'w-4 h-4')
                                </x-nx-button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @empty
                <div class="px-4 py-6 text-sm text-[color:var(--nx-muted)]">
                    Die Verkaufsliste dieses Termins enthält keine freigegebenen Artikel.
                </div>
            @endforelse

            {{-- Immer sichtbar, auch bei null: Sonst springt die Zeile beim ersten
                 Artikel in die Liste hinein. --}}
            <div class="flex items-center justify-between border-b border-[color:var(--nx-line)] px-4 py-3">
                <span class="text-sm font-medium text-[color:var(--nx-text)]">Gesamt</span>
                <span class="whitespace-nowrap text-sm font-semibold tabular-nums text-[color:var(--nx-text)] transition-opacity"
                    :class="unterwegs ? 'opacity-40' : ''">
                    {{ number_format($this->orderTotal, 2, ',', '.') }} €
                </span>
            </div>

            <div class="flex items-center justify-between px-4 py-3">
                <x-nx-button wire:click="prevStep">
                    @svg('heroicon-o-arrow-left', 'w-4 h-4')
                    <span>Zurück</span>
                </x-nx-button>
                <x-nx-button variant="primary" wire:click="confirm" wire:loading.attr="disabled" wire:target="confirm">
                    <span wire:loading.remove wire:target="confirm">Buchung anlegen</span>
                    <span wire:loading wire:target="confirm">Wird gespeichert…</span>
                </x-nx-button>
            </div>
        </x-nx-card>
        </div>
    @endif

    {{-- Schritt 4: Bestätigung --}}
    @if ($step === 4)
        <x-nx-card>
            <x-nx-empty icon="heroicon-o-check-circle">
                <span class="text-sm font-medium text-[color:var(--nx-text)]">Buchung angelegt</span>
                <span class="mt-1 block">
                    {{ trim($guestFirstName . ' ' . $guestLastName) }} · {{ $this->event?->date?->format('d.m.Y') }}@if ($this->slot) · {{ $this->slot->displayLabel() }}@endif
                    @if ($this->selectedTable) · Tisch {{ $this->selectedTable->label }} @endif
                </span>
                <span class="mt-1 block text-[11px]">
                    Bestätigt, Zahlung vor Ort. Zählt ab jetzt in Küche, Laufzettel und Platzprüfung.
                </span>
                <x-slot name="action">
                    <x-nx-button variant="primary" :href="route('reservation.bookings.index')">
                        @svg('heroicon-o-list-bullet', 'w-4 h-4')
                        <span>Zur Buchungsübersicht</span>
                    </x-nx-button>
                </x-slot>
            </x-nx-empty>
        </x-nx-card>
    @endif

    </div>
    </x-ui-page-container>
</x-ui-page>
