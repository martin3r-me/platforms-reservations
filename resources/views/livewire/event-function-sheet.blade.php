<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="'Laufzettel – ' . $this->event->name" icon="heroicon-o-clipboard-document-list" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Veranstaltungen', 'href' => route('reservation.operations.index')],
            ['label' => $this->event->name, 'href' => route('reservation.events.dashboard', $this->event->id)],
            ['label' => 'Laufzettel'],
        ]">
            <x-nx-button :href="route('reservation.events.function-sheet', $this->event->id)" target="_blank">
                @svg('heroicon-o-printer', 'w-4 h-4')
                <span>Drucken</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        @include('reservation::partials.event-sidebar', ['event' => $this->event, 'active' => 'function'])
    </x-slot>

    <x-ui-page-container width="contained">
    <div class="space-y-5">
        @php $sheet = $this->sheet; @endphp

        <p class="m-0 text-xs text-[color:var(--nx-muted)]">
            {{ optional($sheet['event']['date'])->format('d.m.Y') }}
            @if ($sheet['event']['venue']) · {{ $sheet['event']['venue'] }} @endif
            · erstellt {{ $sheet['generated_at']->format('d.m.Y H:i') }}
        </p>

        @forelse ($sheet['pauses'] as $pause)
            <x-nx-card flush wire:key="fs-pause-{{ $loop->index }}">
                <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
                    @svg('heroicon-o-clock', 'w-4 h-4 shrink-0 text-[color:var(--nx-muted)]')
                    <h2 class="m-0 text-sm font-semibold text-[color:var(--nx-text)]">
                        {{ $pause['slot']['name'] }}@if ($pause['slot']['time_start']) · {{ $pause['slot']['time_start'] }} Uhr @endif
                    </h2>
                </div>

                <div class="divide-y divide-[color:var(--nx-line)]">
                    @forelse ($pause['runs'] as $run)
                        @php $color = $run['holding_class']['color'] ?? '#9b9a97'; @endphp
                        <div class="px-4 py-3" wire:key="fs-run-{{ $loop->parent->index }}-{{ $loop->index }}">
                            {{-- Laufrunde: Timing --}}
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <span class="h-3 w-3 shrink-0 rounded-full" style="background:{{ $color }}"></span>
                                <span class="text-sm font-medium text-[color:var(--nx-text)]">{{ $run['label'] }}</span>
                                @if ($run['target_time'])
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold" style="color:{{ $color }};background:{{ $color }}1a">
                                        @svg('heroicon-o-clock', 'w-3.5 h-3.5')
                                        platzieren bis {{ $run['target_time'] }} Uhr
                                    </span>
                                @else
                                    <span class="rounded-full bg-[color:var(--nx-accent-soft)] px-2 py-0.5 text-xs text-[color:var(--nx-muted)]">vorab / jederzeit</span>
                                @endif
                            </div>

                            {{-- Tisch-Karten in dieser Laufrunde --}}
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                @foreach ($run['tables'] as $table)
                                    <div class="rounded-[8px] border border-[color:var(--nx-line)] bg-[color:var(--nx-bg)] px-3 py-2">
                                        <div class="mb-1 flex items-baseline gap-2">
                                            <span class="text-sm font-semibold text-[color:var(--nx-text)]">Tisch {{ $table['table']['label'] ?? '—' }}</span>
                                            @if ($table['room'])<span class="text-xs text-[color:var(--nx-faint)]">{{ $table['room'] }}</span>@endif
                                        </div>
                                        @foreach ($table['bookings'] as $booking)
                                            <div class="text-xs leading-relaxed">
                                                <span class="text-[color:var(--nx-muted)]">{{ $booking['guest_name'] }}:</span>
                                                @foreach ($booking['items'] as $item)<span class="font-semibold tabular-nums text-[color:var(--nx-text)]">{{ $item['quantity'] }}×</span> {{ $item['name'] }}@if (! $loop->last), @endif @endforeach
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="px-4 py-4 text-sm text-[color:var(--nx-muted)]">Keine Bestellungen für diese Pause.</div>
                    @endforelse
                </div>
            </x-nx-card>
        @empty
            <x-nx-card>
                <x-nx-empty icon="heroicon-o-clock">Dieser Termin hat keine Pausen.</x-nx-empty>
            </x-nx-card>
        @endforelse
    </div>
    </x-ui-page-container>
</x-ui-page>
