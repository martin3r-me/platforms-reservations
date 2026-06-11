<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Verkaufslisten" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Verkaufslisten'],
        ]">
            <x-ui-button wire:click="openListForm()" variant="primary" size="sm">
                @svg('heroicon-o-plus', 'w-4 h-4')
                Verkaufsliste
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
    <div class="pt-4 space-y-6">

    @if (session('sales_list_message'))
        <div class="rounded-lg bg-green-100 p-3 text-sm text-green-800 dark:bg-green-900/30 dark:text-green-300">
            {{ session('sales_list_message') }}
        </div>
    @endif

    {{-- Listen --}}
    @if ($this->salesLists->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-300 dark:border-gray-700 py-16 text-center">
            <h2 class="text-lg font-semibold dark:text-white">Noch keine Verkaufsliste</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Verkaufslisten sind segmentierte Sortimente (z.&nbsp;B. Konzert, Kantine), die Terminen zugewiesen werden.
            </p>
            <button wire:click="openListForm()"
                class="mt-5 rounded-xl bg-indigo-600 px-6 py-3 text-sm font-medium text-white hover:bg-indigo-700">
                Verkaufsliste erstellen
            </button>
        </div>
    @else
        <div class="overflow-hidden rounded-xl border dark:border-gray-700">
            <div class="divide-y dark:divide-gray-700">
                @foreach ($this->salesLists as $list)
                    <div wire:key="list-{{ $list->id }}" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-medium dark:text-white">{{ $list->name }}</span>
                                @if ($list->is_default)
                                    <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">Team-Standard</span>
                                @endif
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $list->menu_items_count }} Artikel
                                </span>
                            </div>
                            @if ($list->description)
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $list->description }}</p>
                            @endif
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <button wire:click="openAssignment({{ $list->id }})"
                                class="rounded px-3 py-1 text-xs bg-indigo-600 text-white hover:bg-indigo-700">Artikel zuordnen</button>
                            <button wire:click="openListForm({{ $list->id }})"
                                class="rounded border px-3 py-1 text-xs dark:border-gray-600 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">Bearbeiten</button>
                            <button wire:click="deleteList({{ $list->id }})"
                                wire:confirm="Verkaufsliste löschen? (Artikel bleiben erhalten)"
                                class="rounded px-3 py-1 text-xs text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">Löschen</button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Raum-Defaults --}}
        @if ($this->floorPlans->isNotEmpty())
            <div class="rounded-xl border dark:border-gray-700">
                <div class="border-b px-4 py-3 dark:border-gray-700">
                    <h2 class="font-semibold dark:text-white">Standard-Liste pro Raum</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Vorbelegung beim Anlegen eines Termins für diesen Raum.</p>
                </div>
                <div class="divide-y dark:divide-gray-700">
                    @foreach ($this->floorPlans as $plan)
                        <div wire:key="plan-{{ $plan->id }}" class="flex items-center justify-between gap-3 px-4 py-3">
                            <span class="text-sm dark:text-white">
                                {{ $plan->venue?->name }} – {{ $plan->name }}
                            </span>
                            <select wire:change="setFloorPlanDefault({{ $plan->id }}, $event.target.value)"
                                class="rounded-md border px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                <option value="" @selected(!$plan->default_sales_list_id)>– keine –</option>
                                @foreach ($this->salesLists as $list)
                                    <option value="{{ $list->id }}" @selected($plan->default_sales_list_id === $list->id)>{{ $list->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif

    {{-- Modal: Liste anlegen/bearbeiten --}}
    @if ($showListForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900">
                <h3 class="mb-4 text-lg font-semibold dark:text-white">
                    {{ $editingListId ? 'Verkaufsliste bearbeiten' : 'Neue Verkaufsliste' }}
                </h3>
                <div class="space-y-3">
                    <div>
                        <label class="text-xs text-gray-600 dark:text-gray-400">Name *</label>
                        <input wire:model="listName" type="text" placeholder="z.B. Konzert, Kantine …"
                            class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                        @error('listName') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs text-gray-600 dark:text-gray-400">Beschreibung</label>
                        <textarea wire:model="listDescription" rows="2"
                            class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"></textarea>
                    </div>
                    <label class="flex items-center gap-2 text-sm dark:text-white">
                        <input wire:model="listIsDefault" type="checkbox" class="rounded border-gray-300" />
                        Team-Standard (Fallback, wenn ein Termin keine Liste hat)
                    </label>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button wire:click="$set('showListForm', false)"
                        class="rounded-md border px-4 py-2 text-sm dark:border-gray-700 dark:text-white">Abbrechen</button>
                    <button wire:click="saveList"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700">Speichern</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal: Artikel zuordnen --}}
    @if ($assigningListId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="flex max-h-[85vh] w-full max-w-lg flex-col rounded-xl bg-white shadow-xl dark:bg-gray-900">
                <div class="border-b p-4 dark:border-gray-700">
                    <h3 class="text-lg font-semibold dark:text-white">Artikel zuordnen</h3>
                    <input wire:model.live.debounce.300ms="itemSearch" type="text" placeholder="Artikel suchen…"
                        class="mt-2 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                </div>
                <div class="flex-1 overflow-y-auto p-4 space-y-4">
                    @forelse ($this->categoriesWithItems as $category)
                        <div wire:key="assign-cat-{{ $category->id }}">
                            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                {{ $category->name }}
                            </p>
                            <div class="space-y-1">
                                @foreach ($category->menuItems as $item)
                                    <label wire:key="assign-item-{{ $item->id }}"
                                        class="flex cursor-pointer items-center justify-between gap-2 rounded-lg border px-3 py-2 text-sm
                                        {{ in_array((string) $item->id, $assignedItemIds) ? 'border-indigo-400 bg-indigo-50 dark:border-indigo-700 dark:bg-indigo-900/20' : 'border-gray-200 dark:border-gray-700' }}">
                                        <span class="flex items-center gap-2 dark:text-white">
                                            <input type="checkbox" wire:model.live="assignedItemIds" value="{{ $item->id }}" class="rounded border-gray-300" />
                                            {{ $item->name }}
                                        </span>
                                        <span class="text-xs text-gray-500">{{ number_format($item->price, 2, ',', '.') }} €</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">Keine Artikel gefunden.</p>
                    @endforelse
                </div>
                <div class="flex items-center justify-between border-t p-4 dark:border-gray-700">
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ count($assignedItemIds) }} ausgewählt</span>
                    <div class="flex gap-2">
                        <button wire:click="$set('assigningListId', null)"
                            class="rounded-md border px-4 py-2 text-sm dark:border-gray-700 dark:text-white">Abbrechen</button>
                        <button wire:click="saveAssignment"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700">Speichern</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    </div>
    </x-ui-page-container>
</x-ui-page>
