{{-- Shop-Sprachen (#522) --}}
<x-nx-card flush>
    <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
        @svg('heroicon-o-language', 'w-4 h-4 text-[color:var(--nx-muted)]')
        <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Shop-Sprachen</h2>
    </div>
    <div class="p-5">
        <x-nx-input-text name="languagesCsv" label="Zusätzliche Sprachen (Codes, kommagetrennt)" size="sm" wire:model="languagesCsv" placeholder="en, fr" errorKey="languagesCsv" />
        <p class="mt-1 text-[11px] text-[color:var(--nx-muted)]"><strong>Deutsch</strong> ist Basis-/Standardsprache und immer aktiv. Zusätzliche Sprachen z. B. <code>en, fr</code>. Übersetzungen der Speisen, Kategorien, Allergene und Checkout-Texte pflegst du je Objekt (auch per MCP); fehlt eine Übersetzung, wird Deutsch angezeigt.</p>

        <div class="mt-4">
            <x-nx-input-text name="guestFrontendUrl" label="Shop-Frontend-URL (für Zahlungs-Rücksprung)" size="sm" wire:model="guestFrontendUrl" placeholder="https://culinaria.pauseplus.de" errorKey="guestFrontendUrl" />
            <p class="mt-1 text-[11px] text-[color:var(--nx-muted)]">Basis-URL des externen Shops. Nach der Zahlung darf Mollie nur auf eine <code>redirect_url</code> mit <strong>diesem Origin</strong> zurückspringen (Schutz vor offenen Weiterleitungen). Ohne Eintrag wird eine vom Frontend übergebene Rücksprung-URL abgelehnt und die In-App-Seite genutzt.</p>
        </div>
    </div>
</x-nx-card>
