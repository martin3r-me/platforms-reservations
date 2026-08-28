{{-- Bon-Druck: neue Auftraege automatisch drucken. Der Druckknopf je Zeile
     in der Buchungsliste bleibt davon unberuehrt. --}}
@if ($this->printingAvailable)
    <x-nx-card flush>
        <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
            @svg('heroicon-o-printer', 'w-4 h-4 text-[color:var(--nx-muted)]')
            <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Bon-Druck</h2>
        </div>
        <div class="p-5">
            <label class="flex items-start gap-2 text-sm text-[color:var(--nx-text)] cursor-pointer">
                <input wire:model.live="autoPrintEnabled" type="checkbox" class="mt-0.5 rounded-[4px] accent-[var(--nx-accent)]" />
                <span>
                    Neue Aufträge automatisch drucken
                    <span class="block text-[11px] text-[color:var(--nx-muted)]">Sobald eine Buchung bestätigt ist – nach der Zahlung ebenso wie bei manueller Bestätigung –, wird der Bon gedruckt. Einzelne Aufträge lassen sich weiterhin über das Druckersymbol in der Buchungsliste drucken.</span>
                </span>
            </label>

            @if ($autoPrintEnabled)
                <div class="mt-4 ml-6">
                    <div class="mb-2 text-[11px] uppercase tracking-wide text-[color:var(--nx-muted)]">Ziel</div>
                    <div class="flex flex-wrap gap-4">
                        <label class="flex items-center gap-2 text-sm text-[color:var(--nx-text)] cursor-pointer">
                            <input wire:model.live="autoPrintTarget" type="radio" value="printer" class="accent-[var(--nx-accent)]" />
                            <span>Einzelner Drucker</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm text-[color:var(--nx-text)] cursor-pointer">
                            <input wire:model.live="autoPrintTarget" type="radio" value="group" class="accent-[var(--nx-accent)]" />
                            <span>Druckergruppe</span>
                        </label>
                    </div>

                    <div class="mt-3 max-w-sm">
                        @if ($autoPrintTarget === 'printer')
                            <x-nx-input-select
                                name="autoPrintPrinterId"
                                label="Drucker"
                                size="sm"
                                :options="$this->printers"
                                optionValue="id"
                                optionLabel="name"
                                :nullable="true"
                                nullLabel="— bitte wählen —"
                                wire:model.live="autoPrintPrinterId"
                                errorKey="autoPrintPrinterId"
                            />
                            @if ($this->printers->isEmpty())
                                <p class="mt-1 text-[11px] text-[color:var(--nx-muted)]">Noch kein Drucker eingerichtet.</p>
                            @endif
                        @else
                            <x-nx-input-select
                                name="autoPrintPrinterGroupId"
                                label="Druckergruppe"
                                size="sm"
                                :options="$this->printerGroups"
                                optionValue="id"
                                optionLabel="name"
                                :nullable="true"
                                nullLabel="— bitte wählen —"
                                wire:model.live="autoPrintPrinterGroupId"
                                errorKey="autoPrintPrinterGroupId"
                            />
                            @if ($this->printerGroups->isEmpty())
                                <p class="mt-1 text-[11px] text-[color:var(--nx-muted)]">Noch keine Druckergruppe eingerichtet.</p>
                            @endif
                        @endif
                    </div>

                    {{-- Ohne Ziel ginge der Druck ins Leere – lieber vorher sagen.
                         Geprueft wird nur das gerade gewaehlte Ziel: Beim Umschalten
                         wird das andere ohnehin verworfen. --}}
                    @if (($autoPrintTarget === 'printer' && ! $autoPrintPrinterId)
                        || ($autoPrintTarget === 'group' && ! $autoPrintPrinterGroupId))
                        <div class="mt-3">
                            <x-nx-callout variant="warning">
                                Ohne ausgewähltes Ziel wird nichts gedruckt.
                            </x-nx-callout>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </x-nx-card>
@endif
