<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Venues & Tischpläne" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Venues & Tischpläne'],
        ]">
            <x-ui-button wire:click="openVenueForm()" variant="primary" size="sm">
                @svg('heroicon-o-plus', 'w-4 h-4')
                Venue anlegen
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
    <div class="mx-auto max-w-4xl pt-4 space-y-6">

    {{-- ── Leer-Zustand ─────────────────────────────────────────── --}}
    @if ($this->venues->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-300 dark:border-gray-700 py-20 text-center">
            <div class="text-5xl mb-4">🎭</div>
            <h2 class="text-lg font-semibold dark:text-white">Noch kein Venue vorhanden</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Lege dein erstes Venue an, um Tischpläne zu erstellen.</p>
            <button
                wire:click="openVenueForm()"
                class="mt-5 rounded-xl bg-indigo-600 px-6 py-3 text-sm font-medium text-white hover:bg-indigo-700"
            >
                Venue erstellen
            </button>
        </div>

    {{-- ── Venue-Liste ──────────────────────────────────────────── --}}
    @else
        @foreach ($this->venues as $venue)
            <div class="overflow-hidden rounded-xl border dark:border-gray-700">

                {{-- Venue-Kopfzeile --}}
                <div class="flex items-center justify-between gap-3 bg-gray-50 px-4 py-3 dark:bg-gray-800">
                    <div>
                        <h2 class="font-semibold dark:text-white">{{ $venue->name }}</h2>
                        @if ($venue->address)
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $venue->address }}</p>
                        @endif
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <button
                            wire:click="openFloorPlanForm({{ $venue->id }})"
                            class="rounded px-3 py-1 text-xs bg-indigo-600 text-white hover:bg-indigo-700"
                        >
                            + Tischplan
                        </button>
                        <button
                            wire:click="openVenueForm({{ $venue->id }})"
                            class="rounded border px-3 py-1 text-xs dark:border-gray-600 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700"
                        >
                            Bearbeiten
                        </button>
                        <button
                            wire:click="deleteVenue({{ $venue->id }})"
                            wire:confirm="Venue und alle Tischpläne wirklich löschen?"
                            class="rounded px-3 py-1 text-xs text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"
                        >
                            Löschen
                        </button>
                    </div>
                </div>

                {{-- Tischplan-Liste --}}
                @if ($venue->floorPlans->isEmpty())
                    <div class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">
                        Noch kein Tischplan vorhanden. Klicke auf <em>+ Tischplan</em>.
                    </div>
                @else
                    <div class="divide-y dark:divide-gray-700">
                        @foreach ($venue->floorPlans as $plan)
                            <div class="flex items-center justify-between gap-3 px-4 py-3">
                                <div class="min-w-0">
                                    <span class="font-medium dark:text-white">{{ $plan->name }}</span>
                                    <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $plan->tables_count }} {{ $plan->tables_count === 1 ? 'Tisch' : 'Tische' }}
                                    </span>
                                </div>
                                <div class="flex shrink-0 flex-wrap gap-2">
                                    <a
                                        href="{{ route('reservation.floor-plan.editor', ['venueId' => $venue->id, 'floorPlanId' => $plan->id]) }}"
                                        class="rounded px-3 py-1 text-xs bg-gray-600 text-white hover:bg-gray-700"
                                    >
                                        ✏️ Tischplan Editor
                                    </a>
                                    <a
                                        href="{{ route('reservation.floor-plan.viewer', ['floorPlanId' => $plan->id]) }}"
                                        target="_blank"
                                        class="rounded px-3 py-1 text-xs bg-emerald-600 text-white hover:bg-emerald-700"
                                    >
                                        👁 3D-Ansicht
                                    </a>
                                    <button
                                        wire:click="openFloorPlanForm({{ $venue->id }}, {{ $plan->id }})"
                                        class="rounded border px-3 py-1 text-xs dark:border-gray-600 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700"
                                    >
                                        Umbenennen
                                    </button>
                                    <button
                                        wire:click="deleteFloorPlan({{ $plan->id }})"
                                        wire:confirm="Tischplan wirklich löschen?"
                                        class="rounded px-3 py-1 text-xs text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"
                                    >
                                        Löschen
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    @endif

    {{-- ── Modal: Venue erstellen / bearbeiten ─────────────────── --}}
    @if ($showVenueForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900">
                <h2 class="mb-4 text-lg font-semibold dark:text-white">
                    {{ $editingVenueId ? 'Venue bearbeiten' : 'Neues Venue' }}
                </h2>

                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name *</label>
                        <input
                            wire:model="venueName"
                            type="text"
                            placeholder="z.B. Foyer, Hauptsaal, Galerie …"
                            class="mt-1 w-full rounded-lg border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        />
                        @error('venueName') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Adresse / Beschreibung</label>
                        <input
                            wire:model="venueAddress"
                            type="text"
                            placeholder="Haus 1, EG, links …"
                            class="mt-1 w-full rounded-lg border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        wire:click="$set('showVenueForm', false)"
                        class="rounded-lg border px-4 py-2 text-sm dark:border-gray-700 dark:text-white"
                    >Abbrechen</button>
                    <button
                        wire:click="saveVenue"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                    >Speichern</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ── Modal: Tischplan erstellen / umbenennen ──────────────── --}}
    @if ($showFloorPlanForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900">
                <h2 class="mb-4 text-lg font-semibold dark:text-white">
                    {{ $editingFloorPlanId ? 'Tischplan umbenennen' : 'Neuer Tischplan' }}
                </h2>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name *</label>
                    <input
                        wire:model="floorPlanName"
                        type="text"
                        placeholder="z.B. Erdgeschoss, Terrasse …"
                        class="mt-1 w-full rounded-lg border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        autofocus
                    />
                    @error('floorPlanName') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        wire:click="$set('showFloorPlanForm', false)"
                        class="rounded-lg border px-4 py-2 text-sm dark:border-gray-700 dark:text-white"
                    >Abbrechen</button>
                    <button
                        wire:click="saveFloorPlan"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                    >Speichern</button>
                </div>
            </div>
        </div>
    @endif

    </div>
    </x-ui-page-container>
</x-ui-page>
