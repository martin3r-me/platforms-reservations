{{-- Anmeldefelder (Gast-Checkout) --}}
<x-nx-card flush>
    <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
        @svg('heroicon-o-identification', 'w-4 h-4 text-[color:var(--nx-muted)]')
        <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Anmeldefelder im Gast-Checkout</h2>
    </div>
    <div class="p-5 space-y-4">
        <p class="text-[11px] text-[color:var(--nx-muted)] m-0">Steuert je Feld, ob es im Gast-Checkout abgefragt wird. <strong>Name</strong> und <strong>Personenzahl</strong> sind immer Pflicht.</p>
        @php
            $fieldModeOptions = [
                ['value' => 'required', 'label' => 'Pflicht'],
                ['value' => 'optional', 'label' => 'Optional'],
                ['value' => 'hidden',   'label' => 'Ausgeblendet'],
            ];
        @endphp
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-nx-input-select name="fieldEmail" label="E-Mail" :options="$fieldModeOptions" wire:model="fieldEmail" />
            <x-nx-input-select name="fieldPhone" label="Rufnummer" :options="$fieldModeOptions" wire:model="fieldPhone" />
            <x-nx-input-select name="fieldNotes" label="Anmerkungen" :options="$fieldModeOptions" wire:model="fieldNotes" />
        </div>
        <p class="text-[11px] text-[color:var(--nx-muted)] m-0">Hinweis: Wird die E-Mail ausgeblendet oder optional gesetzt, kann für diese Bestellung keine automatische Bestätigungs-E-Mail versendet werden.</p>
    </div>
</x-nx-card>
