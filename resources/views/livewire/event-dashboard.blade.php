<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$this->event->name" icon="heroicon-o-fire" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Veranstaltungen', 'href' => route('reservation.operations.index')],
            ['label' => $this->event->name],
        ]">
            <div class="flex items-center gap-2">
                @if (\Illuminate\Support\Facades\Route::has('reservation.guest.checkout') && $this->event->status->value === 'published')
                    <x-ui-button variant="secondary-outline" size="sm" :href="route('reservation.guest.checkout', $this->event->uuid)" target="_blank">
                        @svg('heroicon-o-eye', 'w-4 h-4')
                        <span>Gast-Ansicht</span>
                    </x-ui-button>
                @endif
                <x-ui-button variant="secondary-outline" size="sm" :href="route('reservation.events.briefing', $this->event->id)" target="_blank">
                    @svg('heroicon-o-presentation-chart-bar', 'w-4 h-4')
                    <span>Abend-Übersicht</span>
                </x-ui-button>
            </div>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
    <div class="pt-4 space-y-4">

        @php
            $currency = strtoupper((string) config('reservation.currency', 'EUR'));
            $sym = $currency === 'EUR' ? '€' : $currency;
            $s = $this->stats;
        @endphp

        {{-- Kopf: Termin-Kontext --}}
        <div class="flex flex-wrap items-center gap-2 text-sm text-[var(--ui-muted)]">
            <x-ui-badge :variant="$this->event->status->badgeVariant()" size="xs">{{ $this->event->status->label() }}</x-ui-badge>
            <span>{{ $this->event->date->format('d.m.Y') }}</span>
            @if ($this->event->venue) <span>· {{ $this->event->venue->name }}</span> @endif
            @if ($this->event->slots->isNotEmpty())
                <span>· {{ $this->event->slots->map(fn ($sl) => $sl->displayLabel())->implode(', ') }}</span>
            @endif
        </div>

        {{-- Kennzahlen --}}
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm p-3">
                <span class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)]">Buchungen</span>
                <p class="m-0 mt-1 text-lg font-bold tabular-nums text-[var(--ui-secondary)]">{{ $s['bookings'] }}</p>
            </div>
            <div class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm p-3">
                <span class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)]">Gäste</span>
                <p class="m-0 mt-1 text-lg font-bold tabular-nums text-[var(--ui-secondary)]">{{ $s['guests'] }}</p>
            </div>
            <div class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm p-3">
                <span class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)]">Umsatz</span>
                <p class="m-0 mt-1 text-lg font-bold tabular-nums text-[var(--ui-secondary)]">{{ number_format($s['revenue'], 2, ',', '.') }} {{ $sym }}</p>
            </div>
            <div class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm p-3">
                <span class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)]">Pausen</span>
                <p class="m-0 mt-1 text-lg font-bold tabular-nums text-[var(--ui-secondary)]">{{ $s['pauses'] }}</p>
            </div>
        </div>

        {{-- Vollwertige Views --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <a href="{{ route('reservation.events.orders', $this->event->id) }}" wire:navigate
               class="group flex items-start gap-4 rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm p-5 transition-colors hover:border-[var(--ui-primary)]/50 hover:bg-[var(--ui-primary-10)]/20">
                <div class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-[var(--ui-primary-10)]">
                    @svg('heroicon-o-fire', 'w-6 h-6 text-[var(--ui-primary)]')
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="m-0 text-base font-semibold text-[var(--ui-secondary)]">Küche</h3>
                    <p class="m-0 mt-1 text-sm text-[var(--ui-muted)]">Gesamtbestellungen je Pause – was die Küche bereitstellen muss.</p>
                </div>
                @svg('heroicon-o-arrow-right', 'w-5 h-5 text-[var(--ui-muted)] transition-transform group-hover:translate-x-0.5')
            </a>

            <a href="{{ route('reservation.events.function', $this->event->id) }}" wire:navigate
               class="group flex items-start gap-4 rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm p-5 transition-colors hover:border-[var(--ui-primary)]/50 hover:bg-[var(--ui-primary-10)]/20">
                <div class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-[var(--ui-primary-10)]">
                    @svg('heroicon-o-clipboard-document-list', 'w-6 h-6 text-[var(--ui-primary)]')
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="m-0 text-base font-semibold text-[var(--ui-secondary)]">Laufzettel</h3>
                    <p class="m-0 mt-1 text-sm text-[var(--ui-muted)]">Laufrunden je Pause: Standzeit-Klasse → Tisch → Bestellung.</p>
                </div>
                @svg('heroicon-o-arrow-right', 'w-5 h-5 text-[var(--ui-muted)] transition-transform group-hover:translate-x-0.5')
            </a>
        </div>

    </div>
    </x-ui-page-container>
</x-ui-page>
