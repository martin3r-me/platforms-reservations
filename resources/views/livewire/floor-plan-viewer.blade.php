<div class="min-h-screen bg-gray-50 dark:bg-gray-950">
    {{-- Header --}}
    <div class="sticky top-0 z-10 bg-white px-4 py-3 shadow dark:bg-gray-900">
        <h1 class="text-center text-lg font-bold text-gray-900 dark:text-white">
            {{ $this->floorPlan->name }}
        </h1>

        {{-- Datum & Zeit wählen --}}
        <div class="mt-2 flex gap-2">
            <input
                type="date"
                wire:model.live="selectedDate"
                class="flex-1 rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            />
            <input
                type="time"
                wire:model.live="selectedTimeStart"
                placeholder="Von"
                class="w-28 rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            />
        </div>
    </div>

    {{-- Legende --}}
    <div class="flex items-center justify-center gap-4 px-4 py-2 text-xs text-gray-600 dark:text-gray-400">
        <span class="flex items-center gap-1">
            <span class="inline-block h-3 w-3 rounded-full bg-green-400"></span> Frei
        </span>
        <span class="flex items-center gap-1">
            <span class="inline-block h-3 w-3 rounded-full bg-red-400"></span> Belegt
        </span>
    </div>

    {{-- SVG Tischplan (Touch-optimiert) --}}
    <div
        class="relative mx-auto mt-2 w-full max-w-2xl overflow-auto rounded-xl"
        style="touch-action: pan-x pan-y;"
        x-data="{ scale: 1 }"
        x-on:wheel.prevent="scale = Math.min(3, Math.max(0.5, scale - $event.deltaY * 0.001))"
    >
        <svg
            viewBox="0 0 800 600"
            class="w-full"
            x-bind:style="`transform: scale(${scale}); transform-origin: center;`"
        >
            {{-- Hintergrund --}}
            <rect width="800" height="600" fill="#F3F4F6" rx="12"/>

            @foreach ($this->tableAvailability as $tableId => $info)
                @php $table = $info['table']; @endphp
                <g
                    wire:key="viewer-table-{{ $table->id }}"
                    class="cursor-pointer"
                    wire:click="selectTable({{ $table->id }})"
                >
                    @if ($table->shape === 'round')
                        <circle
                            cx="{{ $table->x + $table->width / 2 }}"
                            cy="{{ $table->y + $table->height / 2 }}"
                            r="{{ min($table->width, $table->height) / 2 }}"
                            class="{{ $info['color_class'] }} transition-colors"
                            stroke="white"
                            stroke-width="2"
                        />
                    @else
                        <rect
                            x="{{ $table->x }}"
                            y="{{ $table->y }}"
                            width="{{ $table->width }}"
                            height="{{ $table->height }}"
                            rx="{{ $table->shape === 'rectangle' ? '4' : '8' }}"
                            class="{{ $info['color_class'] }} transition-colors"
                            stroke="white"
                            stroke-width="2"
                        />
                    @endif

                    <text
                        x="{{ $table->x + $table->width / 2 }}"
                        y="{{ $table->y + $table->height / 2 - 5 }}"
                        text-anchor="middle"
                        fill="white"
                        font-size="12"
                        font-weight="bold"
                    >{{ $table->label }}</text>
                    <text
                        x="{{ $table->x + $table->width / 2 }}"
                        y="{{ $table->y + $table->height / 2 + 10 }}"
                        text-anchor="middle"
                        fill="white"
                        font-size="10"
                        opacity="0.85"
                    >{{ $table->capacity }}P</text>
                </g>
            @endforeach
        </svg>
    </div>

    {{-- Sticky CTA --}}
    @if ($selectedTableId)
        @php $selectedInfo = $this->tableAvailability[$selectedTableId] ?? null; @endphp
        <div class="fixed bottom-0 left-0 right-0 border-t bg-white p-4 shadow-lg dark:bg-gray-900 dark:border-gray-700">
            <p class="mb-2 text-sm text-gray-600 dark:text-gray-400">
                Ausgewählt: <strong class="text-gray-900 dark:text-white">{{ $selectedInfo['table']->label }}</strong>
                ({{ $selectedInfo['table']->capacity }} Personen)
            </p>
            <a
                href="{{ route('reservation.bookings.create', ['tableId' => $selectedTableId, 'date' => $selectedDate, 'timeStart' => $selectedTimeStart]) }}"
                class="block w-full rounded-xl bg-indigo-600 py-3 text-center text-base font-bold text-white hover:bg-indigo-700 active:bg-indigo-800"
            >
                Jetzt buchen
            </a>
        </div>
    @endif
</div>
