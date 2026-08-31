{{-- Blätterung – deutsch und im Ton des Moduls.

     Die mitgelieferte Livewire-Ansicht bringt englischen Text mit („Showing 1 to
     25 of 42 results") und Tailwind-Grautöne, die neben den nx-Farben fremd
     wirken. Sie doppelt außerdem den Zähler, den die Listen links ohnehin
     zeigen.

     Deshalb hier nur die Navigation: Die Liste sagt, WAS gezählt wird
     („42 Vorgänge", „42 Buchungen"), diese Ansicht bringt einen nur woanders
     hin. Eingebunden über ->links('reservation::partials.pagination').

     Erwartet die üblichen Variablen der Livewire-Paginierung ($paginator,
     $elements). --}}

@php
    $knopf = 'inline-flex h-7 min-w-7 items-center justify-center rounded-md border border-[color:var(--nx-line)] px-2 text-xs tabular-nums transition-colors';
    $ruhend = $knopf . ' text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)]';
    $aktiv = $knopf . ' border-transparent bg-[color:var(--nx-active)] font-semibold text-[color:var(--nx-text)]';
    $aus = $knopf . ' cursor-not-allowed text-[color:var(--nx-faint)] opacity-60';
    $hoch = 'window.scrollTo({ top: 0, behavior: \'smooth\' })';
@endphp

@if ($paginator->hasPages())
    <nav class="flex items-center gap-1" role="navigation" aria-label="Seiten">
        @if ($paginator->onFirstPage())
            <span class="{{ $aus }}" aria-disabled="true">Zurück</span>
        @else
            <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $hoch }}" wire:loading.attr="disabled" class="{{ $ruhend }}">Zurück</button>
        @endif

        {{-- Seitenzahlen. „…" steht für ausgelassene Seiten und ist kein Knopf. --}}
        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="px-1 text-xs text-[color:var(--nx-faint)]">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="{{ $aktiv }}" aria-current="page">{{ $page }}</span>
                    @else
                        <button type="button" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" x-on:click="{{ $hoch }}" wire:loading.attr="disabled" class="{{ $ruhend }}" aria-label="Seite {{ $page }}">{{ $page }}</button>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $hoch }}" wire:loading.attr="disabled" class="{{ $ruhend }}">Weiter</button>
        @else
            <span class="{{ $aus }}" aria-disabled="true">Weiter</span>
        @endif
    </nav>
@endif
