{{-- Gast-Checkout --}}
<x-nx-card flush>
    <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
        @svg('heroicon-o-document-text', 'w-4 h-4 text-[color:var(--nx-muted)]')
        <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Texte im Gast-Checkout</h2>
    </div>
    <div class="p-5 space-y-5">
        <div>
            <x-nx-input-textarea
                name="ageCheckText"
                label="18+-Hinweis (erscheint nur bei alkoholischen Artikeln)"
                wire:model="ageCheckText"
                rows="3"
                :placeholder="$defaultAge"
            />
            <p class="mt-1 text-[11px] text-[color:var(--nx-muted)]">Leer lassen = Standardtext wird verwendet.</p>
        </div>

        <div>
            <x-nx-input-textarea
                name="legalText"
                label="Pflicht-Bestätigung (Checkbox vor dem Bezahlen)"
                wire:model="legalText"
                rows="3"
                :placeholder="$defaultLegal"
            />
            <p class="mt-1 text-[11px] text-[color:var(--nx-muted)]">Leer lassen = Standardtext wird verwendet.</p>
        </div>

        <x-nx-input-text
            type="url"
            name="privacyUrl"
            label="Link zur Datenschutzerklärung (optional)"
            wire:model="privacyUrl"
            placeholder="https://…"
        />
    </div>
</x-nx-card>
