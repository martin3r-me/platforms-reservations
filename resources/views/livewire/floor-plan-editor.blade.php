<div class="p-4 space-y-4">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">
            Tischplan bearbeiten – {{ $this->venue->name }}
        </h1>
    </div>

    {{-- Tischplan Name --}}
    <div class="flex gap-2">
        <input
            type="text"
            wire:model="floorPlanName"
            placeholder="Name des Tischplans"
            class="flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
        />
        <button
            wire:click="saveFloorPlan"
            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
        >
            Speichern
        </button>
    </div>

    @if ($floorPlanId)
        {{-- Canvas: Tischplan --}}
        <div
            id="floor-plan-canvas"
            class="relative w-full overflow-hidden rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-900"
            style="height: 600px;"
            x-data="floorPlanEditor()"
        >
            @foreach ($this->tables as $table)
                <div
                    wire:key="table-{{ $table->id }}"
                    class="absolute flex cursor-move select-none items-center justify-center text-xs font-bold text-white shadow-md transition"
                    style="
                        left: {{ $table->x }}px;
                        top: {{ $table->y }}px;
                        width: {{ $table->width }}px;
                        height: {{ $table->height }}px;
                        background-color: {{ $table->color ?? '#4F46E5' }};
                        border-radius: {{ $table->shape === 'round' ? '50%' : '8px' }};
                    "
                    x-on:dblclick="$wire.openTableForm({{ $table->id }})"
                    x-data="draggable({{ $table->id }}, {{ $table->x }}, {{ $table->y }})"
                    x-init="init()"
                >
                    <div class="text-center leading-tight">
                        <div>{{ $table->label }}</div>
                        <div class="opacity-75">{{ $table->capacity }}P</div>
                    </div>
                </div>
            @endforeach

            {{-- Neuen Tisch hinzufügen --}}
            <button
                wire:click="openTableForm()"
                class="absolute bottom-4 right-4 flex items-center gap-1 rounded-full bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow hover:bg-indigo-700"
            >
                + Tisch
            </button>
        </div>

        {{-- Tisch-Formular Modal --}}
        @if ($showTableForm)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900">
                    <h2 class="mb-4 text-lg font-semibold dark:text-white">
                        {{ $editingTableId ? 'Tisch bearbeiten' : 'Neuer Tisch' }}
                    </h2>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm text-gray-700 dark:text-gray-300">Label</label>
                            <input wire:model="tableLabel" type="text"
                                class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                            @error('tableLabel') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm text-gray-700 dark:text-gray-300">Kapazität</label>
                                <input wire:model="tableCapacity" type="number" min="1" max="50"
                                    class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                            </div>
                            <div>
                                <label class="block text-sm text-gray-700 dark:text-gray-300">Form</label>
                                <select wire:model="tableShape"
                                    class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                    <option value="square">Eckig</option>
                                    <option value="rectangle">Rechteck</option>
                                    <option value="round">Rund</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm text-gray-700 dark:text-gray-300">Farbe</label>
                            <input wire:model="tableColor" type="color"
                                class="mt-1 h-10 w-full rounded-md border" />
                        </div>
                    </div>

                    <div class="mt-6 flex justify-between">
                        @if ($editingTableId)
                            <button
                                wire:click="deleteTable({{ $editingTableId }})"
                                wire:confirm="Tisch wirklich löschen?"
                                class="rounded-md bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700"
                            >Löschen</button>
                        @else
                            <div></div>
                        @endif

                        <div class="flex gap-2">
                            <button
                                wire:click="$set('showTableForm', false)"
                                class="rounded-md border px-4 py-2 text-sm dark:border-gray-700 dark:text-white"
                            >Abbrechen</button>
                            <button
                                wire:click="saveTable"
                                class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700"
                            >Speichern</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @else
        <p class="text-sm text-gray-500">Speichere zuerst den Tischplan, um Tische hinzuzufügen.</p>
    @endif
</div>

@push('scripts')
<script src="{{ asset('vendor/reservation/js/floor-plan.js') }}"></script>
@endpush
