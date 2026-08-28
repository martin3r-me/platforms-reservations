{{-- DATEV: Angaben fuer den Buchungsstapel. Sie stehen hier und nicht im Code,
     weil Kontonummern je Mandant verschieden sind und vom Kontenrahmen
     abhaengen – SKR03 bucht Erloese auf 8400/8300, SKR04 auf 4400/4300. --}}
<x-nx-card flush>
    <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
        @svg('heroicon-o-calculator', 'w-4 h-4 text-[color:var(--nx-muted)]')
        <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">DATEV-Export</h2>
        <span class="ml-auto text-[11px] text-[color:var(--nx-muted)]">für den Buchungsstapel im Export</span>
    </div>
    <div class="space-y-4 p-5">
        <p class="m-0 text-[11px] text-[color:var(--nx-muted)]">
            Die Angaben kommen aus der Kanzlei. Ohne sie bleibt das Format „DATEV" im Export gesperrt.
        </p>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-nx-input-text name="datevBerater" label="Beraternummer" wire:model="datevBerater" placeholder="z.B. 1234567" />
            <x-nx-input-text name="datevMandant" label="Mandantennummer" wire:model="datevMandant" placeholder="z.B. 12345" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-nx-input-select
                name="datevSachkontenlaenge"
                label="Sachkontenlänge"
                :options="[['value' => 4, 'label' => '4-stellig'], ['value' => 5, 'label' => '5-stellig']]"
                wire:model="datevSachkontenlaenge"
            />
            {{-- Auswahl statt Textfeld: Ein Wirtschaftsjahr beginnt am
                 Monatsersten, und damit ist die Frage nach dem Format weg.
                 Das Jahr steht bewusst nicht dabei – es ergibt sich beim
                 Export aus dem Zeitraum. --}}
            <x-nx-input-select
                name="datevWjBeginn"
                label="Beginn Wirtschaftsjahr"
                :options="collect(range(1, 12))->map(fn ($m) => [
                    'value' => str_pad((string) $m, 2, '0', STR_PAD_LEFT) . '-01',
                    'label' => '1. ' . \Illuminate\Support\Carbon::create(2000, $m, 1)->locale('de')->isoFormat('MMMM'),
                ])->all()"
                wire:model="datevWjBeginn"
            />
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <x-nx-input-text name="datevErloes7" label="Erlöskonto 7 %" wire:model="datevErloes7" placeholder="8300" />
            <x-nx-input-text name="datevErloes19" label="Erlöskonto 19 %" wire:model="datevErloes19" placeholder="8400" />
            <x-nx-input-text name="datevGeldkonto" label="Gegenkonto (Zahlungseingang)" wire:model="datevGeldkonto" placeholder="1200" />
        </div>

        <x-nx-input-select
            name="datevModus"
            label="Buchungsweise"
            :options="[
                ['value' => 'einzel', 'label' => 'Je Bestellung einzeln'],
                ['value' => 'tagessumme', 'label' => 'Tagessumme je Steuersatz'],
            ]"
            wire:model="datevModus"
        />
        <p class="m-0 text-[11px] text-[color:var(--nx-muted)]">
            An einem Veranstaltungsabend kommen schnell hunderte Bestellungen zusammen – viele Kanzleien
            wollen dann eine Summe pro Tag mit dem Veranstaltungsnamen als Buchungstext.
        </p>
    </div>
</x-nx-card>
