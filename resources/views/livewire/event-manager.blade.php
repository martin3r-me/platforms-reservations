<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Termine" icon="heroicon-o-ticket" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Termine'],
        ]">
            <div class="flex items-center gap-2">
                @if (\Illuminate\Support\Facades\Route::has('reservation.guest.events.index'))
                    <x-ui-button variant="secondary-outline" size="sm" :href="route('reservation.guest.events.index')" target="_blank">
                        @svg('heroicon-o-globe-alt', 'w-4 h-4')
                        <span>Zur Termin-Übersicht</span>
                    </x-ui-button>
                @endif
                <x-ui-button variant="primary" size="sm" wire:click="openForm()">
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    <span>Termin</span>
                </x-ui-button>
            </div>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
    <div class="pt-4 space-y-4">

    @if (session('event_error'))
        <div class="rounded-lg border border-[var(--ui-danger)]/30 bg-[var(--ui-danger-10)] p-3 text-sm text-[var(--ui-danger)]">
            {{ session('event_error') }}
        </div>
    @endif
    @if (session('event_message'))
        <div class="rounded-lg border border-[var(--ui-success)]/30 bg-[var(--ui-success-10)] p-3 text-sm text-[var(--ui-success)]">
            {{ session('event_message') }}
        </div>
    @endif

    @if ($this->events->isEmpty())
        <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm">
            <div class="flex flex-col items-center justify-center py-16 text-[var(--ui-muted)]">
                @svg('heroicon-o-ticket', 'w-10 h-10 mb-3 opacity-40')
                <span class="text-sm font-medium text-[var(--ui-secondary)]">Noch kein Termin angelegt</span>
                <span class="text-xs mt-1 opacity-70">Ein Termin ist eine Veranstaltung mit Pausen-Slots und Räumen, für die Gäste vorbestellen können.</span>
                <div class="mt-4">
                    <x-ui-button variant="primary" size="sm" wire:click="openForm()">
                        @svg('heroicon-o-plus', 'w-4 h-4')
                        <span>Termin erstellen</span>
                    </x-ui-button>
                </div>
            </div>
        </section>
    @else
        <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
                @svg('heroicon-o-ticket', 'w-4 h-4 text-[var(--ui-muted)]')
                <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">
                    Termine
                </h2>
                <span class="ml-auto text-[11px] text-[var(--ui-muted)]">{{ $this->events->count() }}</span>
            </div>
            <div class="divide-y divide-[var(--ui-border)]/30">
                @foreach ($this->events as $event)
                    <div wire:key="event-{{ $event->id }}" class="flex items-center justify-between gap-3 px-4 py-2.5 hover:bg-[var(--ui-muted-5)] transition-colors">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-medium text-[var(--ui-secondary)]">{{ $event->name }}</span>
                                @php
                                    [$statusLabel, $statusVariant] = [
                                        'draft'     => ['Entwurf', 'muted'],
                                        'published' => ['Veröffentlicht', 'success'],
                                        'closed'    => ['Geschlossen', 'danger'],
                                    ][$event->status] ?? ['Entwurf', 'muted'];
                                @endphp
                                <x-ui-badge :variant="$statusVariant" size="xs">{{ $statusLabel }}</x-ui-badge>
                                @if ($event->room_release_mode === 'sequential')
                                    <x-ui-badge variant="info" size="xs">Sequentielle Freigabe</x-ui-badge>
                                @endif
                            </div>
                            <p class="mt-0.5 text-xs text-[var(--ui-muted)] m-0">
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
                        <div class="flex shrink-0 flex-wrap items-center justify-end gap-1.5">
                            @if ($event->status !== 'published')
                                <x-ui-button variant="primary" size="sm" wire:click="publish({{ $event->id }})">
                                    @svg('heroicon-o-rocket-launch', 'w-4 h-4')
                                    <span>Veröffentlichen</span>
                                </x-ui-button>
                            @endif
                            @if ($event->bookings_count > 0)
                                <x-ui-button variant="secondary-outline" size="sm" :href="route('reservation.events.orders', $event->id)" wire:navigate>
                                    @svg('heroicon-o-fire', 'w-4 h-4')
                                    <span>Küche</span>
                                </x-ui-button>
                            @endif
                            @if ($event->status === 'published')
                                @if (\Illuminate\Support\Facades\Route::has('reservation.guest.checkout'))
                                    <x-ui-button variant="secondary-outline" size="sm" :href="route('reservation.guest.checkout', $event->uuid)" target="_blank">
                                        @svg('heroicon-o-eye', 'w-4 h-4')
                                        <span>Gast-Ansicht</span>
                                    </x-ui-button>
                                @endif
                                <x-ui-button variant="secondary-outline" size="sm" wire:click="unpublish({{ $event->id }})">Zurückziehen</x-ui-button>
                            @endif
                            <x-ui-button variant="secondary-outline" size="sm" :iconOnly="true" wire:click="openForm({{ $event->id }})" title="Bearbeiten">
                                @svg('heroicon-o-pencil', 'w-4 h-4')
                            </x-ui-button>
                            <x-ui-button variant="secondary-outline" size="sm" :iconOnly="true" wire:click="duplicate({{ $event->id }})" title="Duplizieren">
                                @svg('heroicon-o-document-duplicate', 'w-4 h-4')
                            </x-ui-button>
                            <div class="shrink-0">
                                <x-ui-confirm-button
                                    action="delete"
                                    :value="$event->id"
                                    text=""
                                    confirmText="Wirklich löschen?"
                                    variant="danger-outline"
                                    size="sm"
                                    :icon="svg('heroicon-o-trash', 'w-4 h-4')->toHtml()"
                                />
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Modal: Termin anlegen/bearbeiten --}}
    <x-ui-modal size="lg" wire:model="showForm">
        <x-slot name="header">
            <div class="flex items-center gap-3">
                <div class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-[var(--ui-primary-10)] flex-shrink-0">
                    @svg('heroicon-o-ticket', 'w-5 h-5 text-[var(--ui-primary)]')
                </div>
                <div class="min-w-0">
                    <h3 class="text-base font-semibold text-[var(--ui-secondary)] m-0 leading-tight">
                        {{ $editingEventId ? 'Termin bearbeiten' : 'Neuer Termin' }}
                    </h3>
                    <p class="text-[12px] text-[var(--ui-muted)] m-0 mt-0.5">Veranstaltung mit Pausen-Slots und Räumen</p>
                </div>
            </div>
        </x-slot>

        <div class="space-y-5">
            {{-- Stammdaten --}}
            <x-ui-form-grid :cols="2" :gap="3">
                <div class="sm:col-span-2">
                    <x-ui-input-text name="eventName" label="Name" wire:model="eventName" placeholder="z.B. Bodo Wartke" required errorKey="eventName" />
                </div>
                <x-ui-input-date name="eventDate" label="Datum" wire:model="eventDate" required errorKey="eventDate" />
                <x-ui-input-datetime name="eventDeadline" label="Bestellschluss" wire:model="eventDeadline" :nullable="true" errorKey="eventDeadline" />
                <div class="sm:col-span-2">
                    <x-ui-input-textarea name="eventDescription" label="Beschreibung" wire:model="eventDescription" rows="2" />
                </div>
                <x-ui-input-select
                    name="eventVenueId"
                    label="Venue"
                    :options="$this->venues"
                    optionValue="id"
                    optionLabel="name"
                    :nullable="true"
                    nullLabel="– automatisch aus Raum –"
                    wire:model="eventVenueId"
                />
                <x-ui-input-select
                    name="eventSalesListId"
                    label="Verkaufsliste"
                    :options="$this->salesLists"
                    optionValue="id"
                    optionLabel="name"
                    :nullable="true"
                    nullLabel="– Team-Standard –"
                    wire:model="eventSalesListId"
                />
                <x-ui-input-select
                    name="eventReleaseMode"
                    label="Raumfreigabe"
                    :options="[
                        ['value' => 'parallel', 'label' => 'Parallel (alle Räume offen)'],
                        ['value' => 'sequential', 'label' => 'Sequentiell (Raum 2 nach Füllung von Raum 1)'],
                    ]"
                    wire:model.live="eventReleaseMode"
                />
                @if (!empty($this->linkableEventsEvents))
                    <x-ui-input-select
                        name="eventEventsEventId"
                        label="Veranstaltung (Events-Modul)"
                        :options="collect($this->linkableEventsEvents)->map(fn ($e) => ['value' => $e['id'], 'label' => $e['name'] . ' (' . $e['start_date'] . ')'])->all()"
                        :nullable="true"
                        nullLabel="– keine –"
                        wire:model="eventEventsEventId"
                    />
                @endif
            </x-ui-form-grid>

            {{-- Pausen-Slots --}}
            <section class="rounded-lg border border-[var(--ui-border)]/40 overflow-hidden">
                <div class="px-3 py-2 border-b border-[var(--ui-border)]/30 flex items-center gap-2 bg-[var(--ui-muted-5)]">
                    @svg('heroicon-o-clock', 'w-4 h-4 text-[var(--ui-muted)]')
                    <h4 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Pausen-Slots</h4>
                    <div class="ml-auto">
                        <x-ui-button variant="secondary-ghost" size="sm" wire:click="addSlot" type="button">+ Slot</x-ui-button>
                    </div>
                </div>
                <div class="p-3 space-y-2">
                    @error('slots') <p class="text-xs text-[var(--ui-danger)] m-0">{{ $message }}</p> @enderror
                    @foreach ($slots as $i => $slot)
                        <div wire:key="slot-row-{{ $i }}" class="flex items-end gap-2">
                            <div class="flex-1">
                                <x-ui-input-text name="slots.{{ $i }}.name" label="Name" size="sm" wire:model="slots.{{ $i }}.name" />
                            </div>
                            <div class="w-28">
                                <x-ui-input-text type="time" name="slots.{{ $i }}.time_start" label="Von *" size="sm" wire:model="slots.{{ $i }}.time_start" errorKey="slots.{{ $i }}.time_start" />
                            </div>
                            <div class="w-28">
                                <x-ui-input-text type="time" name="slots.{{ $i }}.time_end" label="Bis" size="sm" wire:model="slots.{{ $i }}.time_end" />
                            </div>
                            <x-ui-button variant="secondary-ghost" size="sm" wire:click="removeSlot({{ $i }})" type="button">✕</x-ui-button>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Räume --}}
            <section class="rounded-lg border border-[var(--ui-border)]/40 overflow-hidden">
                <div class="px-3 py-2 border-b border-[var(--ui-border)]/30 flex items-center gap-2 bg-[var(--ui-muted-5)]">
                    @svg('heroicon-o-building-storefront', 'w-4 h-4 text-[var(--ui-muted)]')
                    <h4 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">
                        Räume{{ $eventReleaseMode === 'sequential' ? ' · Reihenfolge = Freigabe-Reihenfolge' : '' }}
                    </h4>
                    <div class="ml-auto">
                        <x-ui-button variant="secondary-ghost" size="sm" wire:click="addRoom" type="button">+ Raum</x-ui-button>
                    </div>
                </div>
                <div class="p-3 space-y-2">
                    @if ($this->availableFloorPlans->isEmpty())
                        <p class="text-xs text-[var(--ui-muted)] m-0">
                            Noch keine Tischpläne vorhanden –
                            <a href="{{ route('reservation.venues.index') }}" wire:navigate class="text-[var(--ui-primary)] underline">zuerst unter „Venues &amp; Tischpläne“ anlegen</a>.
                        </p>
                    @elseif (empty($rooms))
                        <p class="text-xs text-[var(--ui-muted)] m-0">Noch kein Raum zugeordnet – über „+ Raum“ einen Tischplan hinzufügen.</p>
                    @endif

                    @foreach ($rooms as $i => $room)
                        <div wire:key="room-row-{{ $i }}" class="flex flex-wrap items-end gap-2">
                            <div class="min-w-[180px] flex-1">
                                <x-ui-input-select
                                    name="rooms.{{ $i }}.floor_plan_id"
                                    label="Tischplan *"
                                    size="sm"
                                    :options="$this->availableFloorPlans->map(fn ($p) => [
                                        'value' => $p->id,
                                        'label' => ($p->venue?->name ? $p->venue->name . ' – ' : '') . $p->name,
                                    ])->values()->all()"
                                    :nullable="true"
                                    nullLabel="– wählen –"
                                    wire:model.live="rooms.{{ $i }}.floor_plan_id"
                                    errorKey="rooms.{{ $i }}.floor_plan_id"
                                />
                            </div>
                            @if ($eventReleaseMode === 'sequential')
                                <div class="w-24">
                                    <x-ui-input-number name="rooms.{{ $i }}.fill_threshold_percent" label="Voll ab %" size="sm" min="1" max="100" wire:model="rooms.{{ $i }}.fill_threshold_percent" />
                                </div>
                            @endif
                            <div class="w-28">
                                <x-ui-input-number name="rooms.{{ $i }}.capacity_override" label="Plätze" size="sm" min="1" placeholder="auto" wire:model="rooms.{{ $i }}.capacity_override" />
                            </div>
                            <div class="w-36">
                                <x-ui-input-select
                                    name="rooms.{{ $i }}.open_mode"
                                    label="Status"
                                    size="sm"
                                    :options="[
                                        ['value' => 'auto', 'label' => 'Automatisch'],
                                        ['value' => 'open', 'label' => 'Immer offen'],
                                        ['value' => 'closed', 'label' => 'Geschlossen'],
                                    ]"
                                    wire:model="rooms.{{ $i }}.open_mode"
                                />
                            </div>
                            <x-ui-button variant="secondary-ghost" size="sm" wire:click="removeRoom({{ $i }})" type="button">✕</x-ui-button>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Gesperrte Tische je Raum --}}
            @if ($this->roomTables->isNotEmpty())
                <section class="rounded-lg border border-[var(--ui-border)]/40 overflow-hidden">
                    <div class="px-3 py-2 border-b border-[var(--ui-border)]/30 flex items-center gap-2 bg-[var(--ui-muted-5)]">
                        @svg('heroicon-o-no-symbol', 'w-4 h-4 text-[var(--ui-muted)]')
                        <h4 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Tische sperren</h4>
                        <span class="ml-auto text-[11px] text-[var(--ui-muted)]">{{ count($disabledTableIds) }} gesperrt</span>
                    </div>
                    <div class="p-3 space-y-3">
                        <p class="text-xs text-[var(--ui-muted)] m-0">Gesperrte Tische sind für diesen Termin nicht buchbar (z.&nbsp;B. reserviert oder defekt).</p>
                        @foreach ($this->roomTables as $plan)
                            <div wire:key="dis-plan-{{ $plan->id }}">
                                <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">{{ $plan->name }}</p>
                                @if ($plan->tables->isEmpty())
                                    <p class="text-xs text-[var(--ui-muted)] m-0">Keine Tische in diesem Raum.</p>
                                @else
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($plan->tables as $table)
                                            @php $isDisabled = in_array($table->id, $disabledTableIds); @endphp
                                            <button type="button" wire:click="toggleDisabledTable({{ $table->id }})"
                                                wire:key="dis-table-{{ $table->id }}"
                                                class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs transition-colors
                                                    {{ $isDisabled
                                                        ? 'border-[var(--ui-danger)]/40 bg-[var(--ui-danger-10)] text-[var(--ui-danger)] line-through'
                                                        : 'border-[var(--ui-border)]/60 text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">
                                                @if ($isDisabled)
                                                    @svg('heroicon-o-no-symbol', 'w-3.5 h-3.5')
                                                @endif
                                                {{ $table->label }} ({{ $table->capacity }}P)
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Hero-Bild --}}
            <div>
                <label class="block text-[12px] font-medium text-[var(--ui-muted)] mb-1">Bild (16:9, für die Termin-Übersicht)</label>
                @php $editingEvent = $editingEventId ? \Platform\Reservation\Models\Event::with('imageFile.variants')->find($editingEventId) : null; @endphp
                @if ($eventImage)
                    <img src="{{ $eventImage->temporaryUrl() }}" alt="" class="mb-2 aspect-video w-full rounded-lg object-cover" />
                @elseif ($editingEvent?->image_context_file_id && $editingEvent->imageFile)
                    <img src="{{ $editingEvent->imageUrl('medium_16_9') }}" alt="" class="mb-2 aspect-video w-full rounded-lg object-cover" />
                @endif
                <input type="file" wire:model="eventImage" accept="image/*"
                    class="w-full text-sm text-[var(--ui-muted)]" />
                <div wire:loading wire:target="eventImage" class="mt-1 text-xs text-[var(--ui-muted)]">Wird hochgeladen…</div>
                @error('eventImage') <p class="mt-1 text-xs text-[var(--ui-danger)]">{{ $message }}</p> @enderror
            </div>
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-ui-button variant="secondary-outline" size="sm" wire:click="$set('showForm', false)">Abbrechen</x-ui-button>
                <x-ui-button variant="primary" size="sm" wire:click="save">Speichern</x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>

    </div>
    </x-ui-page-container>
</x-ui-page>
