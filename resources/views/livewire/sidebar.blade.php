<div>
    <div x-show="!collapsed" class="p-3 text-sm italic text-[color:var(--nx-muted)] uppercase border-b border-[color:var(--nx-line)] mb-2">
        PausePlus
    </div>

    {{-- Expanded: gruppierte Navigation --}}
    <div x-show="!collapsed">
    <x-ui-sidebar-list label="Übersicht">
        <x-ui-sidebar-item :href="route('reservation.dashboard')">
            @svg('heroicon-o-home', 'w-4 h-4 text-[color:var(--nx-muted)]')
            <span class="ml-2 text-sm">Dashboard</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('reservation.inbox.index')">
            @svg('heroicon-o-inbox', 'w-4 h-4 text-[color:var(--nx-muted)]')
            <span class="ml-2 text-sm">Posteingang</span>
            @if ($this->inboxCount > 0)
                <span class="ml-auto inline-flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-[color:var(--nx-accent)] px-1 text-[10px] font-semibold text-white">{{ $this->inboxCount }}</span>
            @endif
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('reservation.operations.index')">
            @svg('heroicon-o-fire', 'w-4 h-4 text-[color:var(--nx-muted)]')
            <span class="ml-2 text-sm">Veranstaltungen</span>
            @if ($this->operationsCount > 0)
                <span class="ml-auto inline-flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-[color:var(--nx-accent-soft)] px-1 text-[10px] font-semibold text-[color:var(--nx-muted)]">{{ $this->operationsCount }}</span>
            @endif
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('reservation.bookings.index')">
            @svg('heroicon-o-calendar-days', 'w-4 h-4 text-[color:var(--nx-muted)]')
            <span class="ml-2 text-sm">Alle Buchungen</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    <x-ui-sidebar-list label="Verwaltung">
        <x-ui-sidebar-item :href="route('reservation.events.index')">
            @svg('heroicon-o-ticket', 'w-4 h-4 text-[color:var(--nx-muted)]')
            <span class="ml-2 text-sm">Termine</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('reservation.venues.index')">
            @svg('heroicon-o-building-storefront', 'w-4 h-4 text-[color:var(--nx-muted)]')
            <span class="ml-2 text-sm">Venues &amp; Tischpläne</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('reservation.sales-lists.index')">
            @svg('heroicon-o-queue-list', 'w-4 h-4 text-[color:var(--nx-muted)]')
            <span class="ml-2 text-sm">Verkaufslisten</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('reservation.menu.index')">
            @svg('heroicon-o-rectangle-stack', 'w-4 h-4 text-[color:var(--nx-muted)]')
            <span class="ml-2 text-sm">Artikel</span>
            {{-- Nur was ICH freigeben kann: die eigene Einreichung wäre eine
                 Aufgabe, die der Zähler mir stellt und die ich nicht lösen kann. --}}
            @if ($this->approvalCount > 0)
                <span class="ml-auto inline-flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-[color:var(--nx-accent-soft)] px-1 text-[10px] font-semibold text-[color:var(--nx-muted)]">{{ $this->approvalCount }}</span>
            @endif
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('reservation.stations.index')">
            @svg('heroicon-o-inbox-arrow-down', 'w-4 h-4 text-[color:var(--nx-muted)]')
            <span class="ml-2 text-sm">Abholstationen</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    <x-ui-sidebar-list label="Finanzen">
        <x-ui-sidebar-item :href="route('reservation.finance.index')">
            @svg('heroicon-o-banknotes', 'w-4 h-4 text-[color:var(--nx-muted)]')
            <span class="ml-2 text-sm">Umsatz</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    <x-ui-sidebar-list label="Auswertung">
        <x-ui-sidebar-item :href="route('reservation.products.stats')">
            @svg('heroicon-o-chart-bar', 'w-4 h-4 text-[color:var(--nx-muted)]')
            <span class="ml-2 text-sm">Artikel</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('reservation.checkouts.stats')">
            @svg('heroicon-o-arrow-trending-down', 'w-4 h-4 text-[color:var(--nx-muted)]')
            <span class="ml-2 text-sm">Bestellwege</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('reservation.export')">
            @svg('heroicon-o-arrow-down-tray', 'w-4 h-4 text-[color:var(--nx-muted)]')
            <span class="ml-2 text-sm">Export</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Ein Eintrag statt drei. Allergene und Standzeit-Klassen sind
         Einstellungen wie die übrigen auch; sie stehen jetzt in der zweiten
         Sidebar der Einstellungsseite, zusammen mit den restlichen Kategorien.
         Hier links wurde die Liste sonst länger als die Arbeitsbereiche
         darüber. --}}
    <x-ui-sidebar-list label="Einstellungen">
        <x-ui-sidebar-item :href="route('reservation.settings.checkout')">
            @svg('heroicon-o-cog-6-tooth', 'w-4 h-4 text-[color:var(--nx-muted)]')
            <span class="ml-2 text-sm">Einstellungen</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Die Hilfe ist kein Arbeitsbereich und keine Einstellung, sondern gehört
         zu allen – deshalb ein eigener Block ganz unten. Sie öffnet das
         Hilfe-Modal der Plattform, das in jedem Layout schon geladen ist und
         auf 'open-help' hört; die Seiten dazu liegen in docs/ dieses Moduls.
         Ein Knopf, keine Route: Der Core stellt nur das Modal, keinen Auslöser. --}}
    <x-ui-sidebar-list label="Hilfe">
        <x-ui-sidebar-item type="button" @click="$dispatch('open-help', { module: 'reservation' })">
            @svg('heroicon-o-question-mark-circle', 'w-4 h-4 text-[color:var(--nx-muted)]')
            <span class="ml-2 text-sm">So funktioniert PausePlus</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>
    </div>

    {{-- Collapsed: Icons-only --}}
    <div x-show="collapsed" class="px-2 py-2 border-b border-[color:var(--nx-line)]">
        <div class="flex flex-col gap-2">
            <a href="{{ route('reservation.inbox.index') }}" wire:navigate class="relative flex items-center justify-center p-2 rounded-md text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]" title="Posteingang">
                @svg('heroicon-o-inbox', 'w-5 h-5')
                @if ($this->inboxCount > 0)
                    <span class="absolute right-1 top-1 inline-flex h-4 min-w-[16px] items-center justify-center rounded-full bg-[color:var(--nx-accent)] px-1 text-[9px] font-semibold text-white">{{ $this->inboxCount > 99 ? '99+' : $this->inboxCount }}</span>
                @endif
            </a>
            <a href="{{ route('reservation.operations.index') }}" wire:navigate class="relative flex items-center justify-center p-2 rounded-md text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]" title="Veranstaltungen">
                @svg('heroicon-o-fire', 'w-5 h-5')
                @if ($this->operationsCount > 0)
                    <span class="absolute right-1 top-1 inline-flex h-4 min-w-[16px] items-center justify-center rounded-full bg-[color:var(--nx-accent)] px-1 text-[9px] font-semibold text-white">{{ $this->operationsCount > 99 ? '99+' : $this->operationsCount }}</span>
                @endif
            </a>
            <a href="{{ route('reservation.bookings.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]">
                @svg('heroicon-o-calendar-days', 'w-5 h-5')
            </a>
            <a href="{{ route('reservation.venues.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]">
                @svg('heroicon-o-building-storefront', 'w-5 h-5')
            </a>
            <a href="{{ route('reservation.menu.index') }}" wire:navigate class="relative flex items-center justify-center p-2 rounded-md text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]">
                @svg('heroicon-o-rectangle-stack', 'w-5 h-5')
                @if ($this->approvalCount > 0)
                    <span class="absolute right-1 top-1 inline-flex h-4 min-w-[16px] items-center justify-center rounded-full bg-[color:var(--nx-accent)] px-1 text-[9px] font-semibold text-white">{{ $this->approvalCount > 99 ? '99+' : $this->approvalCount }}</span>
                @endif
            </a>
            <a href="{{ route('reservation.stations.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]">
                @svg('heroicon-o-inbox-arrow-down', 'w-5 h-5')
            </a>
            <a href="{{ route('reservation.events.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]">
                @svg('heroicon-o-ticket', 'w-5 h-5')
            </a>
            <a href="{{ route('reservation.finance.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]">
                @svg('heroicon-o-banknotes', 'w-5 h-5')
            </a>
            <a href="{{ route('reservation.settings.checkout') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]">
                @svg('heroicon-o-cog-6-tooth', 'w-5 h-5')
            </a>
            <a href="{{ route('reservation.export') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]">
                @svg('heroicon-o-arrow-down-tray', 'w-5 h-5')
            </a>
            <button type="button" @click="$dispatch('open-help', { module: 'reservation' })" class="flex items-center justify-center p-2 rounded-md text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]" title="Hilfe">
                @svg('heroicon-o-question-mark-circle', 'w-5 h-5')
            </button>
        </div>
    </div>
</div>
