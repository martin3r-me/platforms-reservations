{{-- Rechnungsangaben (Aussteller) --}}
<x-nx-card flush>
    <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
        @svg('heroicon-o-building-office-2', 'w-4 h-4 text-[color:var(--nx-muted)]')
        <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Rechnungsangaben (Aussteller)</h2>
    </div>
    <div class="p-5 space-y-3">
        <p class="text-[11px] text-[color:var(--nx-muted)] m-0">Diese Firmendaten erscheinen auf Beleg und Bewirtungsbeleg (USt-IdNr/Steuernummer nach Bedarf).</p>
        <x-nx-input-text name="issuer.name" label="Firmenname" size="sm" wire:model="issuer.name" placeholder="Musterkatering GmbH" />
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="sm:col-span-2"><x-nx-input-text name="issuer.street" label="Straße & Nr." size="sm" wire:model="issuer.street" /></div>
            <x-nx-input-text name="issuer.zip" label="PLZ" size="sm" wire:model="issuer.zip" />
        </div>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="sm:col-span-2"><x-nx-input-text name="issuer.city" label="Ort" size="sm" wire:model="issuer.city" /></div>
            <x-nx-input-text name="issuer.country" label="Land" size="sm" wire:model="issuer.country" placeholder="DE" />
        </div>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <x-nx-input-text name="issuer.vat_id" label="USt-IdNr" size="sm" wire:model="issuer.vat_id" placeholder="DE123456789" />
            <x-nx-input-text name="issuer.tax_number" label="Steuernummer" size="sm" wire:model="issuer.tax_number" />
        </div>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <x-nx-input-text name="issuer.register_court" label="Registergericht" size="sm" wire:model="issuer.register_court" placeholder="Amtsgericht Wuppertal" />
            <x-nx-input-text name="issuer.register_number" label="HRB-Nr." size="sm" wire:model="issuer.register_number" placeholder="8727" />
            <x-nx-input-text name="issuer.managing_directors" label="Vertreten durch" size="sm" wire:model="issuer.managing_directors" placeholder="Max Muster & …" />
        </div>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
            <x-nx-input-text name="issuer.email" label="E-Mail" size="sm" wire:model="issuer.email" errorKey="issuer.email" />
            <x-nx-input-text name="issuer.phone" label="Telefon" size="sm" wire:model="issuer.phone" />
            <x-nx-input-text name="issuer.fax" label="Telefax" size="sm" wire:model="issuer.fax" />
            <x-nx-input-text name="issuer.website" label="Website" size="sm" wire:model="issuer.website" />
        </div>
    </div>
</x-nx-card>
