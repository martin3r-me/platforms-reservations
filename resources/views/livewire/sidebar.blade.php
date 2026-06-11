<div>
    <div x-show="!collapsed" class="p-3 text-sm italic text-[var(--ui-secondary)] uppercase border-b border-[var(--ui-border)] mb-2">
        PausePlus
    </div>

    {{-- Expanded: gruppierte Navigation --}}
    <div x-show="!collapsed">
    <x-ui-sidebar-list label="Übersicht">
        <x-ui-sidebar-item :href="route('reservation.bookings.index')">
            @svg('heroicon-o-calendar-days', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Buchungen</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    <x-ui-sidebar-list label="Verwaltung">
        <x-ui-sidebar-item :href="route('reservation.venues.index')">
            @svg('heroicon-o-building-storefront', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Venues &amp; Tischpläne</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('reservation.menu.index')">
            @svg('heroicon-o-rectangle-stack', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Menü</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('reservation.dropoff.index')">
            @svg('heroicon-o-clock', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Drop-off</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    <x-ui-sidebar-list label="Auswertung">
        <x-ui-sidebar-item :href="route('reservation.export')">
            @svg('heroicon-o-arrow-down-tray', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Export</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>
    </div>

    {{-- Collapsed: Icons-only --}}
    <div x-show="collapsed" class="px-2 py-2 border-b border-[var(--ui-border)]">
        <div class="flex flex-col gap-2">
            <a href="{{ route('reservation.bookings.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]">
                @svg('heroicon-o-calendar-days', 'w-5 h-5')
            </a>
            <a href="{{ route('reservation.venues.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]">
                @svg('heroicon-o-building-storefront', 'w-5 h-5')
            </a>
            <a href="{{ route('reservation.menu.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]">
                @svg('heroicon-o-rectangle-stack', 'w-5 h-5')
            </a>
            <a href="{{ route('reservation.dropoff.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]">
                @svg('heroicon-o-clock', 'w-5 h-5')
            </a>
            <a href="{{ route('reservation.export') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]">
                @svg('heroicon-o-arrow-down-tray', 'w-5 h-5')
            </a>
        </div>
    </div>
</div>
