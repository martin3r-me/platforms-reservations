{{--
    Innere Sidebar für den Veranstaltungs-Kontext (page-sidebar-Slot).
    Erwartet: $event (Event), $active ('dashboard'|'kitchen'|'function').
    nx-styled.
--}}
@php
    $active = $active ?? 'dashboard';
    $links = [
        ['reservation.events.dashboard', 'heroicon-o-calendar-days', 'Buchungen', 'dashboard'],
        ['reservation.events.orders', 'heroicon-o-fire', 'Küche', 'kitchen'],
        ['reservation.events.function', 'heroicon-o-clipboard-document-list', 'Laufzettel', 'function'],
    ];
@endphp
<x-ui-page-sidebar title="Veranstaltung" :defaultOpen="true" width="w-72" icon="heroicon-o-fire">
    <div class="p-3">
        {{-- Kontext --}}
        <div class="mb-2 border-b border-[color:var(--nx-line)] px-2 pb-3">
            <div class="truncate text-sm font-semibold text-[color:var(--nx-text)]">{{ $event->name }}</div>
            <div class="text-xs text-[color:var(--nx-muted)]">{{ $event->date->format('d.m.Y') }}</div>
        </div>

        {{-- Sub-Navigation --}}
        <nav class="flex flex-col gap-0.5">
            @foreach ($links as [$route, $icon, $label, $key])
                @php $isActive = $active === $key; @endphp
                <a href="{{ route($route, $event->id) }}" wire:navigate
                   class="flex items-center gap-2.5 rounded-md px-2 py-1.5 text-sm transition-colors {{ $isActive ? 'bg-[color:var(--nx-active)] font-semibold text-[color:var(--nx-text)]' : 'text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)]' }}">
                    @svg($icon, 'w-4 h-4 shrink-0 text-[color:var(--nx-muted)]')
                    <span class="truncate">{{ $label }}</span>
                </a>
            @endforeach
        </nav>

        <div class="my-2 border-t border-[color:var(--nx-line)]"></div>

        <a href="{{ route('reservation.operations.index') }}" wire:navigate
           class="flex items-center gap-2.5 rounded-md px-2 py-1.5 text-sm text-[color:var(--nx-muted)] transition-colors hover:bg-[color:var(--nx-hover)]">
            @svg('heroicon-o-arrow-left', 'w-4 h-4 shrink-0')
            <span>Alle Veranstaltungen</span>
        </a>
    </div>
</x-ui-page-sidebar>
