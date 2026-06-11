<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Venues & Tischpläne" icon="heroicon-o-building-storefront" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Venues & Tischpläne'],
        ]">
            <x-ui-button variant="primary" size="sm" wire:click="openVenueForm()">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Venue anlegen</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
    <div class="pt-4 space-y-4">

    {{-- Leer-Zustand --}}
    @if ($this->venues->isEmpty())
        <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm">
            <div class="flex flex-col items-center justify-center py-16 text-[var(--ui-muted)]">
                @svg('heroicon-o-building-storefront', 'w-10 h-10 mb-3 opacity-40')
                <span class="text-sm font-medium text-[var(--ui-secondary)]">Noch kein Venue vorhanden</span>
                <span class="text-xs mt-1 opacity-70">Lege dein erstes Venue an, um Tischpläne zu erstellen.</span>
                <div class="mt-4">
                    <x-ui-button variant="primary" size="sm" wire:click="openVenueForm()">
                        @svg('heroicon-o-plus', 'w-4 h-4')
                        <span>Venue erstellen</span>
                    </x-ui-button>
                </div>
            </div>
        </section>
    @else
        @foreach ($this->venues as $venue)
            <section wire:key="venue-{{ $venue->id }}" class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
                {{-- Venue-Kopfzeile --}}
                <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
                    @svg('heroicon-o-building-storefront', 'w-4 h-4 text-[var(--ui-muted)]')
                    <div class="min-w-0">
                        <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">{{ $venue->name }}</h2>
                        @if ($venue->address)
                            <p class="text-[11px] text-[var(--ui-muted)] m-0 normal-case tracking-normal">{{ $venue->address }}</p>
                        @endif
                    </div>
                    <div class="ml-auto flex shrink-0 items-center gap-1.5">
                        <x-ui-button variant="primary" size="sm" wire:click="openFloorPlanForm({{ $venue->id }})">+ Tischplan</x-ui-button>
                        <x-ui-button variant="secondary-ghost" size="sm" wire:click="openVenueForm({{ $venue->id }})">Bearbeiten</x-ui-button>
                        <x-ui-confirm-button
                            action="deleteVenue"
                            :value="$venue->id"
                            text="Löschen"
                            confirmText="Venue und alle Tischpläne wirklich löschen?"
                            variant="danger"
                            size="sm"
                        />
                    </div>
                </div>

                {{-- Tischplan-Liste --}}
                @if ($venue->floorPlans->isEmpty())
                    <div class="px-4 py-4 text-sm text-[var(--ui-muted)]">
                        Noch kein Tischplan vorhanden. Klicke auf <em>+ Tischplan</em>.
                    </div>
                @else
                    <div class="divide-y divide-[var(--ui-border)]/30">
                        @foreach ($venue->floorPlans as $plan)
                            <div wire:key="plan-{{ $plan->id }}" class="flex items-center justify-between gap-3 px-4 py-2.5 hover:bg-[var(--ui-muted-5)] transition-colors">
                                <div class="min-w-0">
                                    <span class="text-sm font-medium text-[var(--ui-secondary)]">{{ $plan->name }}</span>
                                    <span class="ml-2 text-xs text-[var(--ui-muted)]">
                                        {{ $plan->tables_count }} {{ $plan->tables_count === 1 ? 'Tisch' : 'Tische' }}
                                    </span>
                                </div>
                                <div class="flex shrink-0 flex-wrap items-center gap-1.5">
                                    <x-ui-button variant="secondary-outline" size="sm"
                                        :href="route('reservation.floor-plan.editor', ['venueId' => $venue->id, 'floorPlanId' => $plan->id])">
                                        @svg('heroicon-o-pencil-square', 'w-4 h-4')
                                        <span>Editor</span>
                                    </x-ui-button>
                                    <x-ui-button variant="success" size="sm"
                                        :href="route('reservation.floor-plan.viewer', ['floorPlanId' => $plan->id])" target="_blank">
                                        @svg('heroicon-o-eye', 'w-4 h-4')
                                        <span>3D-Ansicht</span>
                                    </x-ui-button>
                                    <x-ui-button variant="secondary-ghost" size="sm" wire:click="openFloorPlanForm({{ $venue->id }}, {{ $plan->id }})">Umbenennen</x-ui-button>
                                    <x-ui-confirm-button
                                        action="deleteFloorPlan"
                                        :value="$plan->id"
                                        text="Löschen"
                                        confirmText="Tischplan wirklich löschen?"
                                        variant="danger"
                                        size="sm"
                                    />
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        @endforeach
    @endif

    {{-- Modal: Venue erstellen / bearbeiten --}}
    <x-ui-modal size="sm" wire:model="showVenueForm">
        <x-slot name="header">
            <div class="flex items-center gap-3">
                <div class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-[var(--ui-primary-10)] flex-shrink-0">
                    @svg('heroicon-o-building-storefront', 'w-5 h-5 text-[var(--ui-primary)]')
                </div>
                <h3 class="text-base font-semibold text-[var(--ui-secondary)] m-0 leading-tight">
                    {{ $editingVenueId ? 'Venue bearbeiten' : 'Neues Venue' }}
                </h3>
            </div>
        </x-slot>

        <div class="space-y-3">
            <x-ui-input-text name="venueName" label="Name" wire:model="venueName" placeholder="z.B. Foyer, Hauptsaal, Galerie …" required errorKey="venueName" />
            <x-ui-input-text name="venueAddress" label="Adresse / Beschreibung" wire:model="venueAddress" placeholder="Haus 1, EG, links …" />
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-ui-button variant="secondary-outline" size="sm" wire:click="$set('showVenueForm', false)">Abbrechen</x-ui-button>
                <x-ui-button variant="primary" size="sm" wire:click="saveVenue">Speichern</x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>

    {{-- Modal: Tischplan erstellen / umbenennen --}}
    <x-ui-modal size="sm" wire:model="showFloorPlanForm">
        <x-slot name="header">
            <div class="flex items-center gap-3">
                <div class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-[var(--ui-primary-10)] flex-shrink-0">
                    @svg('heroicon-o-map', 'w-5 h-5 text-[var(--ui-primary)]')
                </div>
                <h3 class="text-base font-semibold text-[var(--ui-secondary)] m-0 leading-tight">
                    {{ $editingFloorPlanId ? 'Tischplan umbenennen' : 'Neuer Tischplan' }}
                </h3>
            </div>
        </x-slot>

        <x-ui-input-text name="floorPlanName" label="Name" wire:model="floorPlanName" placeholder="z.B. Erdgeschoss, Terrasse …" required errorKey="floorPlanName" />

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-ui-button variant="secondary-outline" size="sm" wire:click="$set('showFloorPlanForm', false)">Abbrechen</x-ui-button>
                <x-ui-button variant="primary" size="sm" wire:click="saveFloorPlan">Speichern</x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>

    </div>
    </x-ui-page-container>
</x-ui-page>
