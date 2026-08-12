{{--
    Reiter für den Veranstaltungs-Kontext: Buchungen / Küche / Laufzettel.

    Ersetzt die frühere innere Sidebar. Die übrigen Modul-Seiten haben keine
    zweite Spalte, und für drei Einträge war eine eigene Spalte zu viel Fläche.
    Zurück zur Übersicht führt der Breadcrumb, deshalb hier kein "Alle
    Veranstaltungen" mehr.

    Erwartet: $event (Event), $active ('dashboard'|'kitchen'|'function').

    Die Reiter sind <a> und nicht <x-nx-tab>: das sind eigene Routen, keine
    Zustände einer Seite. Die Auszeichnung von x-nx-tab ist deshalb hier
    nachgebildet; den Unterstrich zeichnet der Container x-nx-tabs.
--}}
@php
    $active = $active ?? 'dashboard';

    $tabs = [
        ['key' => 'dashboard', 'label' => 'Buchungen',  'icon' => 'heroicon-o-calendar-days',           'route' => 'reservation.events.dashboard'],
        ['key' => 'kitchen',   'label' => 'Küche',      'icon' => 'heroicon-o-fire',                    'route' => 'reservation.events.orders'],
        ['key' => 'function',  'label' => 'Laufzettel', 'icon' => 'heroicon-o-clipboard-document-list', 'route' => 'reservation.events.function'],
    ];
@endphp

<x-nx-tabs class="mb-0">
    @foreach ($tabs as $tab)
        @php $isActive = $active === $tab['key']; @endphp
        <a href="{{ route($tab['route'], $event->id) }}" wire:navigate
           @if ($isActive) aria-current="page" @endif
           class="flex items-center gap-2 border-b-2 px-3 py-2 text-sm font-medium transition-colors
                  {{ $isActive
                        ? 'border-[color:var(--nx-accent)] text-[color:var(--nx-text)]'
                        : 'border-transparent text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]' }}">
            @svg($tab['icon'], 'w-4 h-4 shrink-0')
            <span>{{ $tab['label'] }}</span>
        </a>
    @endforeach
</x-nx-tabs>
