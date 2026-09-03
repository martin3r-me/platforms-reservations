{{-- Zeitraum-Leiste: Schnellwahl links, Von/Bis rechts.

     Eine Fassung für Artikel-Auswertung, Bestellwege, Finanzen und Export. Sie
     sah auf jeder Seite anders aus – im Export in einem Kasten mit
     untereinanderstehenden Datumsfeldern, sonst als eine Zeile –, und die
     Zeiträume selbst waren auch noch verschieden. Wer zwischen den Seiten
     wechselt, soll dieselbe Leiste vorfinden und nicht suchen.

     @param array  $presets  key => Beschriftung (aus der jeweiligen Komponente)
     @param string $aktiv    der gewählte Schlüssel --}}
@props(['presets' => [], 'aktiv' => ''])

<div class="flex flex-wrap items-end justify-between gap-3">
    <div class="flex flex-wrap items-center gap-1 rounded-[8px] border border-[color:var(--nx-line)] bg-[color:var(--nx-bg)] p-1">
        @foreach ($presets as $preset => $label)
            <button type="button" wire:click="setPreset('{{ $preset }}')"
                class="inline-flex h-7 items-center rounded-[6px] px-3 text-xs font-medium transition-colors
                    {{ $aktiv === $preset ? 'bg-[color:var(--nx-surface)] font-semibold text-[color:var(--nx-text)]' : 'bg-transparent text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Rechts und in einer Zeile mit der Schnellwahl: Die Felder gehören zu
         ihr – ein Klick links füllt sie. Untereinander gestellt sahen sie im
         Export nach einem eigenen Filter aus. --}}
    <div class="flex items-end gap-2">
        <div class="w-40">
            <x-nx-input-date name="dateFrom" label="Von" size="sm" wire:model.live="dateFrom" />
        </div>
        <div class="w-40">
            <x-nx-input-date name="dateTo" label="Bis" size="sm" wire:model.live="dateTo" />
        </div>
    </div>
</div>
