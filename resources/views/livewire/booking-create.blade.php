<div class="mx-auto max-w-lg p-4">
    {{-- Step Indicator --}}
    <div class="mb-6 flex items-center justify-between">
        @foreach(['Termin', 'Gast', 'Menü', 'Bestätigung'] as $i => $label)
            <div class="flex flex-col items-center">
                <div class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold
                    {{ $step > $i + 1 ? 'bg-green-500 text-white' : ($step === $i + 1 ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-500') }}">
                    {{ $step > $i + 1 ? '✓' : $i + 1 }}
                </div>
                <span class="mt-1 text-xs text-gray-500">{{ $label }}</span>
            </div>
            @if ($i < 3)
                <div class="flex-1 h-px bg-gray-200 mx-1 mt-[-16px]"></div>
            @endif
        @endforeach
    </div>

    {{-- Schritt 1: Datum & Zeit --}}
    @if ($step === 1)
        <div class="space-y-4">
            <h2 class="text-lg font-semibold dark:text-white">Wann möchten Sie kommen?</h2>

            @if ($this->selectedTable)
                <div class="rounded-lg bg-indigo-50 p-3 text-sm dark:bg-indigo-900/30">
                    <strong>{{ $this->selectedTable->label }}</strong> – max. {{ $this->selectedTable->capacity }} Personen
                </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Datum</label>
                <input wire:model="date" type="date" min="{{ now()->toDateString() }}"
                    class="mt-1 w-full rounded-xl border px-4 py-3 text-base dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                @error('date') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Uhrzeit</label>
                <input wire:model="timeStart" type="time"
                    class="mt-1 w-full rounded-xl border px-4 py-3 text-base dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                @error('timeStart') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Personenanzahl</label>
                <input wire:model="guestCount" type="number" min="1" max="20"
                    class="mt-1 w-full rounded-xl border px-4 py-3 text-base dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
            </div>

            <button wire:click="nextStep"
                class="mt-4 w-full rounded-xl bg-indigo-600 py-3 text-base font-bold text-white hover:bg-indigo-700">
                Weiter
            </button>
        </div>
    @endif

    {{-- Schritt 2: Gastdaten --}}
    @if ($step === 2)
        <div class="space-y-4">
            <h2 class="text-lg font-semibold dark:text-white">Ihre Daten</h2>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name *</label>
                <input wire:model="guestName" type="text" autocomplete="name"
                    class="mt-1 w-full rounded-xl border px-4 py-3 text-base dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                @error('guestName') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">E-Mail</label>
                <input wire:model="guestEmail" type="email" autocomplete="email"
                    class="mt-1 w-full rounded-xl border px-4 py-3 text-base dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                @error('guestEmail') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Telefon</label>
                <input wire:model="guestPhone" type="tel" autocomplete="tel"
                    class="mt-1 w-full rounded-xl border px-4 py-3 text-base dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Anmerkungen</label>
                <textarea wire:model="notes" rows="3"
                    class="mt-1 w-full rounded-xl border px-4 py-3 text-base dark:border-gray-700 dark:bg-gray-800 dark:text-white"></textarea>
            </div>

            <div class="flex gap-3">
                <button wire:click="prevStep"
                    class="flex-1 rounded-xl border py-3 text-base font-medium dark:border-gray-700 dark:text-white">
                    Zurück
                </button>
                <button wire:click="nextStep"
                    class="flex-1 rounded-xl bg-indigo-600 py-3 text-base font-bold text-white hover:bg-indigo-700">
                    Weiter
                </button>
            </div>
        </div>
    @endif

    {{-- Schritt 3: Menü-Vorbestellung --}}
    @if ($step === 3)
        <div class="space-y-4">
            <h2 class="text-lg font-semibold dark:text-white">Vorbestellung (optional)</h2>

            @foreach ($this->availableMenuItems->groupBy('category_id') as $categoryId => $items)
                @php $category = $items->first()->category; @endphp
                <div>
                    <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ $category?->name }}
                    </h3>
                    <div class="space-y-2">
                        @foreach ($items as $item)
                            <div class="flex items-center justify-between rounded-xl border p-3 dark:border-gray-700">
                                <div class="flex-1">
                                    <p class="text-sm font-medium dark:text-white">{{ $item->name }}</p>
                                    @if ($item->description)
                                        <p class="text-xs text-gray-500">{{ $item->description }}</p>
                                    @endif
                                    <p class="text-sm font-semibold text-indigo-600">
                                        {{ number_format($item->price, 2, ',', '.') }} €
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    @if (($selectedItems[$item->id] ?? 0) > 0)
                                        <button wire:click="decrementItem({{ $item->id }})"
                                            class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-white">−</button>
                                        <span class="w-6 text-center text-sm font-medium dark:text-white">
                                            {{ $selectedItems[$item->id] }}
                                        </span>
                                    @endif
                                    <button wire:click="incrementItem({{ $item->id }})"
                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300">+</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            @if ($this->orderTotal > 0)
                <div class="rounded-xl bg-gray-100 p-3 dark:bg-gray-800">
                    <div class="flex justify-between text-sm font-semibold dark:text-white">
                        <span>Gesamt Vorbestellung:</span>
                        <span>{{ number_format($this->orderTotal, 2, ',', '.') }} €</span>
                    </div>
                </div>
            @endif

            <div class="flex gap-3">
                <button wire:click="prevStep"
                    class="flex-1 rounded-xl border py-3 text-base font-medium dark:border-gray-700 dark:text-white">
                    Zurück
                </button>
                <button wire:click="nextStep"
                    class="flex-1 rounded-xl bg-indigo-600 py-3 text-base font-bold text-white hover:bg-indigo-700">
                    Weiter
                </button>
            </div>
        </div>
    @endif

    {{-- Schritt 4: Bestätigung --}}
    @if ($step === 4)
        <div class="space-y-4 text-center">
            <div class="text-5xl">✅</div>
            <h2 class="text-xl font-bold dark:text-white">Buchung eingegangen!</h2>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Vielen Dank, {{ $guestName }}. Wir freuen uns auf Ihren Besuch am
                {{ \Carbon\Carbon::parse($date)->format('d.m.Y') }} um {{ $timeStart }} Uhr.
            </p>
            <p class="text-xs text-gray-500">Eine Bestätigung erhalten Sie per E-Mail.</p>

            <a href="{{ route('reservation.floor-plan.viewer', ['floorPlanId' => $tableId]) }}"
                class="mt-4 block w-full rounded-xl border py-3 text-base font-medium dark:border-gray-700 dark:text-white">
                Zurück zur Übersicht
            </a>
        </div>
    @endif
</div>
