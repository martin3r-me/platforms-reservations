{{-- Bon drucken: Drucker/Gruppe wählen.
     Gehört zu Concerns\PrintsBookingReceipt – wer den Trait einbindet, bindet
     auch dieses Partial ein. --}}
<x-nx-modal size="sm" wire:model="printModalShow">
    <x-slot name="header">
        <h3 class="m-0 text-base font-semibold leading-tight text-[color:var(--nx-text)]">Bon drucken</h3>
        <p class="m-0 mt-1 text-xs text-[color:var(--nx-muted)]">Buchung als Beleg an einen Drucker senden</p>
    </x-slot>

    <div class="space-y-4">
        {{-- Ziel: Einzeldrucker oder Gruppe --}}
        <div class="inline-flex overflow-hidden rounded-[8px] border border-[color:var(--nx-line-strong)]">
            <button type="button" wire:click="$set('printTarget', 'printer')" class="px-3 py-1.5 text-sm transition-colors {{ $printTarget === 'printer' ? 'bg-[color:var(--nx-accent)] text-[color:var(--nx-on-accent)]' : 'text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)]' }}">Drucker</button>
            <button type="button" wire:click="$set('printTarget', 'group')" class="px-3 py-1.5 text-sm transition-colors {{ $printTarget === 'group' ? 'bg-[color:var(--nx-accent)] text-[color:var(--nx-on-accent)]' : 'text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)]' }}">Gruppe</button>
        </div>

        @if ($printTarget === 'printer')
            @if ($this->printers->isEmpty())
                <p class="m-0 text-sm text-[color:var(--nx-muted)]">Kein Drucker verfügbar.</p>
            @else
                <x-ui-input-select
                    name="selectedPrinterId"
                    label="Drucker"
                    :options="$this->printers->map(fn ($p) => ['value' => $p->id, 'label' => $p->name])->values()->all()"
                    :nullable="true"
                    nullLabel="– wählen –"
                    wire:model="selectedPrinterId"
                />
            @endif
        @else
            @if ($this->printerGroups->isEmpty())
                <p class="m-0 text-sm text-[color:var(--nx-muted)]">Keine Drucker-Gruppe verfügbar.</p>
            @else
                <x-ui-input-select
                    name="selectedPrinterGroupId"
                    label="Drucker-Gruppe"
                    :options="$this->printerGroups->map(fn ($g) => ['value' => $g->id, 'label' => $g->name])->values()->all()"
                    :nullable="true"
                    nullLabel="– wählen –"
                    wire:model="selectedPrinterGroupId"
                />
            @endif
        @endif
    </div>

    <x-slot name="footer">
        <x-nx-button wire:click="closePrintModal">Abbrechen</x-nx-button>
        <x-nx-button variant="primary" wire:click="printBookingConfirm">
            @svg('heroicon-o-printer', 'w-4 h-4')
            <span>Drucken</span>
        </x-nx-button>
    </x-slot>
</x-nx-modal>
