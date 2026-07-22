<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Drop-off Slots" icon="heroicon-o-clock" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Drop-off'],
        ]">
            <x-nx-button variant="primary" wire:click="openForm()">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neuer Slot</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
    <div class="pt-4 space-y-4">

    {{-- Datumsfilter --}}
    <div class="w-44">
        <x-ui-input-date name="filterDate" size="sm" wire:model.live="filterDate" />
    </div>

    {{-- Slot-Liste --}}
    <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
        <x-ui-table compact="true">
            <x-ui-table-header>
                <x-ui-table-header-cell compact="true">Datum</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Von</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Bis</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true" align="center">Kapazität</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true" align="center">Gebucht</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true" align="center">Frei</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Aktionen</x-ui-table-header-cell>
            </x-ui-table-header>
            <x-ui-table-body>
                @forelse ($this->slots as $slot)
                    <x-ui-table-row compact="true" wire:key="slot-{{ $slot->id }}">
                        <x-ui-table-cell compact="true">{{ $slot->date->format('d.m.Y') }}</x-ui-table-cell>
                        <x-ui-table-cell compact="true">{{ $slot->time_from }}</x-ui-table-cell>
                        <x-ui-table-cell compact="true">{{ $slot->time_to }}</x-ui-table-cell>
                        <x-ui-table-cell compact="true" align="center">{{ $slot->capacity }}</x-ui-table-cell>
                        <x-ui-table-cell compact="true" align="center">{{ $slot->booked_count }}</x-ui-table-cell>
                        <x-ui-table-cell compact="true" align="center">
                            <span class="font-semibold tabular-nums {{ $slot->remaining_capacity > 0 ? 'text-[var(--ui-success)]' : 'text-[var(--ui-danger)]' }}">
                                {{ $slot->remaining_capacity }}
                            </span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <div class="flex gap-1.5">
                                <x-ui-button variant="secondary-outline" size="sm" :iconOnly="true" wire:click="openForm({{ $slot->id }})" title="Bearbeiten">
                                    @svg('heroicon-o-pencil', 'w-4 h-4')
                                </x-ui-button>
                                <div class="shrink-0">
                                    <x-ui-confirm-button
                                        action="delete"
                                        :value="$slot->id"
                                        text=""
                                        confirmText="Wirklich löschen?"
                                        variant="danger-outline"
                                        size="sm"
                                        :icon="svg('heroicon-o-trash', 'w-4 h-4')->toHtml()"
                                    />
                                </div>
                            </div>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @empty
                    <tr>
                        <td colspan="7">
                            <div class="flex flex-col items-center justify-center py-8 text-[var(--ui-muted)]">
                                @svg('heroicon-o-inbox', 'w-8 h-8 mb-2 opacity-40')
                                <span class="text-xs">Keine Slots gefunden</span>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </x-ui-table-body>
        </x-ui-table>
    </section>

    {{-- Formular-Modal --}}
    <x-ui-modal size="sm" wire:model="showForm">
        <x-slot name="header">
            <div class="flex items-center gap-3">
                <div class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-[var(--ui-primary-10)] flex-shrink-0">
                    @svg('heroicon-o-clock', 'w-5 h-5 text-[var(--ui-primary)]')
                </div>
                <h3 class="text-base font-semibold text-[var(--ui-secondary)] m-0 leading-tight">
                    {{ $editingId ? 'Slot bearbeiten' : 'Neuer Drop-off Slot' }}
                </h3>
            </div>
        </x-slot>

        <div class="space-y-3">
            <x-ui-input-date name="slotDate" label="Datum" wire:model="slotDate" required errorKey="slotDate" />
            <x-ui-form-grid :cols="2" :gap="3">
                <x-ui-input-text type="time" name="slotTimeFrom" label="Von" wire:model="slotTimeFrom" required errorKey="slotTimeFrom" />
                <x-ui-input-text type="time" name="slotTimeTo" label="Bis" wire:model="slotTimeTo" required errorKey="slotTimeTo" />
            </x-ui-form-grid>
            <x-ui-input-number name="slotCapacity" label="Kapazität" min="1" wire:model="slotCapacity" />
            <x-ui-input-textarea name="slotNotes" label="Notizen" wire:model="slotNotes" rows="2" />
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
