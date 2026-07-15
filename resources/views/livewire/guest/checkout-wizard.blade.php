<div class="min-h-screen bg-gray-50 dark:bg-gray-950">
    {{-- Kopf --}}
    <div class="border-b bg-white px-4 py-4 dark:border-gray-800 dark:bg-gray-900">
        <div class="mx-auto max-w-3xl">
            <a href="{{ route('reservation.guest.events.index') }}" class="text-xs text-[var(--ui-primary)] hover:underline">← Alle Termine</a>
            <h1 class="mt-1 text-xl font-bold text-gray-900 dark:text-white">{{ $this->event->name }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ $this->event->date->locale('de')->isoFormat('dd, D. MMMM Y') }}
                @if ($this->event->venue) · {{ $this->event->venue->name }} @endif
            </p>
        </div>
    </div>

    <div class="mx-auto max-w-3xl px-4 py-6">
        @if (!$this->event->isOrderable() && $step < 5)
            {{-- Bestellschluss erreicht --}}
            <div class="rounded-2xl border border-dashed border-gray-300 bg-white py-16 text-center dark:border-gray-700 dark:bg-gray-900">
                <div class="text-5xl mb-4">⏰</div>
                <h2 class="text-lg font-semibold dark:text-white">Der Bestellschluss ist erreicht</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Für diesen Termin sind leider keine Vorbestellungen mehr möglich.
                </p>
                <a href="{{ route('reservation.guest.events.index') }}"
                    class="mt-5 inline-block rounded-xl bg-[var(--ui-primary)] px-6 py-3 text-sm font-medium text-white hover:opacity-90">
                    Andere Termine ansehen
                </a>
            </div>
        @else

        {{-- Step-Indicator --}}
        @if ($step < 5)
            <div class="mx-auto mb-6 max-w-lg">
                <div class="grid grid-cols-4 gap-0">
                    @foreach (['Gastdaten', 'Produkte', 'Sitzplatz', 'Bezahlung'] as $i => $label)
                        @php
                            $isDone = $step > $i + 1;
                            $isActive = $step === $i + 1;
                        @endphp
                        <div class="relative flex flex-col items-center">
                            {{-- Verbindungslinien auf Kreis-Höhe --}}
                            @if ($i > 0)
                                <div class="absolute left-0 right-1/2 top-4 -z-0 h-0.5 -translate-y-1/2 {{ $step > $i ? 'bg-[var(--ui-primary)]' : 'bg-gray-200 dark:bg-gray-800' }}"></div>
                            @endif
                            @if ($i < 3)
                                <div class="absolute left-1/2 right-0 top-4 -z-0 h-0.5 -translate-y-1/2 {{ $step > $i + 1 ? 'bg-[var(--ui-primary)]' : 'bg-gray-200 dark:bg-gray-800' }}"></div>
                            @endif
                            <div class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold ring-4 ring-gray-50 dark:ring-gray-950
                                {{ $isDone ? 'bg-[var(--ui-primary)] text-white' : ($isActive ? 'bg-[var(--ui-primary)] text-white' : 'bg-white text-gray-400 border border-gray-300 dark:border-gray-700 dark:bg-gray-900') }}">
                                @if ($isDone)
                                    @svg('heroicon-o-check', 'w-4 h-4')
                                @else
                                    {{ $i + 1 }}
                                @endif
                            </div>
                            <span class="mt-1.5 text-[11px] {{ $isActive ? 'font-semibold text-[var(--ui-primary)]' : 'text-gray-500' }}">{{ $label }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- ── Schritt 1: Gastdaten ────────────────────────────── --}}
        @if ($step === 1)
            <div class="mx-auto max-w-lg space-y-4">
                <h2 class="text-lg font-semibold dark:text-white">Ihre Daten</h2>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name *</label>
                    <input wire:model="guestName" type="text" autocomplete="name"
                        class="mt-1 w-full rounded-xl border px-4 py-3 text-base dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                    @error('guestName') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">E-Mail *</label>
                    <input wire:model.blur="guestEmail" type="email" autocomplete="email" inputmode="email"
                        class="mt-1 w-full rounded-xl border px-4 py-3 text-base dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                    @error('guestEmail') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-gray-500">Für Ihre Buchungsbestätigung – bitte gültige Adresse angeben.</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Telefon</label>
                        <input wire:model.blur="guestPhone" type="tel" autocomplete="tel" inputmode="tel"
                            class="mt-1 w-full rounded-xl border px-4 py-3 text-base dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                        @error('guestPhone') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Personen *</label>
                        <input wire:model="guestCount" type="number" min="1" max="20"
                            class="mt-1 w-full rounded-xl border px-4 py-3 text-base dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                        @error('guestCount') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Anmerkungen</label>
                    <textarea wire:model="notes" rows="2"
                        class="mt-1 w-full rounded-xl border px-4 py-3 text-base dark:border-gray-700 dark:bg-gray-800 dark:text-white"></textarea>
                </div>

                <button wire:click="nextStep"
                    class="mt-2 w-full rounded-xl bg-[var(--ui-primary)] py-3 text-base font-bold text-white hover:opacity-90">
                    Weiter zur Produktauswahl
                </button>
            </div>
        @endif

        {{-- ── Schritt 2: Produktauswahl ───────────────────────── --}}
        @if ($step === 2)
            <style>[x-cloak]{display:none!important}</style>
            <div class="space-y-4"
                x-data="cart(@js($selectedItems), @js($this->itemPrices))">

                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold dark:text-white">Was darf es in der Pause sein?</h2>
                    <div class="flex gap-2">
                        <button wire:click="$toggle('filterVegetarian')"
                            class="rounded-full border px-3 py-1 text-xs {{ $filterVegetarian ? 'border-lime-500 bg-lime-100 text-lime-700 dark:bg-lime-900/30 dark:text-lime-300' : 'border-gray-300 text-gray-600 dark:border-gray-700 dark:text-gray-300' }}">
                            🌿 Vegetarisch
                        </button>
                        <button wire:click="$toggle('filterVegan')"
                            class="rounded-full border px-3 py-1 text-xs {{ $filterVegan ? 'border-emerald-500 bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'border-gray-300 text-gray-600 dark:border-gray-700 dark:text-gray-300' }}">
                            🌱 Vegan
                        </button>
                    </div>
                </div>

                @error('selectedItems')
                    <div class="rounded-lg bg-red-100 p-3 text-sm text-red-800 dark:bg-red-900/30 dark:text-red-300">{{ $message }}</div>
                @enderror

                @forelse ($this->menuItems as $categoryName => $items)
                    <div wire:key="cat-{{ $categoryName }}">
                        <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            {{ $categoryName }}
                        </h3>
                        <div class="space-y-2">
                            @foreach ($items as $item)
                                <div wire:key="prod-{{ $item->id }}"
                                    class="flex items-center gap-3 rounded-xl border bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
                                    @if ($item->image_context_file_id && $item->imageFile)
                                        <img src="{{ $item->imageUrl('thumbnail_1_1') }}" alt=""
                                            class="h-16 w-16 shrink-0 rounded-lg object-cover" />
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <p class="text-sm font-medium dark:text-white">{{ $item->name }}</p>
                                            @if ($item->portion_size)
                                                <span class="text-xs text-gray-500">{{ $item->portion_size }}</span>
                                            @endif
                                            @if ($item->is_vegan)
                                                <span title="Vegan">🌱</span>
                                            @elseif ($item->is_vegetarian)
                                                <span title="Vegetarisch">🌿</span>
                                            @endif
                                            @if ($item->is_alcoholic)
                                                <span class="rounded-full bg-purple-100 px-1.5 py-0.5 text-[10px] text-purple-700 dark:bg-purple-900/30 dark:text-purple-300">18+</span>
                                            @endif
                                        </div>
                                        @if ($item->description)
                                            <p class="text-xs text-gray-500">{{ $item->description }}</p>
                                        @endif
                                        @if ($item->allergens->isNotEmpty() || $item->additives->isNotEmpty())
                                            <p class="mt-0.5 text-[11px] text-gray-400">
                                                Enthält: {{ $item->allergens->pluck('code')->merge($item->additives->pluck('code'))->filter()->implode(', ') }}
                                            </p>
                                        @endif
                                        <p class="text-sm font-semibold text-[var(--ui-primary)]">
                                            {{ number_format($item->price, 2, ',', '.') }} €
                                        </p>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-2">
                                        <button type="button" x-show="(qty[{{ $item->id }}] || 0) > 0" x-cloak
                                            x-on:click="dec({{ $item->id }})"
                                            class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-200 text-gray-700 transition active:scale-90 dark:bg-gray-700 dark:text-white">−</button>
                                        <span x-show="(qty[{{ $item->id }}] || 0) > 0" x-cloak
                                            class="w-6 text-center text-sm font-medium tabular-nums dark:text-white"
                                            x-text="qty[{{ $item->id }}] || 0"></span>
                                        <button type="button" x-on:click="inc({{ $item->id }})"
                                            class="flex h-9 w-9 items-center justify-center rounded-full bg-[var(--ui-primary-10)] text-[var(--ui-primary)] transition active:scale-90">+</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed p-8 text-center text-sm text-gray-500 dark:border-gray-700">
                        Für diesen Termin sind aktuell keine Produkte verfügbar.
                    </div>
                @endforelse

                {{-- Legende --}}
                @if ($this->legend['allergens']->isNotEmpty() || $this->legend['additives']->isNotEmpty())
                    <details class="rounded-xl border bg-white p-3 text-xs text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
                        <summary class="cursor-pointer font-medium">Allergene &amp; Zusatzstoffe (Legende)</summary>
                        <div class="mt-2 grid grid-cols-1 gap-1 sm:grid-cols-2">
                            @foreach ($this->legend['allergens'] as $allergen)
                                <span>({{ $allergen->code }}) {{ $allergen->name }}</span>
                            @endforeach
                            @foreach ($this->legend['additives'] as $additive)
                                <span>({{ $additive->code }}) {{ $additive->name }}</span>
                            @endforeach
                        </div>
                    </details>
                @endif

                {{-- Summe + Navigation --}}
                <div class="sticky bottom-0 -mx-4 border-t bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <div class="mx-auto flex max-w-3xl items-center justify-between gap-3">
                        <div>
                            <p class="text-xs text-gray-500"><span x-text="count"></span> Produkte</p>
                            <p class="text-lg font-bold dark:text-white"><span x-text="euro(total)"></span> €</p>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" x-on:click="navigate('prevStep')"
                                class="rounded-xl border px-4 py-3 text-sm font-medium dark:border-gray-700 dark:text-white">Zurück</button>
                            <button type="button" x-on:click="navigate('nextStep')"
                                class="rounded-xl bg-[var(--ui-primary)] px-5 py-3 text-sm font-bold text-white transition hover:opacity-90 active:scale-95">
                                Weiter zur Sitzplatzwahl
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- ── Schritt 3: Sitzplatz (Slot → Raum → Tisch) ──────── --}}
        @if ($step === 3)
            <div class="space-y-4">
                <h2 class="text-lg font-semibold dark:text-white">Wo möchten Sie sitzen?</h2>

                {{-- Slot-Wahl (nur bei mehreren Pausen) --}}
                @if ($this->event->slots->count() > 1)
                    <div>
                        <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Pause wählen</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($this->event->slots as $slot)
                                <button wire:key="slot-{{ $slot->id }}" wire:click="selectSlot({{ $slot->id }})"
                                    class="rounded-xl border px-4 py-2 text-sm {{ $selectedSlotId === $slot->id ? 'border-[var(--ui-primary)] bg-[var(--ui-primary-10)] font-semibold text-[var(--ui-primary)]' : 'border-gray-300 text-gray-700 dark:border-gray-700 dark:text-gray-300' }}">
                                    {{ $slot->name }} · {{ substr($slot->time_start, 0, 5) }} Uhr
                                </button>
                            @endforeach
                        </div>
                        @error('selectedSlotId') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                @endif

                {{-- Raum-Wahl --}}
                @if ($selectedSlotId)
                    @if ($this->openRooms->count() > 1)
                        <div>
                            <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Raum wählen</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($this->openRooms as $room)
                                    <button wire:key="room-{{ $room->id }}" wire:click="selectRoom({{ $room->id }})"
                                        class="rounded-xl border px-4 py-2 text-sm {{ $selectedRoomId === $room->id ? 'border-[var(--ui-primary)] bg-[var(--ui-primary-10)] font-semibold text-[var(--ui-primary)]' : 'border-gray-300 text-gray-700 dark:border-gray-700 dark:text-gray-300' }}">
                                        {{ $room->floorPlan->name }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @elseif ($this->openRooms->isEmpty())
                        <div class="rounded-xl border border-dashed p-8 text-center text-sm text-gray-500 dark:border-gray-700">
                            Aktuell ist kein Raum für diese Pause geöffnet.
                        </div>
                    @endif

                    {{-- Tischplan --}}
                    @if ($this->selectedRoom)
                        <div class="overflow-hidden rounded-2xl bg-slate-900">
                            <div class="flex items-center justify-center gap-4 border-b border-slate-700 bg-slate-800 py-2 text-xs text-slate-300">
                                <span class="flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded-full bg-emerald-500"></span> Frei</span>
                                <span class="flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded-full bg-amber-500"></span> Teilbelegt</span>
                                <span class="flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded-full bg-red-500"></span> Voll</span>
                                <span class="flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded-full bg-indigo-500"></span> Ausgewählt</span>
                            </div>
                            <div class="flex flex-col" style="min-height: 50vh;">
                                @include('reservation::partials.floor-plan-svg', [
                                    'tableStates' => $this->tableStates,
                                    'clickAction' => 'selectTable',
                                    'backgroundUrl' => $this->selectedRoom?->floorPlan?->backgroundUrl(),
                                    'rotation' => $this->selectedRoom?->floorPlan?->background_rotation ?? 0,
                                    'aspect' => $this->selectedRoom?->floorPlan?->displayAspect() ?? (4 / 3),
                                ])
                            </div>
                        </div>
                        <p class="text-xs text-gray-500">Gruppengröße: {{ $guestCount }} {{ $guestCount === 1 ? 'Person' : 'Personen' }} – Tische mit weniger freien Plätzen sind ausgegraut.</p>
                    @endif
                @endif

                @error('selectedTableId')
                    <div class="rounded-lg bg-red-100 p-3 text-sm text-red-800 dark:bg-red-900/30 dark:text-red-300">{{ $message }}</div>
                @enderror

                <div class="flex gap-3">
                    <button wire:click="prevStep"
                        class="flex-1 rounded-xl border py-3 text-base font-medium dark:border-gray-700 dark:text-white">Zurück</button>
                    <button wire:click="nextStep"
                        class="flex-1 rounded-xl bg-[var(--ui-primary)] py-3 text-base font-bold text-white hover:opacity-90 disabled:opacity-50"
                        @if (!$selectedTableId) disabled @endif>
                        Weiter zur Bezahlung
                    </button>
                </div>
            </div>
        @endif

        {{-- ── Schritt 4: Checkout (Mock) ──────────────────────── --}}
        @if ($step === 4)
            <div class="mx-auto max-w-lg space-y-4">
                <h2 class="text-lg font-semibold dark:text-white">Zusammenfassung &amp; Bezahlung</h2>

                {{-- Buchungsdaten --}}
                <div class="rounded-xl border bg-white p-4 text-sm dark:border-gray-700 dark:bg-gray-900">
                    <p class="font-semibold dark:text-white">{{ $this->event->name }}</p>
                    <p class="text-gray-500 dark:text-gray-400">
                        {{ $this->event->date->format('d.m.Y') }}
                        @if ($this->selectedSlot) · {{ $this->selectedSlot->name }} {{ substr($this->selectedSlot->time_start, 0, 5) }} Uhr @endif
                        @if ($this->selectedRoom) · {{ $this->selectedRoom->floorPlan->name }} @endif
                    </p>
                    <p class="text-gray-500 dark:text-gray-400">
                        {{ $guestName }} · {{ $guestCount }} {{ $guestCount === 1 ? 'Person' : 'Personen' }}
                        @php $selectedTable = collect($this->tableStates)->first(fn ($s) => $s['table']->id === $selectedTableId); @endphp
                        @if ($selectedTable) · Tisch {{ $selectedTable['table']->label }} @endif
                    </p>
                </div>

                {{-- Warenkorb --}}
                <div class="rounded-xl border bg-white dark:border-gray-700 dark:bg-gray-900">
                    <div class="divide-y dark:divide-gray-700">
                        @foreach ($this->cartItems as $line)
                            <div class="flex items-center justify-between px-4 py-2.5 text-sm">
                                <span class="dark:text-white">{{ $line['quantity'] }}× {{ $line['item']->name }}</span>
                                <span class="font-medium dark:text-white">{{ number_format($line['total'], 2, ',', '.') }} €</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="border-t px-4 py-3 dark:border-gray-700">
                        @foreach ($this->totalsByTaxRate as $rate => $sum)
                            <div class="flex justify-between text-xs text-gray-500">
                                <span>davon {{ rtrim(rtrim($rate, '0'), '.') }} % MwSt.</span>
                                <span>{{ number_format($sum, 2, ',', '.') }} €</span>
                            </div>
                        @endforeach
                        <div class="mt-1 flex justify-between text-base font-bold dark:text-white">
                            <span>Gesamt</span>
                            <span>{{ number_format($this->orderTotal, 2, ',', '.') }} €</span>
                        </div>
                    </div>
                </div>

                {{-- Zahlung: die Zahlungsart wählt der Gast auf der Mollie-Bezahlseite. --}}
                @if ($this->payViaMollie)
                    <div class="flex items-start gap-3 rounded-xl border border-[var(--ui-border)] bg-[var(--ui-muted-5)] p-3 text-sm">
                        @svg('heroicon-o-lock-closed', 'w-5 h-5 shrink-0 text-[var(--ui-muted)]')
                        <span class="text-[var(--ui-secondary)]">
                            Die Bezahlung erfolgt sicher über <strong>Mollie</strong>. Nach „Weiter zur Zahlung“ werden Sie
                            zur Bezahlseite weitergeleitet und wählen dort Ihre Zahlungsart (Karte, PayPal, Apple&nbsp;Pay …).
                            Ihre Zahlungsdaten werden nicht bei uns gespeichert.
                        </span>
                    </div>
                @endif

                {{-- 18+ (nur bei Alkohol) --}}
                @if ($this->requiresAgeCheck)
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-purple-300 bg-purple-50 p-3 text-sm dark:border-purple-800 dark:bg-purple-900/20">
                        <input type="checkbox" wire:model.live="ageConfirmed" class="mt-0.5 rounded border-gray-300" />
                        <span class="dark:text-white">{{ $this->checkoutTexts->ageText() }}</span>
                    </label>
                    @error('ageConfirmed') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
                @endif

                {{-- Rechtshinweis --}}
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 text-sm dark:border-gray-700">
                    <input type="checkbox" wire:model.live="legalAccepted" class="mt-0.5 rounded border-gray-300" />
                    <span class="text-gray-600 dark:text-gray-300">
                        {{ $this->checkoutTexts->legalText() }}
                        @if ($this->checkoutTexts->privacy_url)
                            <a href="{{ $this->checkoutTexts->privacy_url }}" target="_blank" rel="noopener"
                                class="text-[var(--ui-primary)] underline">Datenschutzerklärung</a>
                        @endif
                    </span>
                </label>
                @error('legalAccepted') <p class="text-xs text-red-500">{{ $message }}</p> @enderror

                @error('payment') <p class="text-sm text-red-500">{{ $message }}</p> @enderror

                <div class="flex gap-3">
                    <button wire:click="prevStep"
                        class="flex-1 rounded-xl border py-3 text-base font-medium dark:border-gray-700 dark:text-white">Zurück</button>
                    <button wire:click="confirm" wire:loading.attr="disabled"
                        class="flex-1 rounded-xl bg-[var(--ui-primary)] py-3 text-base font-bold text-white hover:opacity-90 disabled:opacity-50">
                        <span wire:loading.remove wire:target="confirm">{{ $this->payViaMollie ? 'Weiter zur Zahlung' : 'Jetzt verbindlich bestellen' }}</span>
                        <span wire:loading wire:target="confirm">{{ $this->payViaMollie ? 'Weiterleitung …' : 'Wird gespeichert…' }}</span>
                    </button>
                </div>
                @unless ($this->payViaMollie)
                    <p class="text-center text-xs text-gray-400">
                        Demo-Modus: Es wird noch keine echte Zahlung ausgelöst.
                    </p>
                @endunless
            </div>
        @endif

        {{-- ── Schritt 5: Bestätigung ──────────────────────────── --}}
        @if ($step === 5)
            <div class="mx-auto max-w-lg space-y-4 py-8 text-center">
                <div class="text-5xl">✅</div>
                <h2 class="text-xl font-bold dark:text-white">Vielen Dank für Ihre Bestellung!</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ $guestName }}, Ihre Pausen-Bestellung für
                    <strong>{{ $this->event->name }}</strong> am
                    {{ $this->event->date->format('d.m.Y') }}
                    @if ($this->selectedSlot) ({{ $this->selectedSlot->name }}, {{ substr($this->selectedSlot->time_start, 0, 5) }} Uhr) @endif
                    ist eingegangen.
                </p>
                @if ($bookingUuid)
                    <p class="text-xs text-gray-500">Buchungsnummer: <code class="rounded bg-gray-100 px-1.5 py-0.5 dark:bg-gray-800">{{ $bookingUuid }}</code></p>
                @endif
                <p class="text-xs text-gray-500">Eine Bestätigung erhalten Sie per E-Mail.</p>

                <a href="{{ route('reservation.guest.events.index') }}"
                    class="mt-4 inline-block rounded-xl border px-6 py-3 text-base font-medium dark:border-gray-700 dark:text-white">
                    Zur Terminübersicht
                </a>
            </div>
        @endif

        @endif
    </div>
</div>

@script
<script>
// Optimistischer Warenkorb: Menge/Summe rein clientseitig (0 ms), Server-Sync
// erst bei der Navigation (deferred -> ein Request). Preise nur für die Anzeige;
// der verbindliche Preis wird beim confirm() serverseitig neu berechnet.
Alpine.data('cart', (initial, prices) => ({
    qty: Object.assign({}, initial || {}),
    prices: prices || {},

    inc(id) {
        this.qty[id] = (this.qty[id] || 0) + 1;
    },
    dec(id) {
        const n = (this.qty[id] || 0) - 1;
        if (n <= 0) {
            delete this.qty[id];
        } else {
            this.qty[id] = n;
        }
    },

    get count() {
        return Object.values(this.qty).reduce((sum, n) => sum + n, 0);
    },
    get total() {
        let sum = 0;
        for (const id in this.qty) {
            sum += (this.prices[id] || 0) * this.qty[id];
        }
        return sum;
    },
    euro(value) {
        return value.toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },

    // Client-Stand deferred an den Server hängen und die Wizard-Navigation
    // auslösen – beides in EINEM Request (kein Zwischen-Render/Flackern).
    navigate(method) {
        this.$wire.set('selectedItems', this.qty, false);
        this.$wire[method]();
    },
}));
</script>
@endscript
