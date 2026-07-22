<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Veranstaltungen" icon="heroicon-o-fire" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Veranstaltungen'],
        ]" />
    </x-slot>

    <x-ui-page-container width="contained">
    <div class="space-y-5">

        {{-- Zeitfilter (rahmenlos) --}}
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs">
            <div class="flex items-center gap-1">
                <span class="text-[color:var(--nx-faint)]">Zeit</span>
                @foreach (['upcoming' => 'Kommend', 'past' => 'Vergangen', 'all' => 'Alle'] as $val => $label)
                    <button type="button" wire:click="$set('timeFilter', '{{ $val }}')"
                        class="rounded-full px-2.5 py-1 transition-colors {{ $timeFilter === $val ? 'bg-[color:var(--nx-active)] font-medium text-[color:var(--nx-text)]' : 'text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]' }}">{{ $label }}</button>
                @endforeach
            </div>
            <span class="ml-auto tabular-nums text-[color:var(--nx-faint)]">{{ $this->events->count() }} mit Buchungen</span>
        </div>

        @if ($this->events->isEmpty())
            <x-nx-card>
                <x-nx-empty icon="heroicon-o-fire">
                    <span class="text-sm font-medium text-[color:var(--nx-text)]">Keine Veranstaltung mit Buchungen</span>
                    <span class="mt-1 block">Sobald für einen Termin Buchungen eingehen, erscheint er hier für die operative Durchführung.</span>
                </x-nx-empty>
            </x-nx-card>
        @else
            <x-nx-card flush>
                <div>
                    @foreach ($this->events as $event)
                        <a href="{{ route('reservation.events.dashboard', $event->id) }}" wire:navigate wire:key="op-event-{{ $event->id }}"
                            class="flex items-center justify-between gap-3 border-b border-[color:var(--nx-line)] px-4 py-3 transition-colors last:border-0 hover:bg-[color:var(--nx-hover)]">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-medium text-[color:var(--nx-text)]">{{ $event->name }}</span>
                                    @if ($event->date->isToday())
                                        <x-nx-badge variant="success">Heute</x-nx-badge>
                                    @elseif ($event->date->isPast())
                                        <x-nx-badge>Vergangen</x-nx-badge>
                                    @endif
                                </div>
                                <p class="m-0 mt-0.5 text-xs text-[color:var(--nx-muted)]">
                                    {{ $event->date->format('d.m.Y') }}
                                    @if ($event->slots->isNotEmpty())
                                        · {{ $event->slots->map(fn ($s) => $s->displayLabel())->implode(', ') }}
                                    @endif
                                    @if ($event->venue) · {{ $event->venue->name }} @endif
                                    · <span class="font-medium text-[color:var(--nx-text)]">{{ $event->bookings_count }} {{ $event->bookings_count === 1 ? 'Buchung' : 'Buchungen' }}</span>
                                </p>
                            </div>
                            @svg('heroicon-o-chevron-right', 'w-4 h-4 shrink-0 text-[color:var(--nx-faint)]')
                        </a>
                    @endforeach
                </div>
            </x-nx-card>
        @endif

    </div>
    </x-ui-page-container>
</x-ui-page>
