<div class="min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="mx-auto max-w-4xl px-4 py-6 space-y-4">

        {{-- Kopf --}}
        <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm p-4">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-xl font-bold text-[var(--ui-secondary)] m-0">{{ $this->floorPlan->name }}</h1>
                    <p class="text-sm text-[var(--ui-muted)] m-0 mt-0.5">Wähle Datum, Uhrzeit und einen freien Tisch.</p>
                </div>
                <div class="flex gap-2">
                    <input
                        type="date"
                        wire:model.live="selectedDate"
                        class="rounded-md border border-[var(--ui-border)] px-3 py-2 text-sm text-[var(--ui-secondary)]"
                    />
                    <input
                        type="time"
                        wire:model.live="selectedTimeStart"
                        class="w-28 rounded-md border border-[var(--ui-border)] px-3 py-2 text-sm text-[var(--ui-secondary)]"
                    />
                </div>
            </div>
        </section>

        {{-- Tischplan (dunkle Bühne als Panel) --}}
        <section class="overflow-hidden rounded-2xl bg-slate-900 shadow-sm">
            <div class="flex flex-wrap items-center justify-center gap-x-5 gap-y-1 border-b border-slate-700 bg-slate-800 px-3 py-2 text-xs text-slate-300">
                <span class="flex items-center gap-1.5">
                    <span class="inline-block h-3 w-3 rounded-full bg-emerald-500 shadow-[0_0_6px_rgba(52,211,153,0.7)]"></span>
                    Frei
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="inline-block h-3 w-3 rounded-full bg-red-500"></span>
                    Belegt
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="inline-block h-3 w-3 rounded-full bg-indigo-500 shadow-[0_0_6px_rgba(99,102,241,0.7)]"></span>
                    Ausgewählt
                </span>
                <span class="text-slate-500">Scroll/Pinch = Zoom</span>
            </div>
            <div class="flex flex-col" style="min-height: 55vh;">
                @include('reservation::partials.floor-plan-svg', [
                    'tableStates' => collect($this->tableAvailability)->map(fn ($info) => [
                        'table'     => $info['table'],
                        'state'     => $selectedTableId === $info['table']->id
                            ? 'selected'
                            : ($info['available'] ? 'free' : 'full'),
                        'remaining' => null,
                    ])->all(),
                    'clickAction' => 'selectTable',
                ])
            </div>
        </section>

        {{-- Auswahl-CTA --}}
        @if ($selectedTableId)
            @php $info = $this->tableAvailability[$selectedTableId] ?? null; @endphp
            @if ($info)
                <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-semibold text-[var(--ui-secondary)] m-0">Tisch {{ $info['table']->label }}</p>
                            <p class="text-sm text-[var(--ui-muted)] m-0">Bis zu {{ $info['table']->capacity }} Personen</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                wire:click="$set('selectedTableId', null)"
                                class="rounded-md border border-[var(--ui-border)] px-4 py-2 text-sm text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]"
                            >Abwählen</button>
                            <a
                                href="{{ route('reservation.bookings.create', ['tableId' => $selectedTableId, 'date' => $selectedDate, 'timeStart' => $selectedTimeStart]) }}"
                                class="rounded-md bg-[var(--ui-primary)] px-5 py-2 text-sm font-semibold text-white hover:opacity-90"
                            >
                                Jetzt buchen →
                            </a>
                        </div>
                    </div>
                </section>
            @endif
        @endif
    </div>
</div>
