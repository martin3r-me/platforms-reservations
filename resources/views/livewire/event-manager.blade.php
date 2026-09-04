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

    {{-- Bewusst an hasAnyEvents statt an der gefilterten Liste: sonst verschwindet
         mit dem letzten Treffer auch die Filterleiste, und man kommt an die
         übrigen Termine nicht mehr heran (neuer Termin = Entwurf, Filter steht
         aber auf "Veröffentlicht"). --}}
    @if (! $this->hasAnyEvents)
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
                <span class="mr-1 text-[10px] font-semibold uppercase tracking-wider text-[color:var(--nx-faint)]">Status</span>
                @foreach (['published' => 'Veröffentlicht', 'announced' => 'Bald verfügbar', 'draft' => 'Entwurf', 'closed' => 'Bestellschluss', 'nachzubereiten' => 'Nachzubereiten', 'cancelled' => 'Abgesagt', 'all' => 'Alle'] as $val => $label)
                    <button type="button" wire:click="setStatusFilter('{{ $val }}')"
                        class="rounded-full px-2.5 py-1 transition-colors {{ $statusFilter === $val ? 'bg-[color:var(--nx-active)] font-medium text-[color:var(--nx-text)]' : 'text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]' }}">{{ $label }}</button>
                @endforeach
            </div>
            <div class="flex flex-wrap items-center gap-1 border-l border-[color:var(--nx-line)] pl-5">
                <span class="mr-1 text-[10px] font-semibold uppercase tracking-wider text-[color:var(--nx-faint)]">Zeit</span>
                @foreach (['upcoming' => 'Kommend', 'past' => 'Vergangen', 'all' => 'Alle'] as $val => $label)
                    <button type="button" wire:click="$set('timeFilter', '{{ $val }}')"
                        class="rounded-full px-2.5 py-1 transition-colors {{ $timeFilter === $val ? 'bg-[color:var(--nx-active)] font-medium text-[color:var(--nx-text)]' : 'text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]' }}">{{ $label }}</button>
                @endforeach
            </div>
            <span class="ml-auto tabular-nums text-[color:var(--nx-faint)]">{{ $this->events->count() }} Termine</span>
        </div>

        @if ($this->events->isEmpty())
            <x-nx-card>
                <x-nx-empty icon="heroicon-o-funnel">
                    <span class="text-sm font-medium text-[color:var(--nx-text)]">Kein Termin passt zu diesem Filter</span>
                    <span class="mt-1 block">Es gibt Termine, sie sind nur gerade ausgeblendet.</span>
                    <x-slot name="action">
                        <x-nx-button wire:click="resetFilters">
                            @svg('heroicon-o-arrow-path', 'w-4 h-4')
                            <span>Filter zurücksetzen</span>
                        </x-nx-button>
                    </x-slot>
                </x-nx-empty>
            </x-nx-card>
        @else
        <x-nx-card flush>
            <div>
                @foreach ($this->events as $event)
                    <div wire:key="event-{{ $event->id }}" class="flex items-center justify-between gap-3 border-b border-[color:var(--nx-line)] px-4 py-2.5 transition-colors last:border-0 hover:bg-[color:var(--nx-hover)]">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-medium text-[color:var(--nx-text)]">{{ $event->name }}</span>
                                {{-- „Veröffentlicht" ist ein Zustand für die Zukunft. Ist der Abend
                                     vorbei, widerspricht er dem „Vergangen" daneben und sagt nichts
                                     mehr. Entwurf und Abgesagt bleiben stehen – die sagen auch
                                     rückblickend etwas. --}}
                                @if (! $event->istVergangen() || in_array($event->status->value, ['draft', 'cancelled'], true))
                                    <x-nx-badge :variant="$event->status->badgeVariant()">{{ $event->status->label() }}</x-nx-badge>
                                @endif
                                @if ($event->istBestellschlussErreicht())
                                    <x-nx-badge variant="warning">Bestellschluss erreicht</x-nx-badge>
                                @endif
                                @if ($event->istNachzubereiten())
                                    <x-nx-badge variant="warning">Nachzubereiten</x-nx-badge>
                                @endif
                                @if ($event->istVergangen())
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
                                @if ($event->status->value === 'draft')
                                    <x-nx-dropdown-item wire:click="announce({{ $event->id }})">
                                        @svg('heroicon-o-megaphone', 'w-4 h-4') <span>Ankündigen</span>
                                    </x-nx-dropdown-item>
                                @endif
                                @if (in_array($event->status->value, ['announced', 'published'], true))
                                    <x-nx-dropdown-item wire:click="unpublish({{ $event->id }})">
                                        @svg('heroicon-o-arrow-uturn-left', 'w-4 h-4') <span>Zurückziehen</span>
                                    </x-nx-dropdown-item>
                                @endif
                                @if ($event->status->value === 'published')
                                    <x-nx-dropdown-item wire:click="close({{ $event->id }})">
                                        @svg('heroicon-o-lock-closed', 'w-4 h-4') <span>Bestellschluss</span>
                                    </x-nx-dropdown-item>
                                @endif
                                @if (in_array($event->status->value, ['announced', 'published', 'closed'], true))
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
                <x-nx-input-datetime name="eventDeadline" label="Bestellschluss" wire:model="eventDeadline" required errorKey="eventDeadline" />
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
                <div>
                    <x-nx-input-text type="number" name="eventMaxGuestCount" label="Größte buchbare Gruppe" wire:model="eventMaxGuestCount" :placeholder="$this->standardMaxGuestCount . ' (Team-Vorgabe)'" errorKey="eventMaxGuestCount" />
                    <p class="mt-1 text-[11px] text-[color:var(--nx-muted)]">Leer = Vorgabe aus den Einstellungen ({{ $this->standardMaxGuestCount }}).</p>
                </div>
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

                    {{-- Tischbindung: erscheint mit der ZWEITEN Pause.

                         Bei einer Pause rechnen beide Betriebsarten gleich – das Feld
                         wäre eine Frage ohne Wirkung. Dieselbe Disziplin wie beim
                         Freigabe-Schwellwert, der nur steht, wenn ein Raum folgt. --}}
                    @if (count($slots) > 1)
                        <div class="mt-3 border-t border-[color:var(--nx-line)] pt-3">
                            <x-nx-input-select
                                name="eventTableBinding"
                                label="Tisch bei mehreren Pausen"
                                :options="[
                                    ['value' => 'event', 'label' => 'Der Tisch gehört dem Gast den ganzen Abend'],
                                    ['value' => 'slot', 'label' => 'Jede Pause wird einzeln vergeben'],
                                ]"
                                :nullable="true"
                                :nullLabel="'– Team-Vorgabe (' . $this->standardTableBindingLabel . ') –'"
                                wire:model.live="eventTableBinding"
                                errorKey="eventTableBinding"
                            />
                            <p class="mt-1 text-[11px] text-[color:var(--nx-muted)]">
                                Den ganzen Abend heißt: Wer in der ersten Pause an einem Tisch sitzt, hält ihn auch in der zweiten – selbst wenn er dort nichts bestellt. Einzeln vergeben heißt: Der Saal wird zwischen den Pausen geräumt und neu verkauft, der Gast sitzt in der zweiten Pause womöglich woanders.
                            </p>

                            @if (! empty($this->ueberbelegteTische))
                                <div class="mt-2 rounded-[6px] border border-[rgba(240,140,0,.3)] bg-[rgba(240,140,0,.08)] p-2.5 text-[11px] text-[color:var(--nx-text)]">
                                    <span class="font-semibold">{{ count($this->ueberbelegteTische) }} {{ count($this->ueberbelegteTische) === 1 ? 'Tisch ist' : 'Tische sind' }} damit überbelegt:</span>
                                    {{ implode(', ', $this->ueberbelegteTische) }}.
                                    <span class="block text-[color:var(--nx-muted)]">An {{ count($this->ueberbelegteTische) === 1 ? 'ihm' : 'ihnen' }} sitzen in verschiedenen Pausen verschiedene Parteien, die zusammen nicht daraufpassen. Die Buchungen bleiben bestehen – niemand wird umgesetzt oder abgesagt.</span>
                                </div>
                            @endif
                        </div>
                    @endif
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
                        {{-- Eine Zeile je Raum, darunter EIN Satz statt Hinweisen
                             unter einzelnen Feldern. Die hingen vorher verschieden tief
                             und machten die Zeilen ausgefranst.

                             Der Prozentwert erscheint nur, wo ein Raum folgt: Er gibt
                             den NÄCHSTEN frei, am letzten bewirkt er nichts. Bei einem
                             einzigen Raum ist die Spalte damit gar nicht da. --}}
                        <div wire:key="room-row-{{ $i }}" class="rounded-md border border-[color:var(--nx-line)] p-3">
                            <div class="flex flex-wrap items-end gap-2">
                                <div class="min-w-[180px] flex-1">
                                    <x-nx-input-select
                                        name="rooms.{{ $i }}.floor_plan_id"
                                        label="Tischplan *"
                                        size="sm"
                                        :options="$this->floorPlanOptions($i)"
                                        :nullable="true"
                                        nullLabel="– wählen –"
                                        wire:model.live="rooms.{{ $i }}.floor_plan_id"
                                        errorKey="rooms.{{ $i }}.floor_plan_id"
                                    />
                                </div>
                                {{-- Nur wo ein Raum FOLGT. Am letzten - und damit auch
                                     bei einem einzigen - gibt der Wert nichts frei; ein
                                     ausgegrautes Feld mit einer Zahl darin fragt trotzdem
                                     danach, ob man etwas einstellen müsste. --}}
                                @if ($eventReleaseMode === 'sequential' && $i + 1 < count($rooms))
                                    <div class="w-28">
                                        <x-nx-input-number
                                            name="rooms.{{ $i }}.fill_threshold_percent"
                                            label="Freigabe ab %"
                                            size="sm" min="1" max="100"
                                            wire:model.live="rooms.{{ $i }}.fill_threshold_percent"
                                        />
                                    </div>
                                @endif
                                {{-- „Plätze" wirkt AUSSCHLIESSLICH als Nenner der
                                     Prozentrechnung (EventRoom::totalSeats, nur von
                                     RoomReleaseService benutzt). Ohne sequentielle
                                     Freigabe und am letzten Raum tut das Feld also
                                     nichts - dieselbe Bedingung wie beim Prozentwert. --}}
                                @if ($eventReleaseMode === 'sequential' && $i + 1 < count($rooms))
                                    <div class="w-24">
                                        <x-nx-input-number name="rooms.{{ $i }}.capacity_override" label="Plätze" size="sm" min="1" placeholder="auto" wire:model.live="rooms.{{ $i }}.capacity_override" />
                                    </div>
                                @endif
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
                                        wire:model.live="rooms.{{ $i }}.open_mode"
                                    />
                                </div>
                                <x-nx-button icon variant="ghost" wire:click="removeRoom({{ $i }})" type="button" title="Entfernen">
                                    @svg('heroicon-o-x-mark', 'w-4 h-4')
                                </x-nx-button>
                            </div>

                            {{-- Ein Satz, der beides erklärt: wann der nächste Raum
                                 aufgeht und was der Status hier bedeutet. --}}
                            <p class="m-0 mt-2 text-[11px] leading-snug text-[color:var(--nx-muted)]">
                                {{-- Ohne Zwischenvariable: Die einzeilige PHP-Direktive
                                     mit Klammer ist hier unbrauchbar, weil weiter unten
                                     in dieser Datei ein schliessender Block steht. Blade
                                     schneidet rohe PHP-Bloecke von der oeffnenden bis zur
                                     naechsten schliessenden Direktive aus - alles
                                     dazwischen waere verschluckt. --}}
                                @if (($rooms[$i]['open_mode'] ?? 'auto') === 'open')
                                    Immer buchbar, unabhängig von der Reihenfolge.
                                @elseif (($rooms[$i]['open_mode'] ?? 'auto') === 'closed')
                                    Nimmt keine neuen Buchungen an; bestehende bleiben. In der Reihenfolge übersprungen.
                                @elseif ($eventReleaseMode !== 'sequential')
                                    Buchbar, wie alle Räume bei paralleler Freigabe.
                                @elseif ($i === 0)
                                    Erster Raum – von Beginn an buchbar.
                                @else
                                    Buchbar, sobald der Raum darüber seinen Wert erreicht.
                                @endif

                                @if ($eventReleaseMode === 'sequential' && $i + 1 < count($rooms))
                                    Ab {{ $this->schwelle($i) }} % von
                                    {{ $this->plaetzeText($rooms[$i]['floor_plan_id'] ?? null, $rooms[$i]['capacity_override'] ?? null) }}
                                    öffnet „{{ $this->planName($rooms[$i + 1]['floor_plan_id'] ?? null) }}".
                                @endif
                            </p>
                        </div>
                    @endforeach
                </div>
            </section>


            {{-- Abholstationen.

                 Nur, wenn das Team welche hat – oder dieser Termin schon eine
                 zugeordnet hat. Wer die Funktion nie braucht, soll nicht bei
                 jedem Termin einen leeren Kasten sehen; angelegt wird unter
                 „Abholstationen“, dort wird sie auch entdeckt. --}}
            @if (! empty($stations) || $this->teamNutztStationen)
            <section class="overflow-hidden rounded-[8px] border border-[color:var(--nx-line)]">
                <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] bg-[color:var(--nx-bg)] px-3 py-2">
                    @svg('heroicon-o-inbox-arrow-down', 'w-4 h-4 text-[color:var(--nx-muted)]')
                    <h4 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Abholstationen</h4>
                    <div class="ml-auto">
                        <x-nx-button variant="ghost" wire:click="addStation" type="button">+ Abholstation</x-nx-button>
                    </div>
                </div>
                <div class="space-y-2 p-3">
                    @if ($this->availableStations->isEmpty())
                        <p class="m-0 text-xs text-[color:var(--nx-muted)]">
                            {{-- „In diesem Haus“, nicht „vorhanden“: Angeboten werden nur die
                                 Stationen des Hauses, zu dem der Termin gehört. Stünde hier
                                 „keine vorhanden“, während in einem anderen Haus welche
                                 stehen, suchte jemand den Fehler an der falschen Stelle. --}}
                            Für dieses Haus ist keine Abholstation angelegt –
                            <a href="{{ route('reservation.stations.index') }}" wire:navigate class="underline">unter „Abholstationen“ anlegen</a>.
                        </p>
                    @elseif (empty($stations))
                        <p class="m-0 text-xs text-[color:var(--nx-muted)]">
                            Keine Abholstation zugeordnet. Der Termin läuft dann rein über Tische.
                        </p>
                    @endif

                    @foreach ($stations as $i => $station)
                        {{-- Eine Zeile je Station, und darunter die Pausen als Häkchen.

                             Das ist der Unterschied zum Raum: Eine Station gilt JE PAUSE.
                             Zwei Pausen, Station nur in der ersten – genau dieser Fall.

                             Die Häkchen stehen für die Pausen dieses Formulars, auch für
                             noch nicht gespeicherte. Deshalb wird über die Position
                             gearbeitet und nicht über Ids. --}}
                        <div wire:key="station-row-{{ $i }}" class="rounded-md border border-[color:var(--nx-line)] p-3">
                            <div class="flex flex-wrap items-end gap-2">
                                <div class="min-w-[180px] flex-1">
                                    <x-nx-input-select
                                        name="stations.{{ $i }}.pickup_station_id"
                                        label="Abholstation"
                                        size="sm"
                                        {{-- Der Hausname steht nur davor, solange mehrere
                                             infrage kommen - also bevor ein Raum gewaehlt ist.
                                             Danach waere er in jeder Zeile derselbe. --}}
                                        :options="$this->availableStations->map(fn ($s) => ['value' => $s->id, 'label' => ($this->availableStations->pluck('venue_id')->unique()->count() > 1 && $s->venue?->name ? $s->venue->name . ' – ' : '') . $s->name])->all()"
                                        :nullable="true"
                                        nullLabel="– bitte wählen –"
                                        wire:model.live="stations.{{ $i }}.pickup_station_id"
                                        errorKey="stations.{{ $i }}.pickup_station_id"
                                    />
                                </div>
                                <div class="w-32">
                                    <x-nx-input-number name="stations.{{ $i }}.capacity_override" label="Gäste je Pause" size="sm" min="1"
                                        placeholder="Vorgabe" wire:model.live="stations.{{ $i }}.capacity_override" />
                                </div>
                                <div class="ml-auto">
                                    <x-nx-button variant="ghost" wire:click="removeStation({{ $i }})" type="button">Entfernen</x-nx-button>
                                </div>
                            </div>

                            <div class="mt-2">
                                <p class="m-0 mb-1.5 text-[11px] font-semibold text-[color:var(--nx-muted)]">Geöffnet in</p>
                                @if (empty($slots))
                                    <p class="m-0 text-xs text-[color:var(--nx-muted)]">Erst eine Pause anlegen.</p>
                                @else
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($slots as $p => $slot)
                                            {{-- Block-Fassung, nicht @php(...): Der Einzeiler wird
                                                 nur OHNE Leerzeichen vor der Klammer erkannt, sonst
                                                 oeffnet er einen Rohblock und verschluckt alles bis
                                                 zum naechsten @endphp - hier also den Rest der Datei.
                                                 Genau dafuer gibt es BladeKompiliertTest. --}}
                                            @php $an = in_array($p, $stations[$i]['slot_indexes'] ?? [], true); @endphp
                                            <button type="button" wire:click="toggleStationSlot({{ $i }}, {{ $p }})"
                                                class="inline-flex items-center gap-1 rounded-[6px] border px-2 py-1 text-[11px] transition-colors"
                                                style="{{ $an
                                                    ? 'border-color:var(--nx-accent); color:var(--nx-accent);'
                                                    : 'border-color:var(--nx-line); color:var(--nx-muted);' }}">
                                                @if ($an)
                                                    @svg('heroicon-o-check', 'w-3 h-3')
                                                @endif
                                                <span>{{ $slot['name'] ?: 'Pause ' . ($p + 1) }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                                @error("stations.{$i}.slot_indexes")
                                    <p class="m-0 mt-1 text-[11px]" style="color:var(--nx-danger)">{{ $message }}</p>
                                @enderror
                            </div>

                            <p class="m-0 mt-2 text-[11px] text-[color:var(--nx-faint)]">
                                Keine Sitzplätze: Die Zahl sagt, wie viele Gäste hier in <strong>einer</strong> Pause
                                bedient werden können. Leer = Vorgabe der Station. Eine Abholstation zählt nicht zur
                                Raumfreigabe – sie ist offen, sobald der Termin sie in dieser Pause führt.
                            </p>
                        </div>
                    @endforeach
                </div>
            </section>
            @endif


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
                @include('reservation::partials.datei-upload', [
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

    {{-- Rückfrage vor dem Entfernen eines Raums, an dem Buchungen hängen.

         Als eigenes Modal und nicht über wire:confirm: Der Browser-Dialog kann
         nichts erklären. Hier steht die Zahl – und die Alternative, die
         meistens gemeint ist.

         DIESE REIHENFOLGE IST ABSICHT: Das Terminformular darüber ist selbst
         ein Modal, und beide bringen dieselbe Ebene mit (z-[100]/z-[101]).
         Bei gleicher Ebene gewinnt, was später im Dokument steht – deshalb
         liegt die Rückfrage über dem Formular und nicht dahinter. Wer diesen
         Block nach oben verschiebt, macht sie unsichtbar.

         Escape schließt beide zugleich (die Komponente lauscht am Fenster).
         Das ist verkraftbar: Die Rückfrage tut von sich aus nichts, entfernt
         wird nur über den Knopf. --}}
    {{-- md, nicht sm: Drei Knoepfe mit sprechenden Beschriftungen passen in ein
         schmales Fenster nicht nebeneinander - "Abbrechen" wurde links
         abgeschnitten. Die Beschriftungen zu kuerzen waere der falsche Weg
         gewesen: Sie tragen hier den Unterschied zwischen "schliessen" und
         "entfernen", und genau der ist die Frage. --}}
    <x-nx-modal size="md" wire:model="showRoomRemoveConfirm">
        <x-slot name="header">
            <h3 class="m-0 text-base font-semibold leading-tight text-[color:var(--nx-text)]">Raum entfernen?</h3>
            <p class="m-0 mt-1 text-xs text-[color:var(--nx-muted)]">{{ $this->roomRemoveName() }}</p>
        </x-slot>

        <div class="space-y-3">
            <x-nx-callout variant="warning">
                An diesem Raum {{ $roomRemoveBookings === 1 ? 'hängt' : 'hängen' }}
                <strong>{{ $roomRemoveBookings }}</strong>
                {{ $roomRemoveBookings === 1 ? 'aktive Buchung' : 'aktive Buchungen' }}.
            </x-nx-callout>

            <p class="m-0 text-sm text-[color:var(--nx-muted)]">
                Die Buchungen bleiben bestehen – sie verlieren aber ihren Bezug zum Termin
                und tauchen in der Auslastung nicht mehr auf. Der Gast sitzt am Abend
                trotzdem dort.
            </p>

            <p class="m-0 text-sm text-[color:var(--nx-muted)]">
                Meist ist <strong>Geschlossen</strong> gemeint: Der Raum nimmt keine neuen
                Buchungen mehr an, die bestehenden bleiben sichtbar.
            </p>
        </div>

        <x-slot name="footer">
            {{-- flex-wrap als Netz: Wird es doch einmal eng (schmales Fenster,
                 groessere Schrift), rutschen die beiden Aktionen unter
                 "Abbrechen", statt aus dem Fenster zu laufen. --}}
            <div class="flex flex-wrap items-center justify-between gap-2">
                <x-nx-button variant="secondary-outline" wire:click="closeRoomRemoveModal">Abbrechen</x-nx-button>
                <div class="flex flex-wrap items-center gap-2">
                    <x-nx-button variant="primary" wire:click="closeRoomInsteadAndCloseModal">Stattdessen schließen</x-nx-button>
                    <x-nx-button variant="danger-outline" wire:click="removeRoomAndCloseModal">Trotzdem entfernen</x-nx-button>
                </div>
            </div>
        </x-slot>
    </x-nx-modal>

    </div>
    </x-ui-page-container>
</x-ui-page>
