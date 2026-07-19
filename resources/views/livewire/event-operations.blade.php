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

    <x-ui-page-container>
    <div class="pt-4 space-y-4">

        <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
                @svg('heroicon-o-fire', 'w-4 h-4 text-[var(--ui-muted)]')
                <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">
                    Veranstaltungen mit Buchungen
                </h2>
                <span class="ml-auto text-[11px] text-[var(--ui-muted)]">{{ $this->events->count() }}</span>
            </div>

            {{-- Zeitfilter --}}
            <div class="flex flex-wrap items-center gap-1.5 border-b border-[var(--ui-border)]/30 px-4 py-2 text-[11px]">
                <span class="text-[var(--ui-muted)]">Zeit:</span>
                @foreach (['upcoming' => 'Kommend', 'past' => 'Vergangen', 'all' => 'Alle'] as $val => $label)
                    <button type="button" wire:click="$set('timeFilter', '{{ $val }}')"
                        class="rounded-full px-2.5 py-0.5 transition-colors {{ $timeFilter === $val ? 'bg-[var(--ui-primary)] font-medium text-white' : 'text-[var(--ui-muted)] hover:bg-[var(--ui-muted-5)]' }}">{{ $label }}</button>
                @endforeach
            </div>

            @if ($this->events->isEmpty())
                <div class="flex flex-col items-center justify-center py-16 text-[var(--ui-muted)]">
                    @svg('heroicon-o-fire', 'w-10 h-10 mb-3 opacity-40')
                    <span class="text-sm font-medium text-[var(--ui-secondary)]">Keine Veranstaltung mit Buchungen</span>
                    <span class="text-xs mt-1 opacity-70">Sobald für einen Termin Buchungen eingehen, erscheint er hier für die operative Durchführung.</span>
                </div>
            @else
                <div class="divide-y divide-[var(--ui-border)]/30">
                    @foreach ($this->events as $event)
                        <div wire:key="op-event-{{ $event->id }}" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-[var(--ui-muted-5)] transition-colors">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-medium text-[var(--ui-secondary)]">{{ $event->name }}</span>
                                    @if ($event->date->isToday())
                                        <x-ui-badge variant="success" size="xs">Heute</x-ui-badge>
                                    @elseif ($event->date->isPast())
                                        <x-ui-badge variant="muted" size="xs">Vergangen</x-ui-badge>
                                    @endif
                                </div>
                                <p class="mt-0.5 text-xs text-[var(--ui-muted)] m-0">
                                    {{ $event->date->format('d.m.Y') }}
                                    @if ($event->slots->isNotEmpty())
                                        · {{ $event->slots->map(fn ($s) => $s->displayLabel())->implode(', ') }}
                                    @endif
                                    @if ($event->venue) · {{ $event->venue->name }} @endif
                                    · <span class="font-medium text-[var(--ui-secondary)]">{{ $event->bookings_count }} {{ $event->bookings_count === 1 ? 'Buchung' : 'Buchungen' }}</span>
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center justify-end">
                                <x-ui-button variant="primary" size="sm" :href="route('reservation.events.dashboard', $event->id)" wire:navigate>
                                    <span>Öffnen</span>
                                    @svg('heroicon-o-arrow-right', 'w-4 h-4')
                                </x-ui-button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

    </div>
    </x-ui-page-container>
</x-ui-page>
