<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="'Küche – ' . $this->event->name" icon="heroicon-o-fire" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Veranstaltungen', 'href' => route('reservation.operations.index')],
            ['label' => $this->event->name, 'href' => route('reservation.events.dashboard', $this->event->id)],
            ['label' => 'Küche'],
        ]">
            <x-nx-button onclick="window.print()">
                @svg('heroicon-o-printer', 'w-4 h-4')
                <span>Drucken</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        @include('reservation::partials.event-sidebar', ['event' => $this->event, 'active' => 'kitchen'])
    </x-slot>

    <x-ui-page-container width="contained">
    <div class="space-y-5">

        @php $totals = $this->slotStats->get(0); @endphp
        {{-- dünne Kennzahl-Zeile --}}
        <div class="flex flex-wrap items-center gap-x-6 gap-y-1 border-b border-[color:var(--nx-line)] pb-3">
            <div>
                <div class="text-xl font-bold leading-none tabular-nums text-[color:var(--nx-text)]">{{ $totals?->bookings ?? 0 }}</div>
                <div class="mt-1 text-xs text-[color:var(--nx-muted)]">Buchungen</div>
            </div>
            <div>
                <div class="text-xl font-bold leading-none tabular-nums text-[color:var(--nx-text)]">{{ $totals?->guests ?? 0 }}</div>
                <div class="mt-1 text-xs text-[color:var(--nx-muted)]">Gäste</div>
            </div>
            <div>
                <div class="text-xl font-bold leading-none tabular-nums text-[color:var(--nx-text)]">{{ $this->event->slots->count() }}</div>
                <div class="mt-1 text-xs text-[color:var(--nx-muted)]">{{ $this->event->slots->count() === 1 ? 'Pause' : 'Pausen' }}</div>
            </div>
            <span class="ml-auto text-xs text-[color:var(--nx-faint)]">ohne Stornos / No-Shows</span>
        </div>

        {{-- Vorbereitungsplan: pro Pause → Standzeit-Klasse (Timing) → Mengen --}}
        @forelse ($this->prepBySlot as $slot)
            <x-nx-card flush wire:key="prep-slot-{{ $loop->index }}">
                {{-- Pausen-Kopf --}}
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-[color:var(--nx-line)] px-4 py-3">
                    @svg('heroicon-o-clock', 'w-4 h-4 shrink-0 text-[color:var(--nx-muted)]')
                    <h2 class="m-0 text-sm font-semibold text-[color:var(--nx-text)]">{{ $slot['slot']->displayLabel() }}</h2>
                    <span class="text-xs tabular-nums text-[color:var(--nx-faint)]">{{ $slot['total'] }} Artikel gesamt</span>
                </div>

                {{-- Timing-Gruppen: was wann vorbereiten --}}
                <div class="divide-y divide-[color:var(--nx-line)]">
                    @foreach ($slot['groups'] as $g)
                        @php $color = $g['color'] ?: '#9b9a97'; @endphp
                        <div wire:key="prep-{{ $loop->parent->index }}-g-{{ $loop->index }}" class="px-4 py-3">
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <span class="h-3 w-3 shrink-0 rounded-full" style="background:{{ $color }}"></span>
                                <span class="text-sm font-medium text-[color:var(--nx-text)]">{{ $g['name'] }}</span>
                                @if ($g['target_time'])
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold" style="color:{{ $color }};background:{{ $color }}1a">
                                        @svg('heroicon-o-clock', 'w-3.5 h-3.5')
                                        zubereiten ab {{ $g['target_time'] }} Uhr
                                    </span>
                                @else
                                    <span class="rounded-full bg-[color:var(--nx-accent-soft)] px-2 py-0.5 text-xs text-[color:var(--nx-muted)]">vorab / jederzeit</span>
                                @endif
                                <span class="ml-auto text-xs tabular-nums text-[color:var(--nx-faint)]">{{ $g['total'] }} Stück</span>
                            </div>
                            <div class="space-y-1">
                                @foreach ($g['items'] as $it)
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="min-w-0 truncate text-sm text-[color:var(--nx-text)]">{{ $it['name'] }}</span>
                                        <span class="shrink-0 text-lg font-bold tabular-nums text-[color:var(--nx-text)]">{{ $it['qty'] }}×</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-nx-card>
        @empty
            <x-nx-card>
                <x-nx-empty icon="heroicon-o-inbox">
                    <span class="text-sm font-medium text-[color:var(--nx-text)]">Noch keine Bestellungen</span>
                    <span class="mt-1 block">Sobald Gäste vorbestellen, erscheint hier der Vorbereitungsplan je Pause.</span>
                </x-nx-empty>
            </x-nx-card>
        @endforelse

    </div>
    </x-ui-page-container>
</x-ui-page>
