<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Venues & Tischpläne" icon="heroicon-o-building-storefront" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Venues & Tischpläne'],
        ]">
            <x-nx-button variant="primary" wire:click="openVenueForm()">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Venue anlegen</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
    <div class="space-y-5">

    {{-- Leer-Zustand --}}
    @if ($this->venues->isEmpty())
        <x-nx-card>
            <x-nx-empty icon="heroicon-o-building-storefront">
                <span class="text-sm font-medium text-[color:var(--nx-text)]">Noch kein Venue vorhanden</span>
                <span class="mt-1 block">Lege dein erstes Venue an, um Tischpläne zu erstellen.</span>
                <x-slot name="action">
                    <x-nx-button variant="primary" wire:click="openVenueForm()">
                        @svg('heroicon-o-plus', 'w-4 h-4')
                        <span>Venue erstellen</span>
                    </x-nx-button>
                </x-slot>
            </x-nx-empty>
        </x-nx-card>
    @else
        @foreach ($this->venues as $venue)
            <x-nx-card flush wire:key="venue-{{ $venue->id }}">
                {{-- Venue-Kopfzeile --}}
                <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
                    @svg('heroicon-o-building-storefront', 'w-4 h-4 text-[color:var(--nx-muted)]')
                    <div class="min-w-0">
                        <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">{{ $venue->name }}</h2>
                        @if ($venue->address)
                            <p class="m-0 text-[11px] text-[color:var(--nx-muted)]">{{ $venue->address }}</p>
                        @endif
                    </div>
                    <div class="ml-auto flex shrink-0 items-center justify-end gap-1">
                        <x-nx-button variant="primary" wire:click="openFloorPlanForm({{ $venue->id }})">
                            @svg('heroicon-o-plus', 'w-4 h-4')
                            <span>Tischplan</span>
                        </x-nx-button>
                        <x-nx-button icon variant="ghost" wire:click="openVenueForm({{ $venue->id }})" title="Venue bearbeiten">
                            @svg('heroicon-o-pencil', 'w-4 h-4')
                        </x-nx-button>
                        <button type="button" wire:click="deleteVenue({{ $venue->id }})" wire:confirm="Venue wirklich löschen?" title="Löschen"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-[6px] text-[color:var(--nx-danger)] transition-colors hover:bg-[rgba(224,49,49,.08)]">
                            @svg('heroicon-o-trash', 'w-4 h-4')
                        </button>
                    </div>
                </div>

                {{-- Tischplan-Liste --}}
                @if ($venue->floorPlans->isEmpty())
                    <div class="px-4 py-4 text-sm text-[color:var(--nx-muted)]">
                        Noch kein Tischplan vorhanden. Klicke auf <em>+ Tischplan</em>.
                    </div>
                @else
                    <div>
                        @foreach ($venue->floorPlans as $plan)
                            <div wire:key="plan-{{ $plan->id }}" class="group flex items-center justify-between gap-3 border-b border-[color:var(--nx-line)] px-4 py-2.5 transition-colors last:border-0 hover:bg-[color:var(--nx-hover)]">
                                <div class="min-w-0">
                                    <span class="text-sm font-medium text-[color:var(--nx-text)]">{{ $plan->name }}</span>
                                    <span class="ml-2 text-xs text-[color:var(--nx-faint)]">
                                        {{ $plan->tables_count }} {{ $plan->tables_count === 1 ? 'Tisch' : 'Tische' }}
                                    </span>
                                </div>
                                <div class="flex shrink-0 items-center justify-end gap-1">
                                    <x-nx-button :href="route('reservation.floor-plan.editor', ['venueId' => $venue->id, 'floorPlanId' => $plan->id])">
                                        @svg('heroicon-o-pencil-square', 'w-4 h-4')
                                        <span>Editor</span>
                                    </x-nx-button>
                                    <div class="flex items-center gap-0.5 opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100">
                                        <x-nx-button icon variant="ghost" wire:click="openFloorPlanForm({{ $venue->id }}, {{ $plan->id }})" title="Umbenennen">
                                            @svg('heroicon-o-pencil', 'w-4 h-4')
                                        </x-nx-button>
                                        <button type="button" wire:click="deleteFloorPlan({{ $plan->id }})" wire:confirm="Tischplan wirklich löschen?" title="Löschen"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-[6px] text-[color:var(--nx-danger)] transition-colors hover:bg-[rgba(224,49,49,.08)]">
                                            @svg('heroicon-o-trash', 'w-4 h-4')
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-nx-card>
        @endforeach
    @endif

    {{-- Modal: Venue erstellen / bearbeiten --}}
    <x-nx-modal size="sm" wire:model="showVenueForm">
        <x-slot name="header">
            <h3 class="m-0 text-base font-semibold leading-tight text-[color:var(--nx-text)]">
                {{ $editingVenueId ? 'Venue bearbeiten' : 'Neues Venue' }}
            </h3>
        </x-slot>

        <div class="space-y-3">
            <x-nx-input-text name="venueName" label="Name" wire:model="venueName" placeholder="z.B. Foyer, Hauptsaal, Galerie …" required errorKey="venueName" />
            <x-nx-input-text name="venueAddress" label="Adresse / Beschreibung" wire:model="venueAddress" placeholder="Haus 1, EG, links …" />
        </div>

        <x-slot name="footer">
            <x-nx-button wire:click="$set('showVenueForm', false)">Abbrechen</x-nx-button>
            <x-nx-button variant="primary" wire:click="saveVenue">Speichern</x-nx-button>
        </x-slot>
    </x-nx-modal>

    {{-- Modal: Tischplan erstellen / umbenennen --}}
    <x-nx-modal size="sm" wire:model="showFloorPlanForm">
        <x-slot name="header">
            <h3 class="m-0 text-base font-semibold leading-tight text-[color:var(--nx-text)]">
                {{ $editingFloorPlanId ? 'Tischplan umbenennen' : 'Neuer Tischplan' }}
            </h3>
        </x-slot>

        <x-nx-input-text name="floorPlanName" label="Name" wire:model="floorPlanName" placeholder="z.B. Erdgeschoss, Terrasse …" required errorKey="floorPlanName" />

        <x-slot name="footer">
            <x-nx-button wire:click="$set('showFloorPlanForm', false)">Abbrechen</x-nx-button>
            <x-nx-button variant="primary" wire:click="saveFloorPlan">Speichern</x-nx-button>
        </x-slot>
    </x-nx-modal>

    </div>
    </x-ui-page-container>
</x-ui-page>
