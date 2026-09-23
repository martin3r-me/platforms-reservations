{{-- Kennzahlen-Zeile unter den Reitern eines Termins.

     Eine Fassung fuer alle Reiter. Vorher hatte jeder Reiter seine eigene:
     "Buchungen" ein Raster ueber die volle Breite, "Kueche" eine schmalere
     Zeile links. Es waren dieselben Zahlen in zwei Gestalten - beim Wechsel
     des Reiters sprang der Kopf, und man sah zweimal hin, um zu merken, dass
     sich nichts geaendert hat.

     Wie viele Kacheln kommen, entscheidet der Reiter: "Buchungen" hat den
     Umsatz, "Kueche" nicht. Verschieden VIEL ist in Ordnung, verschieden
     AUSSEHEND nicht.

     $tiles : [[Label, Wert], ...]
     $note  : optionaler Zusatz darunter rechts, z.B. "ohne Stornos / No-Shows"
--}}
@php $note = $note ?? null; @endphp

<div class="border-y border-[color:var(--nx-line)] py-4">
    <div class="grid grid-cols-2 gap-x-4 gap-y-4 sm:grid-cols-4">
        @foreach ($tiles as [$label, $value])
            <div wire:key="stat-{{ $loop->index }}">
                <div class="text-2xl font-bold leading-none tabular-nums text-[color:var(--nx-text)]">{{ $value }}</div>
                <div class="mt-1.5 text-xs text-[color:var(--nx-muted)]">{{ $label }}</div>
            </div>
        @endforeach
    </div>

    @if ($note)
        <div class="mt-3 text-right text-xs text-[color:var(--nx-faint)]">{{ $note }}</div>
    @endif
</div>
