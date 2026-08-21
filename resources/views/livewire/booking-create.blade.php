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

                        <x-nx-input-number name="guestCount" label="Personen" wire:model.live="guestCount" min="1" max="100" />

                        {{-- Tisch mit freien Plätzen. Volle Tische stehen dabei, aber
                             gesperrt: Sonst sucht jemand einen Tisch, der einfach fehlt. --}}
                        @if ($this->slot)
                            @if ($this->tables->isEmpty())
                                <x-nx-callout variant="warning">
                                    Dem Termin ist kein Raum mit Tischen zugeordnet.
                                </x-nx-callout>
                            @else
                                <div>
                                    <span class="mb-1 block text-xs font-medium text-[color:var(--nx-text)]">Tisch <span class="text-[color:var(--nx-danger)]">*</span></span>
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        @foreach ($this->tables as $zeile)
                                            @php ($t = $zeile['table'])
                                            @php ($frei = $zeile['free'])
                                            @php ($moeglich = $zeile['bookable'])
                                            <button
                                                type="button"
                                                @if ($moeglich) wire:click="$set('tableId', {{ $t->id }})" @else disabled @endif
                                                wire:key="tisch-{{ $t->id }}"
                                                @class([
                                                    'flex items-center justify-between rounded-[6px] border px-3 py-2 text-left transition-colors',
                                                    'border-[color:var(--nx-accent)] bg-[color:var(--nx-accent-soft)]' => $tableId === $t->id,
                                                    'border-[color:var(--nx-line-strong)] hover:bg-[color:var(--nx-hover)]' => $moeglich && $tableId !== $t->id,
                                                    'cursor-not-allowed border-[color:var(--nx-line)] opacity-50' => ! $moeglich,
                                                ])
                                            >
                                                <span class="text-sm font-medium text-[color:var(--nx-text)]">{{ $t->label }}</span>
                                                <span class="whitespace-nowrap text-[11px] tabular-nums text-[color:var(--nx-muted)]">
                                                    {{ $frei }} / {{ $t->capacity }} frei
                                                </span>
                                            </button>
                                        @endforeach
                                    </div>
                                    @error('tableId') <p class="mt-1 text-xs text-[color:var(--nx-danger)]">{{ $message }}</p> @enderror
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
                <x-nx-input-text name="guestName" label="Name" wire:model="guestName" required autocomplete="name" />

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

    {{-- Schritt 3: Vorbestellung --}}
    @if ($step === 3)
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
                        @php ($menge = $selectedItems[$item->id] ?? 0)
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
                                @if ($menge > 0)
                                    <x-nx-button icon variant="ghost" wire:click="decrementItem({{ $item->id }})" title="Weniger">
                                        @svg('heroicon-o-minus', 'w-4 h-4')
                                    </x-nx-button>
                                    <span class="w-5 text-center text-sm font-medium tabular-nums text-[color:var(--nx-text)]">{{ $menge }}</span>
                                @endif
                                <x-nx-button icon wire:click="incrementItem({{ $item->id }})" title="Mehr">
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

            @if ($this->orderTotal > 0)
                <div class="flex items-center justify-between border-b border-[color:var(--nx-line)] px-4 py-3">
                    <span class="text-sm font-medium text-[color:var(--nx-text)]">Gesamt</span>
                    <span class="whitespace-nowrap text-sm font-semibold tabular-nums text-[color:var(--nx-text)]">
                        {{ number_format($this->orderTotal, 2, ',', '.') }} €
                    </span>
                </div>
            @endif

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
    @endif

    {{-- Schritt 4: Bestätigung --}}
    @if ($step === 4)
        <x-nx-card>
            <x-nx-empty icon="heroicon-o-check-circle">
                <span class="text-sm font-medium text-[color:var(--nx-text)]">Buchung angelegt</span>
                <span class="mt-1 block">
                    {{ $guestName }} · {{ $this->event?->date?->format('d.m.Y') }}@if ($this->slot) · {{ $this->slot->displayLabel() }}@endif
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
