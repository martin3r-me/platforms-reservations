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
            <x-ui-button variant="secondary-outline" size="sm" :href="route('reservation.events.function-sheet', $this->event->id)" target="_blank">
                @svg('heroicon-o-printer', 'w-4 h-4')
                <span>Drucken</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        @include('reservation::partials.event-sidebar', ['event' => $this->event, 'active' => 'function'])
    </x-slot>

    <x-ui-page-container>
    <div class="pt-4 space-y-4">
        @php $sheet = $this->sheet; @endphp

        <p class="text-xs text-[var(--ui-muted)] m-0">
            {{ optional($sheet['event']['date'])->format('d.m.Y') }}
            @if ($sheet['event']['venue']) · {{ $sheet['event']['venue'] }} @endif
            · erstellt {{ $sheet['generated_at']->format('d.m.Y H:i') }}
        </p>

        @forelse ($sheet['pauses'] as $pause)
            <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
                    @svg('heroicon-o-clock', 'w-4 h-4 text-[var(--ui-muted)]')
                    <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">
                        {{ $pause['slot']['name'] }}@if ($pause['slot']['time_start']) · {{ $pause['slot']['time_start'] }} Uhr @endif
                    </h2>
                </div>

                <div class="divide-y divide-[var(--ui-border)]/30">
                    @forelse ($pause['runs'] as $run)
                        <div class="px-4 py-3">
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <span class="inline-block h-3 w-3 shrink-0 rounded-full border border-black/10" style="background: {{ $run['holding_class']['color'] ?? '#94a3b8' }}"></span>
                                <span class="text-sm font-semibold text-[var(--ui-secondary)]">{{ $run['label'] }}</span>
                                @if ($run['target_time'])
                                    <span class="rounded-full bg-[var(--ui-primary-10)] px-2 py-0.5 text-[11px] font-semibold text-[var(--ui-primary)]">platzieren bis {{ $run['target_time'] }} Uhr</span>
                                @else
                                    <span class="rounded-full bg-[var(--ui-success-10)] px-2 py-0.5 text-[11px] font-semibold text-[var(--ui-success)]">zeitlich egal / vorab</span>
                                @endif
                            </div>

                            @foreach ($run['tables'] as $table)
                                <div class="ml-2 border-l-2 border-[var(--ui-border)]/50 pl-3 py-1">
                                    <div class="text-xs font-semibold text-[var(--ui-secondary)]">
                                        Tisch {{ $table['table']['label'] ?? '—' }}
                                        @if ($table['room'])<span class="font-normal text-[var(--ui-muted)]">· {{ $table['room'] }}</span>@endif
                                    </div>
                                    @foreach ($table['bookings'] as $booking)
                                        <div class="ml-2 mt-0.5 text-xs">
                                            <span class="text-[var(--ui-muted)]">{{ $booking['guest_name'] }}:</span>
                                            <span>
                                                @foreach ($booking['items'] as $item)<span class="font-bold tabular-nums">{{ $item['quantity'] }}×</span> {{ $item['name'] }}@if (! $loop->last), @endif @endforeach
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    @empty
                        <div class="px-4 py-4 text-sm text-[var(--ui-muted)]">Keine Bestellungen für diese Pause.</div>
                    @endforelse
                </div>
            </section>
        @empty
            <div class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm p-8 text-center text-sm text-[var(--ui-muted)]">
                Dieser Termin hat keine Pausen.
            </div>
        @endforelse
    </div>
    </x-ui-page-container>
</x-ui-page>
