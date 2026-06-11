<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Termine" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Termine'],
        ]">
            <x-ui-button wire:click="openForm()" variant="primary" size="sm">
                @svg('heroicon-o-plus', 'w-4 h-4')
                Termin
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
    <div class="pt-4 space-y-6">

    @if (session('event_error'))
        <div class="rounded-lg bg-red-100 p-3 text-sm text-red-800 dark:bg-red-900/30 dark:text-red-300">
            {{ session('event_error') }}
        </div>
    @endif

    @if ($this->events->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-300 dark:border-gray-700 py-16 text-center">
            <h2 class="text-lg font-semibold dark:text-white">Noch kein Termin angelegt</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Ein Termin ist eine Veranstaltung mit Pausen-Slots und Räumen, für die Gäste vorbestellen können.
            </p>
            <button wire:click="openForm()"
                class="mt-5 rounded-xl bg-indigo-600 px-6 py-3 text-sm font-medium text-white hover:bg-indigo-700">
                Termin erstellen
            </button>
        </div>
    @else
        <div class="overflow-hidden rounded-xl border dark:border-gray-700">
            <div class="divide-y dark:divide-gray-700">
                @foreach ($this->events as $event)
                    <div wire:key="event-{{ $event->id }}" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium dark:text-white">{{ $event->name }}</span>
                                @php
                                    $statusBadge = [
                                        'draft'     => ['Entwurf', 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300'],
                                        'published' => ['Veröffentlicht', 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'],
                                        'closed'    => ['Geschlossen', 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300'],
                                    ][$event->status] ?? ['Entwurf', 'bg-gray-200 text-gray-700'];
                                @endphp
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $statusBadge[1] }}">{{ $statusBadge[0] }}</span>
                                @if ($event->room_release_mode === 'sequential')
                                    <span class="rounded-full bg-sky-100 px-2 py-0.5 text-xs text-sky-700 dark:bg-sky-900/30 dark:text-sky-300">Sequentielle Freigabe</span>
                                @endif
                            </div>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                {{ $event->date->format('d.m.Y') }}
                                @if ($event->slots->isNotEmpty())
                                    · {{ $event->slots->map(fn ($s) => $s->name . ' ' . substr($s->time_start, 0, 5))->implode(', ') }}
                                @endif
                                @if ($event->venue) · {{ $event->venue->name }} @endif
                                · {{ $event->event_rooms_count }} {{ $event->event_rooms_count === 1 ? 'Raum' : 'Räume' }}
                                · {{ $event->bookings_count }} {{ $event->bookings_count === 1 ? 'Buchung' : 'Buchungen' }}
                                @if ($event->salesList) · Liste: {{ $event->salesList->name }} @endif
                            </p>
                        </div>
                        <div class="flex shrink-0 flex-wrap gap-2">
                            @if ($event->status === 'published')
                                @if (\Illuminate\Support\Facades\Route::has('reservation.guest.checkout'))
                                    <a href="{{ route('reservation.guest.checkout', $event->uuid) }}" target="_blank"
                                        class="rounded px-3 py-1 text-xs bg-emerald-600 text-white hover:bg-emerald-700">Gast-Ansicht</a>
                                @endif
                                <button wire:click="unpublish({{ $event->id }})"
                                    class="rounded border px-3 py-1 text-xs dark:border-gray-600 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">Zurückziehen</button>
                                <button wire:click="close({{ $event->id }})"
                                    class="rounded border px-3 py-1 text-xs dark:border-gray-600 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">Schließen</button>
                            @else
                                <button wire:click="publish({{ $event->id }})"
                                    class="rounded px-3 py-1 text-xs bg-indigo-600 text-white hover:bg-indigo-700">Veröffentlichen</button>
                            @endif
                            <button wire:click="openForm({{ $event->id }})"
                                class="rounded border px-3 py-1 text-xs dark:border-gray-600 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700">Bearbeiten</button>
                            <button wire:click="delete({{ $event->id }})"
                                wire:confirm="Termin und alle Slots/Raum-Zuordnungen löschen? (Buchungen bleiben erhalten)"
                                class="rounded px-3 py-1 text-xs text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">Löschen</button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Modal: Termin anlegen/bearbeiten --}}
    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="flex max-h-[90vh] w-full max-w-2xl flex-col rounded-xl bg-white shadow-xl dark:bg-gray-900">
                <div class="border-b p-4 dark:border-gray-700">
                    <h3 class="text-lg font-semibold dark:text-white">
                        {{ $editingEventId ? 'Termin bearbeiten' : 'Neuer Termin' }}
                    </h3>
                </div>

                <div class="flex-1 space-y-4 overflow-y-auto p-4">
                    {{-- Stammdaten --}}
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="text-xs text-gray-600 dark:text-gray-400">Name *</label>
                            <input wire:model="eventName" type="text" placeholder="z.B. Bodo Wartke"
                                class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                            @error('eventName') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="text-xs text-gray-600 dark:text-gray-400">Datum *</label>
                            <input wire:model="eventDate" type="date"
                                class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                            @error('eventDate') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="text-xs text-gray-600 dark:text-gray-400">Bestellschluss</label>
                            <input wire:model="eventDeadline" type="datetime-local"
                                class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-xs text-gray-600 dark:text-gray-400">Beschreibung</label>
                            <textarea wire:model="eventDescription" rows="2"
                                class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"></textarea>
                        </div>
                        <div>
                            <label class="text-xs text-gray-600 dark:text-gray-400">Venue</label>
                            <select wire:model.live="eventVenueId"
                                class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                <option value="">– wählen –</option>
                                @foreach ($this->venues as $venue)
                                    <option value="{{ $venue->id }}">{{ $venue->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-gray-600 dark:text-gray-400">Verkaufsliste</label>
                            <select wire:model="eventSalesListId"
                                class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                <option value="">– Team-Standard –</option>
                                @foreach ($this->salesLists as $list)
                                    <option value="{{ $list->id }}">{{ $list->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-gray-600 dark:text-gray-400">Raumfreigabe</label>
                            <select wire:model="eventReleaseMode"
                                class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                <option value="parallel">Parallel (alle Räume offen)</option>
                                <option value="sequential">Sequentiell (Raum 2 nach Füllung von Raum 1)</option>
                            </select>
                        </div>
                        @if (!empty($this->linkableEventsEvents))
                            <div>
                                <label class="text-xs text-gray-600 dark:text-gray-400">Mit Veranstaltung verknüpfen (Events-Modul)</label>
                                <select wire:model="eventEventsEventId"
                                    class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                    <option value="">– keine –</option>
                                    @foreach ($this->linkableEventsEvents as $linkable)
                                        <option value="{{ $linkable['id'] }}">{{ $linkable['name'] }} ({{ $linkable['start_date'] }})</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>

                    {{-- Pausen-Slots --}}
                    <div class="rounded-lg border p-3 dark:border-gray-700">
                        <div class="mb-2 flex items-center justify-between">
                            <h4 class="text-sm font-semibold dark:text-white">Pausen-Slots *</h4>
                            <button wire:click="addSlot" type="button"
                                class="text-xs text-indigo-600 hover:underline dark:text-indigo-400">+ Slot</button>
                        </div>
                        @error('slots') <p class="mb-2 text-xs text-red-500">{{ $message }}</p> @enderror
                        <div class="space-y-2">
                            @foreach ($slots as $i => $slot)
                                <div wire:key="slot-row-{{ $i }}" class="flex items-end gap-2">
                                    <div class="flex-1">
                                        <label class="text-xs text-gray-600 dark:text-gray-400">Name</label>
                                        <input wire:model="slots.{{ $i }}.name" type="text"
                                            class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-600 dark:text-gray-400">Von *</label>
                                        <input wire:model="slots.{{ $i }}.time_start" type="time"
                                            class="mt-1 w-28 rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-600 dark:text-gray-400">Bis</label>
                                        <input wire:model="slots.{{ $i }}.time_end" type="time"
                                            class="mt-1 w-28 rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                                    </div>
                                    <button wire:click="removeSlot({{ $i }})" type="button"
                                        class="mb-1 rounded px-2 py-1.5 text-xs text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20">✕</button>
                                </div>
                                @error("slots.{$i}.time_start") <p class="text-xs text-red-500">{{ $message }}</p> @enderror
                            @endforeach
                        </div>
                    </div>

                    {{-- Räume --}}
                    <div class="rounded-lg border p-3 dark:border-gray-700">
                        <div class="mb-2 flex items-center justify-between">
                            <h4 class="text-sm font-semibold dark:text-white">Räume {{ $eventReleaseMode === 'sequential' ? '(Reihenfolge = Freigabe-Reihenfolge)' : '' }}</h4>
                            <button wire:click="addRoom" type="button"
                                class="text-xs text-indigo-600 hover:underline dark:text-indigo-400"
                                @if(!$eventVenueId) disabled title="Zuerst Venue wählen" @endif>+ Raum</button>
                        </div>
                        @if (!$eventVenueId)
                            <p class="text-xs text-gray-400">Zuerst ein Venue wählen, dann Räume (Tischpläne) zuordnen.</p>
                        @endif
                        <div class="space-y-2">
                            @foreach ($rooms as $i => $room)
                                <div wire:key="room-row-{{ $i }}" class="flex flex-wrap items-end gap-2">
                                    <div class="min-w-[160px] flex-1">
                                        <label class="text-xs text-gray-600 dark:text-gray-400">Tischplan *</label>
                                        <select wire:model.live="rooms.{{ $i }}.floor_plan_id"
                                            class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                            <option value="">– wählen –</option>
                                            @foreach ($this->availableFloorPlans as $plan)
                                                <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    @if ($eventReleaseMode === 'sequential')
                                        <div>
                                            <label class="text-xs text-gray-600 dark:text-gray-400">Voll ab (%)</label>
                                            <input wire:model="rooms.{{ $i }}.fill_threshold_percent" type="number" min="1" max="100"
                                                class="mt-1 w-24 rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                                        </div>
                                    @endif
                                    <div>
                                        <label class="text-xs text-gray-600 dark:text-gray-400">Plätze (Override)</label>
                                        <input wire:model="rooms.{{ $i }}.capacity_override" type="number" min="1" placeholder="auto"
                                            class="mt-1 w-28 rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-600 dark:text-gray-400">Status</label>
                                        <select wire:model="rooms.{{ $i }}.open_mode"
                                            class="mt-1 w-32 rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                            <option value="auto">Automatisch</option>
                                            <option value="open">Immer offen</option>
                                            <option value="closed">Geschlossen</option>
                                        </select>
                                    </div>
                                    <button wire:click="removeRoom({{ $i }})" type="button"
                                        class="mb-1 rounded px-2 py-1.5 text-xs text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20">✕</button>
                                </div>
                                @error("rooms.{$i}.floor_plan_id") <p class="text-xs text-red-500">{{ $message }}</p> @enderror
                            @endforeach
                        </div>
                    </div>

                    {{-- Hero-Bild --}}
                    <div>
                        <label class="text-xs text-gray-600 dark:text-gray-400">Bild (16:9, für die Termin-Übersicht)</label>
                        @php $editingEvent = $editingEventId ? \Platform\Reservation\Models\Event::with('imageFile.variants')->find($editingEventId) : null; @endphp
                        @if ($eventImage)
                            <img src="{{ $eventImage->temporaryUrl() }}" alt="" class="mt-1 aspect-video w-full rounded-lg object-cover" />
                        @elseif ($editingEvent?->image_context_file_id && $editingEvent->imageFile)
                            <img src="{{ $editingEvent->imageUrl('medium_16_9') }}" alt="" class="mt-1 aspect-video w-full rounded-lg object-cover" />
                        @endif
                        <input type="file" wire:model="eventImage" accept="image/*"
                            class="mt-1 w-full text-sm text-gray-600 dark:text-gray-300" />
                        <div wire:loading wire:target="eventImage" class="mt-1 text-xs text-gray-500">Wird hochgeladen…</div>
                        @error('eventImage') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t p-4 dark:border-gray-700">
                    <button wire:click="$set('showForm', false)"
                        class="rounded-md border px-4 py-2 text-sm dark:border-gray-700 dark:text-white">Abbrechen</button>
                    <button wire:click="save"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700">Speichern</button>
                </div>
            </div>
        </div>
    @endif

    </div>
    </x-ui-page-container>
</x-ui-page>
