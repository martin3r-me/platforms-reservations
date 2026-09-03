<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Abholstationen" icon="heroicon-o-inbox-arrow-down" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Abholstationen'],
        ]" />
    </x-slot>

    <x-ui-page-container width="contained">
    <div class="space-y-5">

    @if (session('station_error'))
        <x-nx-callout variant="danger">{{ session('station_error') }}</x-nx-callout>
    @endif

    {{-- Was eine Station ist, steht einmal hier und nicht in jeder Zeile.

         Der Satz ist nötig: „Abholstation" klingt nach einem Ort mit Plätzen,
         und die Zahl daneben heißt „Gäste je Pause", nicht „Stühle". Wer das
         verwechselt, pflegt sein Foyer wie einen Tisch. --}}
    <x-nx-callout variant="info">
        Eine Abholstation ist der zweite mögliche Zielort einer Bestellung: Der Gast sitzt
        nicht an einem Tisch, sondern holt in der Pause selbst ab – „Foyer links", „Rang 1 Bar".
        Sie gehört zum Haus, nicht zu einem Raum, und taucht in keiner Platzrechnung auf.
    </x-nx-callout>

    @if ($this->venues->isEmpty())
        <x-nx-card>
            <x-nx-empty icon="heroicon-o-building-storefront">
                <span class="text-sm font-medium text-[color:var(--nx-text)]">Noch kein Haus vorhanden</span>
                <span class="mt-1 block">Abholstationen gehören zu einem Haus – lege zuerst eines an.</span>
                <x-slot name="action">
                    <x-nx-button variant="primary" :href="route('reservation.venues.index')" wire:navigate>
                        <span>Zu den Venues</span>
                    </x-nx-button>
                </x-slot>
            </x-nx-empty>
        </x-nx-card>
    @else
        @foreach ($this->venues as $venue)
            <x-nx-card flush wire:key="venue-{{ $venue->id }}">
                <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
                    @svg('heroicon-o-building-storefront', 'w-4 h-4 text-[color:var(--nx-muted)]')
                    <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">{{ $venue->name }}</h2>
                    <div class="ml-auto shrink-0">
                        <x-nx-button variant="primary" wire:click="openForm({{ $venue->id }})">
                            @svg('heroicon-o-plus', 'w-4 h-4')
                            <span>Abholstation</span>
                        </x-nx-button>
                    </div>
                </div>

                @if ($venue->pickupStations->isEmpty())
                    <div class="px-4 py-4 text-sm text-[color:var(--nx-muted)]">
                        Noch keine Abholstation in diesem Haus.
                    </div>
                @else
                    <div>
                        @foreach ($venue->pickupStations as $station)
                            <div wire:key="station-{{ $station->id }}"
                                class="group flex items-center justify-between gap-3 border-b border-[color:var(--nx-line)] px-4 py-2.5 transition-colors last:border-0 hover:bg-[color:var(--nx-hover)]">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span @class([
                                            'text-sm font-medium text-[color:var(--nx-text)]',
                                            'line-through' => ! $station->is_active,
                                        ])>{{ $station->name }}</span>
                                        @unless ($station->is_active)
                                            <x-nx-badge variant="neutral">abgeschaltet</x-nx-badge>
                                        @endunless
                                    </div>
                                    <p class="m-0 mt-0.5 text-xs text-[color:var(--nx-muted)]">
                                        {{-- Leer heißt unbegrenzt, und das steht als Wort da.
                                             Eine leere Stelle ließe offen, ob niemand die Zahl
                                             gepflegt hat oder ob es keine gibt. --}}
                                        @if ($station->capacity_per_slot)
                                            bis {{ $station->capacity_per_slot }} Gäste je Pause
                                        @else
                                            ohne Obergrenze
                                        @endif
                                        @if ($station->floorPlan) · liegt in {{ $station->floorPlan->name }} @endif
                                        @if ($station->description) · {{ $station->description }} @endif
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-0.5 opacity-0 transition-opacity focus-within:opacity-100 group-hover:opacity-100">
                                    <x-nx-button icon variant="ghost" wire:click="openForm({{ $venue->id }}, {{ $station->id }})" title="Bearbeiten">
                                        @svg('heroicon-o-pencil', 'w-4 h-4')
                                    </x-nx-button>
                                    @php ($eingeplant = (int) ($station->anstehende_termine_count ?? 0))
                                    @if ($eingeplant > 0)
                                        {{-- Gesperrt statt fehlschlagend: Ein Knopf, der immer
                                             nur eine Fehlermeldung bringt, ist eine Falle. --}}
                                        <span title="In {{ $eingeplant }} {{ $eingeplant === 1 ? 'anstehenden Termin' : 'anstehenden Terminen' }} eingeplant – nicht löschbar"
                                            class="inline-flex h-8 w-8 cursor-not-allowed items-center justify-center rounded-[6px] text-[color:var(--nx-faint)]">
                                            @svg('heroicon-o-lock-closed', 'w-4 h-4')
                                        </span>
                                    @else
                                        <button type="button" wire:click="askDelete({{ $station->id }})" title="Löschen"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-[6px] text-[color:var(--nx-danger)] transition-colors hover:bg-[rgba(224,49,49,.08)]">
                                            @svg('heroicon-o-trash', 'w-4 h-4')
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-nx-card>
        @endforeach
    @endif

    {{-- Anlegen / Bearbeiten --}}
    <x-nx-modal size="sm" wire:model="showForm">
        <x-slot name="header">
            <h3 class="m-0 text-base font-semibold leading-tight text-[color:var(--nx-text)]">
                {{ $editingId ? 'Abholstation bearbeiten' : 'Neue Abholstation' }}
            </h3>
        </x-slot>

        <div class="space-y-3">
            <x-nx-input-text name="stationName" label="Name" wire:model="stationName"
                placeholder="z.B. Foyer links, Rang 1 Bar …" required errorKey="stationName" />

            <x-nx-input-text name="stationDescription" label="Hinweis für Gäste" wire:model="stationDescription"
                placeholder="neben der Garderobe …" />

            {{-- Kein Platz-Feld. Die Zahl heißt „Gäste je Pause", und das steht
                 sowohl in der Beschriftung als auch im Satz darunter – am Tisch
                 meint dieselbe Zahl Stühle, und diese Verwechslung wäre teuer. --}}
            <x-nx-input-text name="stationCapacity" label="Gäste je Pause" wire:model="stationCapacity"
                placeholder="leer = unbegrenzt" errorKey="stationCapacity" />

            <p class="m-0 text-[11px] text-[color:var(--nx-muted)]">
                Keine Sitzplätze, sondern wie viele Gäste ihr an dieser Stelle in <strong>einer</strong>
                Pause bedienen könnt. 150 in der ersten und 150 in der zweiten Pause sind zusammen 300.
                Leer lassen, wenn es keine Grenze gibt.
            </p>

            <x-nx-input-checkbox wire:model="stationActive" label="Aktiv" />
        </div>

        <x-slot name="footer">
            <x-nx-button variant="primary" wire:click="save()">Speichern</x-nx-button>
            <x-nx-button wire:click="closeForm()">Abbrechen</x-nx-button>
        </x-slot>
    </x-nx-modal>

    {{-- Löschen bestätigen --}}
    <x-nx-modal size="sm" wire:model="showDeleteConfirm">
        <x-slot name="header">
            <h3 class="m-0 text-base font-semibold leading-tight text-[color:var(--nx-text)]">Abholstation löschen</h3>
        </x-slot>

        <p class="m-0 text-sm text-[color:var(--nx-muted)]">
            „{{ $pendingName }}" wird gelöscht. Vergangene Buchungen behalten den Namen als
            Ort – sie zeigen danach nur nicht mehr auf die Station.
        </p>

        <x-slot name="footer">
            <x-nx-button variant="danger" wire:click="deleteAndCloseModal()">Löschen</x-nx-button>
            <x-nx-button wire:click="$set('showDeleteConfirm', false)">Abbrechen</x-nx-button>
        </x-slot>
    </x-nx-modal>

    </div>
    </x-ui-page-container>
</x-ui-page>
