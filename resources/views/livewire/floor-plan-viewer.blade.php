<div class="flex flex-col bg-slate-900" style="min-height: 100svh;">
    {{-- ── Header ────────────────────────────────────────────── --}}
    <div class="sticky top-0 z-30 bg-slate-800 px-4 py-3 shadow-lg">
        <h1 class="mb-2 text-center text-lg font-bold text-white">
            {{ $this->floorPlan->name }}
        </h1>
        <div class="flex gap-2">
            <input
                type="date"
                wire:model.live="selectedDate"
                class="flex-1 rounded-lg border border-slate-600 bg-slate-700 px-3 py-2 text-sm text-white"
            />
            <input
                type="time"
                wire:model.live="selectedTimeStart"
                placeholder="Ab"
                class="w-28 rounded-lg border border-slate-600 bg-slate-700 px-3 py-2 text-sm text-white"
            />
        </div>
    </div>

    {{-- ── Legende ───────────────────────────────────────────── --}}
    <div class="flex items-center justify-center gap-6 border-b border-slate-700 bg-slate-800 py-2 text-xs text-slate-300">
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

    {{-- ── 3D-Tischplan (gemeinsames Partial) ────────────────── --}}
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

    {{-- ── Sticky CTA (wenn Tisch ausgewählt) ──────────────────── --}}
    @if ($selectedTableId)
        @php $info = $this->tableAvailability[$selectedTableId] ?? null; @endphp
        @if ($info)
            <div class="fixed bottom-0 left-0 right-0 z-40 border-t border-slate-700 bg-slate-800 p-4 shadow-2xl">
                <div class="mb-3 flex items-center justify-between">
                    <div>
                        <p class="font-semibold text-white">{{ $info['table']->label }}</p>
                        <p class="text-sm text-slate-400">Bis zu {{ $info['table']->capacity }} Personen</p>
                    </div>
                    <button
                        wire:click="$set('selectedTableId', null)"
                        class="text-2xl leading-none text-slate-400 hover:text-white"
                    >&times;</button>
                </div>
                <a
                    href="{{ route('reservation.bookings.create', ['tableId' => $selectedTableId, 'date' => $selectedDate, 'timeStart' => $selectedTimeStart]) }}"
                    class="block w-full rounded-xl bg-indigo-600 py-3 text-center text-base font-bold text-white hover:bg-indigo-500 active:bg-indigo-800"
                >
                    Jetzt buchen →
                </a>
            </div>
            {{-- Spacer so content isn't hidden behind the CTA --}}
            <div class="h-28"></div>
        @endif
    @endif
</div>
