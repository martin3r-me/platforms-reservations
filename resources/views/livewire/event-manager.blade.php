<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Termine" icon="heroicon-o-ticket" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Termine'],
        ]">
            <x-nx-button variant="primary" wire:click="openForm()">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Termin</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
    <div class="space-y-5">

    @if (session('event_error'))
        <x-nx-callout variant="danger">{{ session('event_error') }}</x-nx-callout>
    @endif
    @if (session('event_message'))
        <x-nx-callout variant="success">{{ session('event_message') }}</x-nx-callout>
    @endif

    @if ($this->events->isEmpty())
        <x-nx-card>
            <x-nx-empty icon="heroicon-o-ticket">
                <span class="text-sm font-medium text-[color:var(--nx-text)]">Noch kein Termin angelegt</span>
                <span class="mt-1 block">Ein Termin ist eine Veranstaltung mit Pausen-Slots und Räumen, für die Gäste vorbestellen können.</span>
                <x-slot name="action">
                    <x-nx-button variant="primary" wire:click="openForm()">
                        @svg('heroicon-o-plus', 'w-4 h-4')
                        <span>Termin erstellen</span>
                    </x-nx-button>
                </x-slot>
            </x-nx-empty>
        </x-nx-card>
    @else
        {{-- Filter: Status + Zeit, rahmenlos --}}
        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs">
            <div class="flex flex-wrap items-center gap-1">
                <span class="text-[color:var(--nx-faint)]">Status</span>
                @foreach (['published' => 'Veröffentlicht', 'draft' => 'Entwurf', 'closed' => 'Bestellschluss', 'cancelled' => 'Abgesagt', 'all' => 'Alle'] as $val => $label)
                    <button type="button" wire:click="$set('statusFilter', '{{ $val }}')"
                        class="rounded-full px-2.5 py-1 transition-colors {{ $statusFilter === $val ? 'bg-[color:var(--nx-active)] font-medium text-[color:var(--nx-text)]' : 'text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]' }}">{{ $label }}</button>
                @endforeach
            </div>
            <div class="flex flex-wrap items-center gap-1">
                <span class="text-[color:var(--nx-faint)]">Zeit</span>
                @foreach (['upcoming' => 'Kommend', 'past' => 'Vergangen', 'all' => 'Alle'] as $val => $label)
                    <button type="button" wire:click="$set('timeFilter', '{{ $val }}')"
                        class="rounded-full px-2.5 py-1 transition-colors {{ $timeFilter === $val ? 'bg-[color:var(--nx-active)] font-medium text-[color:var(--nx-text)]' : 'text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]' }}">{{ $label }}</button>
                @endforeach
            </div>
            <span class="ml-auto tabular-nums text-[color:var(--nx-faint)]">{{ $this->events->count() }} Termine</span>
        </div>

        <x-nx-card flush>
            <div>
                @php $eventStatusVariant = ['published' => 'success', 'draft' => 'neutral', 'closed' => 'warning', 'cancelled' => 'danger']; @endphp
                @foreach ($this->events as $event)
                    <div wire:key="event-{{ $event->id }}" class="flex items-center justify-between gap-3 border-b border-[color:var(--nx-line)] px-4 py-2.5 transition-colors last:border-0 hover:bg-[color:var(--nx-hover)]">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-medium text-[color:var(--nx-text)]">{{ $event->name }}</span>
                                <x-nx-badge :variant="$eventStatusVariant[$event->status->value] ?? 'neutral'">{{ $event->status->label() }}</x-nx-badge>
                                @if ($event->date->isPast())
                                    <x-nx-badge>Vergangen</x-nx-badge>
                                @endif
                                @if ($event->room_release_mode === 'sequential')
                                    <x-nx-badge variant="info">Sequentielle Freigabe</x-nx-badge>
                                @endif
                            </div>
                            <p class="m-0 mt-0.5 text-xs text-[color:var(--nx-muted)]">
                                {{ $event->date->format('d.m.Y') }}
                                @if ($event->slots->isNotEmpty())
                                    · {{ $event->slots->map(fn ($s) => $s->displayLabel())->implode(', ') }}
                                @endif
                                @if ($event->venue) · {{ $event->venue->name }} @endif
                                · {{ $event->event_rooms_count }} {{ $event->event_rooms_count === 1 ? 'Raum' : 'Räume' }}
                                · {{ $event->bookings_count }} {{ $event->bookings_count === 1 ? 'Buchung' : 'Buchungen' }}
                                @if ($event->salesList) · Liste: {{ $event->salesList->name }} @endif
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center justify-end gap-1">
                            @if ($event->status->value !== 'published')
                                <x-nx-button variant="primary" wire:click="publish({{ $event->id }})">
                                    @svg('heroicon-o-rocket-launch', 'w-4 h-4')
                                    <span>Veröffentlichen</span>
                                </x-nx-button>
                            @endif
                            <x-nx-dropdown align="end">
                                <x-nx-dropdown-item wire:click="openForm({{ $event->id }})">
                                    @svg('heroicon-o-pencil', 'w-4 h-4') <span>Bearbeiten</span>
                                </x-nx-dropdown-item>
                                <x-nx-dropdown-item wire:click="duplicate({{ $event->id }})">
                                    @svg('heroicon-o-document-duplicate', 'w-4 h-4') <span>Duplizieren</span>
                                </x-nx-dropdown-item>
                                @if ($event->status->value === 'published')
                                    <x-nx-dropdown-item wire:click="unpublish({{ $event->id }})">
                                        @svg('heroicon-o-arrow-uturn-left', 'w-4 h-4') <span>Zurückziehen</span>
                                    </x-nx-dropdown-item>
                                    <x-nx-dropdown-item wire:click="close({{ $event->id }})">
                                        @svg('heroicon-o-lock-closed', 'w-4 h-4') <span>Bestellschluss</span>
                                    </x-nx-dropdown-item>
                                @endif
                                @if (in_array($event->status->value, ['published', 'closed'], true))
                                    <x-nx-dropdown-item wire:click="cancel({{ $event->id }})">
                                        @svg('heroicon-o-x-circle', 'w-4 h-4') <span>Absagen</span>
                                    </x-nx-dropdown-item>
                                @endif
                                <x-nx-dropdown-divider />
                                <x-nx-dropdown-item variant="danger" wire:click="delete({{ $event->id }})" wire:confirm="Termin wirklich löschen?">
                                    @svg('heroicon-o-trash', 'w-4 h-4') <span>Löschen</span>
                                </x-nx-dropdown-item>
                            </x-nx-dropdown>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-nx-card>
    @endif

    {{-- Modal: Termin anlegen/bearbeiten --}}
    <x-nx-modal size="lg" wire:model="showForm">
        <x-slot name="header">
            <h3 class="m-0 text-base font-semibold leading-tight text-[color:var(--nx-text)]">
                {{ $editingEventId ? 'Termin bearbeiten' : 'Neuer Termin' }}
            </h3>
            <p class="m-0 mt-1 text-xs text-[color:var(--nx-muted)]">Veranstaltung mit Pausen-Slots und Räumen</p>
        </x-slot>

        <div class="space-y-5">
            {{-- Stammdaten --}}
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-nx-input-text name="eventName" label="Name" wire:model="eventName" placeholder="z.B. Bodo Wartke" required errorKey="eventName" />
                </div>
                <x-nx-input-date name="eventDate" label="Datum" wire:model="eventDate" required errorKey="eventDate" />
                <x-nx-input-datetime name="eventDeadline" label="Bestellschluss" wire:model="eventDeadline" :nullable="true" errorKey="eventDeadline" />
                <div class="sm:col-span-2">
                    <x-nx-input-textarea name="eventDescription" label="Beschreibung" wire:model="eventDescription" rows="2" />
                </div>
                <x-nx-input-select
                    name="eventVenueId"
                    label="Venue"
                    :options="$this->venues"
                    optionValue="id"
                    optionLabel="name"
                    :nullable="true"
                    nullLabel="– automatisch aus Raum –"
                    wire:model="eventVenueId"
                />
                <x-nx-input-select
                    name="eventSalesListId"
                    label="Verkaufsliste"
                    :options="$this->salesLists"
                    optionValue="id"
                    optionLabel="name"
                    :nullable="true"
                    nullLabel="– Team-Standard –"
                    wire:model="eventSalesListId"
                />
                <x-nx-input-select
                    name="eventReleaseMode"
                    label="Raumfreigabe"
                    :options="[
                        ['value' => 'parallel', 'label' => 'Parallel (alle Räume offen)'],
                        ['value' => 'sequential', 'label' => 'Sequentiell (Raum 2 nach Füllung von Raum 1)'],
                    ]"
                    wire:model.live="eventReleaseMode"
                />
                @if (!empty($this->linkableEventsEvents))
                    <x-nx-input-select
                        name="eventEventsEventId"
                        label="Veranstaltung (Events-Modul)"
                        :options="collect($this->linkableEventsEvents)->map(fn ($e) => ['value' => $e['id'], 'label' => $e['name'] . ' (' . $e['start_date'] . ')'])->all()"
                        :nullable="true"
                        nullLabel="– keine –"
                        wire:model="eventEventsEventId"
                    />
                @endif
            </div>

            {{-- Pausen-Slots --}}
            <section class="overflow-hidden rounded-[8px] border border-[color:var(--nx-line)]">
                <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] bg-[color:var(--nx-bg)] px-3 py-2">
                    @svg('heroicon-o-clock', 'w-4 h-4 text-[color:var(--nx-muted)]')
                    <h4 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Pausen-Slots</h4>
                    <div class="ml-auto">
                        <x-nx-button variant="ghost" wire:click="addSlot" type="button">+ Slot</x-nx-button>
                    </div>
                </div>
                <div class="space-y-2 p-3">
                    <p class="m-0 text-[11px] text-[color:var(--nx-muted)]">Pausen sind optional. Zeiten können leer bleiben – ein Termin ist auch ohne Pausenangabe speicherbar (zum Veröffentlichen wird jedoch mindestens eine Pause benötigt).</p>
                    @error('slots') <p class="m-0 text-xs text-[color:var(--nx-danger)]">{{ $message }}</p> @enderror
                    @foreach ($slots as $i => $slot)
                        <div wire:key="slot-row-{{ $i }}" class="flex items-end gap-2">
                            <div class="flex-1">
                                <x-nx-input-text name="slots.{{ $i }}.name" label="Name" size="sm" wire:model="slots.{{ $i }}.name" />
                            </div>
                            <div class="w-28">
                                <x-nx-input-text type="time" name="slots.{{ $i }}.time_start" label="Von" size="sm" wire:model="slots.{{ $i }}.time_start" errorKey="slots.{{ $i }}.time_start" />
                            </div>
                            <div class="w-28">
                                <x-nx-input-text type="time" name="slots.{{ $i }}.time_end" label="Bis" size="sm" wire:model="slots.{{ $i }}.time_end" />
                            </div>
                            <x-nx-button icon variant="ghost" wire:click="removeSlot({{ $i }})" type="button" title="Entfernen">
                                @svg('heroicon-o-x-mark', 'w-4 h-4')
                            </x-nx-button>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Räume --}}
            <section class="overflow-hidden rounded-[8px] border border-[color:var(--nx-line)]">
                <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] bg-[color:var(--nx-bg)] px-3 py-2">
                    @svg('heroicon-o-building-storefront', 'w-4 h-4 text-[color:var(--nx-muted)]')
                    <h4 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">
                        Räume{{ $eventReleaseMode === 'sequential' ? ' · Reihenfolge = Freigabe-Reihenfolge' : '' }}
                    </h4>
                    <div class="ml-auto">
                        <x-nx-button variant="ghost" wire:click="addRoom" type="button">+ Raum</x-nx-button>
                    </div>
                </div>
                <div class="space-y-2 p-3">
                    @if ($this->availableFloorPlans->isEmpty())
                        <p class="m-0 text-xs text-[color:var(--nx-muted)]">
                            Noch keine Tischpläne vorhanden –
                            <a href="{{ route('reservation.venues.index') }}" wire:navigate class="underline">zuerst unter „Venues &amp; Tischpläne“ anlegen</a>.
                        </p>
                    @elseif (empty($rooms))
                        <p class="m-0 text-xs text-[color:var(--nx-muted)]">Noch kein Raum zugeordnet – über „+ Raum“ einen Tischplan hinzufügen.</p>
                    @endif

                    @foreach ($rooms as $i => $room)
                        <div wire:key="room-row-{{ $i }}" class="flex flex-wrap items-end gap-2">
                            <div class="min-w-[180px] flex-1">
                                <x-nx-input-select
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
                                    <x-nx-input-number name="rooms.{{ $i }}.fill_threshold_percent" label="Voll ab %" size="sm" min="1" max="100" wire:model="rooms.{{ $i }}.fill_threshold_percent" />
                                </div>
                            @endif
                            <div class="w-28">
                                <x-nx-input-number name="rooms.{{ $i }}.capacity_override" label="Plätze" size="sm" min="1" placeholder="auto" wire:model="rooms.{{ $i }}.capacity_override" />
                            </div>
                            <div class="w-36">
                                <x-nx-input-select
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
                            <x-nx-button icon variant="ghost" wire:click="removeRoom({{ $i }})" type="button" title="Entfernen">
                                @svg('heroicon-o-x-mark', 'w-4 h-4')
                            </x-nx-button>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Gesperrte Tische je Raum --}}
            @if ($this->roomTables->isNotEmpty())
                <section class="overflow-hidden rounded-[8px] border border-[color:var(--nx-line)]">
                    <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] bg-[color:var(--nx-bg)] px-3 py-2">
                        @svg('heroicon-o-no-symbol', 'w-4 h-4 text-[color:var(--nx-muted)]')
                        <h4 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Tische sperren</h4>
                        <span class="ml-auto text-[11px] tabular-nums text-[color:var(--nx-faint)]">{{ count($disabledTableIds) }} gesperrt</span>
                    </div>
                    <div class="space-y-3 p-3">
                        <p class="m-0 text-xs text-[color:var(--nx-muted)]">Gesperrte Tische sind für diesen Termin nicht buchbar (z.&nbsp;B. reserviert oder defekt).</p>
                        @foreach ($this->roomTables as $plan)
                            <div wire:key="dis-plan-{{ $plan->id }}">
                                <p class="m-0 mb-1.5 text-xs font-semibold text-[color:var(--nx-muted)]">{{ $plan->name }}</p>
                                @if ($plan->tables->isEmpty())
                                    <p class="m-0 text-xs text-[color:var(--nx-muted)]">Keine Tische in diesem Raum.</p>
                                @else
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($plan->tables as $table)
                                            @php $isDisabled = in_array($table->id, $disabledTableIds); @endphp
                                            <button type="button" wire:click="toggleDisabledTable({{ $table->id }})"
                                                wire:key="dis-table-{{ $table->id }}"
                                                class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs transition-colors
                                                    {{ $isDisabled
                                                        ? 'border-[color:var(--nx-danger)] bg-[rgba(224,49,49,.08)] text-[color:var(--nx-danger)] line-through'
                                                        : 'border-[color:var(--nx-line-strong)] text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)]' }}">
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
                <label class="mb-1 block text-xs font-medium text-[color:var(--nx-text)]">Bild (16:9, für die Termin-Übersicht)</label>
                @php $editingEvent = $editingEventId ? \Platform\Reservation\Models\Event::with('imageFile.variants')->find($editingEventId) : null; @endphp
                @if ($eventImage)
                    <img src="{{ $eventImage->temporaryUrl() }}" alt="" class="mb-2 aspect-video w-full rounded-[8px] object-cover" />
                @elseif ($editingEvent?->image_context_file_id && $editingEvent->imageFile)
                    <img src="{{ $editingEvent->imageUrl('medium_16_9') }}" alt="" class="mb-2 aspect-video w-full rounded-[8px] object-cover" />
                @endif
                @include('reservation::partials.image-upload', [
                    'model' => 'eventImage',
                    'hint'  => '16:9 empfohlen · JPG, PNG oder WebP · max. 20 MB.',
                ])
            </div>
        </div>

        <x-slot name="footer">
            <x-nx-button wire:click="$set('showForm', false)">Abbrechen</x-nx-button>
            <x-nx-button variant="primary" wire:click="save">Speichern</x-nx-button>
        </x-slot>
    </x-nx-modal>

    </div>
    </x-ui-page-container>
</x-ui-page>
