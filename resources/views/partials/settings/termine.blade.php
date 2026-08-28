{{-- Termine --}}
<x-nx-card flush>
    <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
        @svg('heroicon-o-ticket', 'w-4 h-4 text-[color:var(--nx-muted)]')
        <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Termine</h2>
    </div>
    <div class="p-5">
        <x-nx-input-select
            name="defaultRoomReleaseMode"
            label="Standard-Raumfreigabe (Vorauswahl bei neuen Terminen)"
            :options="[
                ['value' => 'parallel', 'label' => 'Parallel (alle Räume offen)'],
                ['value' => 'sequential', 'label' => 'Sequentiell (Raum 2 nach Füllung von Raum 1)'],
            ]"
            wire:model="defaultRoomReleaseMode"
        />
        <p class="mt-1 text-[11px] text-[color:var(--nx-muted)]">Beim Anlegen eines Termins kann die Freigabe weiterhin einzeln geändert werden.</p>

        <label class="mt-4 flex items-start gap-2 text-sm text-[color:var(--nx-text)] cursor-pointer">
            <input wire:model.live="softTableCapacity" type="checkbox" class="mt-0.5 rounded-[4px] accent-[var(--nx-accent)]" />
            <span>
                Weiche Tisch-Kapazität (Großgruppen)
                <span class="block text-[11px] text-[color:var(--nx-muted)]">Eine Gruppe, die nicht in die freien Plätze passt, darf einen <strong>komplett leeren</strong> Tisch über die Platzzahl hinaus belegen (z. B. Stehtische). Teilbelegte Tische bleiben für zu große Gruppen gesperrt.</span>
            </span>
        </label>

        @if ($softTableCapacity)
            <div class="mt-3 ml-6 max-w-xs">
                <x-nx-input-text type="number" name="maxGroupEmptyTable" label="Max. Gruppe auf leerem Tisch (leer = unbegrenzt)" size="sm" wire:model="maxGroupEmptyTable" placeholder="z. B. 12" errorKey="maxGroupEmptyTable" />
                <p class="mt-1 text-[11px] text-[color:var(--nx-muted)]">Deckelt, wie viele Personen einen leeren Tisch über die Platzzahl hinaus belegen dürfen.</p>
            </div>
        @endif
    </div>
</x-nx-card>
