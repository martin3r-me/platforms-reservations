{{-- Selbst-Storno --}}
<x-nx-card flush>
    <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
        @svg('heroicon-o-arrow-uturn-left', 'w-4 h-4 text-[color:var(--nx-muted)]')
        <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Stornierung durch Kunden</h2>
    </div>
    <div class="p-5 space-y-4">
        <label class="flex items-start gap-2 text-sm text-[color:var(--nx-text)] cursor-pointer">
            <input wire:model.live="cancellationEnabled" type="checkbox" class="mt-0.5 rounded-[4px] accent-[var(--nx-accent)]" />
            <span>
                Selbst-Storno erlauben
                <span class="block text-[11px] text-[color:var(--nx-muted)]">Kunden erhalten in der Bestätigungs-Mail einen Storno-Link. Innerhalb der Frist wird die Bestellung storniert und die Zahlung über Mollie erstattet.</span>
            </span>
        </label>

        @if ($cancellationEnabled)
            <div class="ml-6 max-w-xs">
                <x-nx-input-text type="number" name="cancellationDeadlineHours" label="Frist: Stunden vor Veranstaltung" size="sm" wire:model="cancellationDeadlineHours" placeholder="z. B. 72" errorKey="cancellationDeadlineHours" />
                <p class="mt-1 text-[11px] text-[color:var(--nx-muted)]">Bis wie viele Stunden vor dem Veranstaltungsdatum ein Storno möglich ist. Leer = keine Frist.</p>
            </div>
            <label class="ml-6 flex items-start gap-2 text-sm text-[color:var(--nx-text)] cursor-pointer">
                <input wire:model="cancellationRequiresApproval" type="checkbox" class="mt-0.5 rounded-[4px] accent-[var(--nx-accent)]" />
                <span>
                    Storno erst nach Freigabe
                    <span class="block text-[11px] text-[color:var(--nx-muted)]">Standard: aus – der Klick storniert sofort und löst die Rückerstattung aus. Aktiv: der Kunde fragt nur an, das Team gibt frei (dann erst Rückerstattung).</span>
                </span>
            </label>
        @endif
    </div>
</x-nx-card>
