{{-- Bestellbestätigung (E-Mail-Absender) --}}
<x-nx-card flush>
    <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
        @svg('heroicon-o-envelope', 'w-4 h-4 text-[color:var(--nx-muted)]')
        <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Bestellbestätigung (E-Mail)</h2>
    </div>
    <div class="p-5">
        @if (count($emailChannels))
            <x-nx-input-select
                name="confirmationChannelId"
                label="Absender für Bestellbestätigungen"
                :options="$emailChannels"
                :nullable="true"
                nullLabel="— kein Versand —"
                wire:model="confirmationChannelId"
            />
            <p class="mt-1 text-[11px] text-[color:var(--nx-muted)]">Wähle den Postmark-Absender (aus dem CRM), über den die „Vielen Dank für Ihre Bestellung"-Mail verschickt wird. <strong>Ohne Auswahl wird keine Bestätigung versendet</strong> (kein Standard-Absender).</p>
        @else
            <p class="text-[11px] text-[color:var(--nx-muted)] m-0">Es sind keine aktiven Postmark-E-Mail-Absender im CRM vorhanden. Lege zuerst im CRM einen E-Mail-Channel (Provider Postmark) an – dann kannst du ihn hier auswählen.</p>
        @endif
    </div>
</x-nx-card>
