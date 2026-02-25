<div class="p-4 space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold dark:text-white">Drop-off Slots</h1>
        <button wire:click="openForm()"
            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            + Neuer Slot
        </button>
    </div>

    {{-- Datumsfilter --}}
    <div>
        <input type="date" wire:model.live="filterDate"
            class="rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
    </div>

    {{-- Slot-Liste --}}
    <div class="overflow-x-auto rounded-xl border dark:border-gray-700">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Datum</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Von</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Bis</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Kapazität</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Gebucht</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Frei</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Aktionen</th>
                </tr>
            </thead>
            <tbody class="divide-y dark:divide-gray-700">
                @forelse ($this->slots as $slot)
                    <tr wire:key="slot-{{ $slot->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="px-4 py-3 dark:text-white">{{ $slot->date->format('d.m.Y') }}</td>
                        <td class="px-4 py-3 dark:text-white">{{ $slot->time_from }}</td>
                        <td class="px-4 py-3 dark:text-white">{{ $slot->time_to }}</td>
                        <td class="px-4 py-3 text-center dark:text-white">{{ $slot->capacity }}</td>
                        <td class="px-4 py-3 text-center dark:text-white">{{ $slot->booked_count }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="font-semibold {{ $slot->remaining_capacity > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                {{ $slot->remaining_capacity }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex gap-2">
                                <button wire:click="openForm({{ $slot->id }})"
                                    class="text-xs text-gray-500 hover:underline dark:text-gray-400">Edit</button>
                                <button wire:click="delete({{ $slot->id }})"
                                    wire:confirm="Slot löschen?"
                                    class="text-xs text-red-500 hover:underline">Del</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                            Keine Slots gefunden.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Formular-Modal --}}
    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900">
                <h3 class="mb-4 text-lg font-semibold dark:text-white">
                    {{ $editingId ? 'Slot bearbeiten' : 'Neuer Drop-off Slot' }}
                </h3>
                <div class="space-y-3">
                    <div>
                        <label class="text-xs text-gray-600 dark:text-gray-400">Datum</label>
                        <input wire:model="slotDate" type="date"
                            class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                        @error('slotDate') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs text-gray-600 dark:text-gray-400">Von</label>
                            <input wire:model="slotTimeFrom" type="time"
                                class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                            @error('slotTimeFrom') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="text-xs text-gray-600 dark:text-gray-400">Bis</label>
                            <input wire:model="slotTimeTo" type="time"
                                class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                            @error('slotTimeTo') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="text-xs text-gray-600 dark:text-gray-400">Kapazität</label>
                        <input wire:model="slotCapacity" type="number" min="1"
                            class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                    </div>
                    <div>
                        <label class="text-xs text-gray-600 dark:text-gray-400">Notizen</label>
                        <textarea wire:model="slotNotes" rows="2"
                            class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"></textarea>
                    </div>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button wire:click="$set('showForm', false)"
                        class="rounded-md border px-4 py-2 text-sm dark:border-gray-700 dark:text-white">Abbrechen</button>
                    <button wire:click="save"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700">Speichern</button>
                </div>
            </div>
        </div>
    @endif
</div>
