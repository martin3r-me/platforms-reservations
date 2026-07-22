{{--
    Moderne Mehrfachauswahl (Chips + Such-Dropdown) für Livewire.

    Erwartet:
    - $options     Collection mit ->id, ->code, ->name
    - $selected    array<int> ausgewählte IDs
    - $toggle      (string) Livewire-Methode zum Umschalten, z. B. 'toggleAllergen'
    - $accent      'warning' | 'info' (Chip-Farbe)
    - $placeholder (string)
    - $key         (string) eindeutiger Schlüssel
--}}
@php
    $selectedModels = $options->whereIn('id', $selected);
    // Accent (warning|info) auf nx-Töne mappen
    $chipBg = $accent === 'info' ? 'rgba(25,113,194,.12)' : 'rgba(232,89,12,.12)';
    $chipFg = $accent === 'info' ? 'var(--nx-info)' : 'var(--nx-warning)';
@endphp
<div wire:key="tagsel-{{ $key }}" x-data="{ open: false, q: '' }" x-on:keydown.escape="open = false"
    @click.outside="open = false" class="relative">

    {{-- Chips + Trigger --}}
    <div class="flex min-h-[42px] flex-wrap items-center gap-1.5 rounded-[6px] border border-[color:var(--nx-line-strong)] bg-[color:var(--nx-surface)] p-2"
        @click="open = true; $nextTick(() => $refs.q?.focus())">
        @forelse ($selectedModels as $opt)
            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs" style="background:{{ $chipBg }};color:{{ $chipFg }}">
                <span class="font-mono opacity-80">{{ $opt->code }}</span> {{ $opt->name }}
                <button type="button" wire:click.stop="{{ $toggle }}({{ $opt->id }})" class="ml-0.5 hover:opacity-70">✕</button>
            </span>
        @empty
            <span class="px-1 text-sm text-[color:var(--nx-muted)]">{{ $placeholder }}</span>
        @endforelse
        <span class="ml-auto pl-1 text-[color:var(--nx-muted)]">@svg('heroicon-o-chevron-down', 'w-4 h-4')</span>
    </div>

    {{-- Dropdown --}}
    <div x-show="open" x-transition x-cloak
        class="absolute z-30 mt-1 max-h-60 w-full overflow-auto rounded-[8px] border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] shadow-[var(--nx-shadow-pop)]">
        <div class="sticky top-0 border-b border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] p-2">
            <input x-ref="q" x-model="q" type="text" placeholder="Suchen…"
                class="w-full rounded-[6px] border border-[color:var(--nx-line-strong)] bg-[color:var(--nx-surface)] px-2 py-1 text-sm text-[color:var(--nx-text)] focus:border-[color:var(--nx-accent)] focus:outline-none focus:ring-1 focus:ring-[color:var(--nx-accent)]" />
        </div>
        @forelse ($options as $opt)
            @php $isSel = in_array($opt->id, $selected); @endphp
            <button type="button" wire:click="{{ $toggle }}({{ $opt->id }})"
                x-show="q === '' || {{ \Illuminate\Support\Js::from(mb_strtolower($opt->code . ' ' . $opt->name)) }}.includes(q.toLowerCase())"
                class="flex w-full items-center justify-between gap-2 px-3 py-1.5 text-left text-sm hover:bg-[color:var(--nx-hover)] {{ $isSel ? 'font-medium' : 'text-[color:var(--nx-text)]' }}"
                @if ($isSel) style="color:{{ $chipFg }}" @endif>
                <span><span class="font-mono text-xs opacity-70">{{ $opt->code }}</span> {{ $opt->name }}</span>
                @if ($isSel) @svg('heroicon-o-check', 'w-4 h-4') @endif
            </button>
        @empty
            <p class="px-3 py-2 text-xs text-[color:var(--nx-muted)]">
                Keine Einträge – bitte zuerst unter <em>Einstellungen → Allergene &amp; Zusatzstoffe</em> anlegen.
            </p>
        @endforelse
    </div>
</div>
