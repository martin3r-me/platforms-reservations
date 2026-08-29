{{-- Zweite Sidebar der Einstellungen: die Kategorien.

     Erwartet:
       $aktiv  - Schlüssel des offenen Eintrags
       $modus  - 'inline' innerhalb der Einstellungs-Komponente, sonst 'link'

     Warum zwei Modi: Innerhalb der Einstellungen darf der Wechsel NICHT
     navigieren. Alles steht in einer Komponente mit einem Speichern; wer in
     „Zahlung" etwas ändert und zu „Buchhaltung" wechselt, würde bei einem
     Seitenwechsel seine Eingaben verlieren. Ein $set lässt die Komponente
     stehen – Livewire hält auch die gerade nicht gezeichneten Eigenschaften.

     Auf den beiden eigenen Seiten (Allergene, Standzeit-Klassen) gibt es diese
     Eigenschaft nicht, dort sind es echte Links.

     Eigenes Markup statt x-ui-sidebar-item: Das hängt an einer Alpine-Variable
     „collapsed" aus der Haupt-Sidebar, die es hier drinnen nicht gibt. --}}
@php
    $inline = ($modus ?? 'link') === 'inline';

    $kategorien = [
        ['key' => 'veranstaltung',      'label' => 'Veranstaltung & Plätze', 'icon' => 'heroicon-o-ticket'],
        ['key' => 'checkout',           'label' => 'Gast-Checkout',          'icon' => 'heroicon-o-identification'],
        ['key' => 'zahlung',            'label' => 'Zahlung',                'icon' => 'heroicon-o-credit-card'],
        ['key' => 'belege',             'label' => 'Belege & Druck',         'icon' => 'heroicon-o-printer'],
        ['key' => 'benachrichtigungen', 'label' => 'Benachrichtigungen',     'icon' => 'heroicon-o-envelope'],
        ['key' => 'buchhaltung',        'label' => 'Buchhaltung',            'icon' => 'heroicon-o-calculator'],
        ['key' => 'artikel',            'label' => 'Artikel & Freigabe',     'icon' => 'heroicon-o-shield-check'],
    ];

    $speisen = [
        ['key' => 'allergene',   'label' => 'Allergene & Zusatzstoffe', 'icon' => 'heroicon-o-beaker', 'href' => route('reservation.settings.declarations')],
        ['key' => 'standzeiten', 'label' => 'Standzeit-Klassen',        'icon' => 'heroicon-o-fire',   'href' => route('reservation.settings.holding-classes')],
    ];

    $klassen = fn (bool $ist) => 'flex w-full items-center gap-3 rounded-md p-2 text-left text-sm font-medium transition-colors '
        . ($ist
            ? 'bg-[color:var(--nx-active)] font-semibold text-[color:var(--nx-text)]'
            : 'text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)]');
@endphp

<div class="flex flex-col gap-4 p-3">
    <div class="flex flex-col gap-1">
        <span class="px-1 pb-1 text-xs font-medium tracking-wide text-[color:var(--nx-faint)]">Einstellungen</span>

        @foreach ($kategorien as $k)
            @if ($inline)
                <button type="button" wire:click="$set('tab', '{{ $k['key'] }}')" class="{{ $klassen($aktiv === $k['key']) }}">
                    @svg($k['icon'], 'w-4 h-4 shrink-0 text-[color:var(--nx-muted)]')
                    <span>{{ $k['label'] }}</span>
                </button>
            @else
                <a href="{{ route('reservation.settings.checkout', ['tab' => $k['key']]) }}" wire:navigate class="{{ $klassen(false) }}">
                    @svg($k['icon'], 'w-4 h-4 shrink-0 text-[color:var(--nx-muted)]')
                    <span>{{ $k['label'] }}</span>
                </a>
            @endif
        @endforeach
    </div>

    {{-- Eigene Seiten mit eigenem Speichern – deshalb immer Links, auch aus
         den Einstellungen heraus. --}}
    <div class="flex flex-col gap-1 border-t border-[color:var(--nx-line)] pt-3">
        <span class="px-1 pb-1 text-xs font-medium tracking-wide text-[color:var(--nx-faint)]">Speisen</span>

        @foreach ($speisen as $s)
            <a href="{{ $s['href'] }}" wire:navigate class="{{ $klassen($aktiv === $s['key']) }}">
                @svg($s['icon'], 'w-4 h-4 shrink-0 text-[color:var(--nx-muted)]')
                <span>{{ $s['label'] }}</span>
            </a>
        @endforeach
    </div>
</div>
