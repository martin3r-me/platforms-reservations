<div
    class="flex flex-col bg-slate-900"
    style="min-height: 100svh;"
    x-data="{
        scale: 1,
        panX: 0,
        panY: 0,
        isPanning: false,
        lastTouch: null,
        pinchDist: null,

        zoom(factor, cx, cy) {
            const newScale = Math.min(3, Math.max(0.3, this.scale * factor));
            const ratio = newScale / this.scale;
            this.panX = cx - ratio * (cx - this.panX);
            this.panY = cy - ratio * (cy - this.panY);
            this.scale = newScale;
        },

        onWheel(e) {
            const rect = e.currentTarget.getBoundingClientRect();
            const cx = e.clientX - rect.left - rect.width / 2;
            const cy = e.clientY - rect.top - rect.height / 2;
            this.zoom(1 - e.deltaY * 0.001, cx, cy);
        },

        onTouchStart(e) {
            if (e.touches.length === 2) {
                this.pinchDist = Math.hypot(
                    e.touches[0].clientX - e.touches[1].clientX,
                    e.touches[0].clientY - e.touches[1].clientY
                );
                this.isPanning = false;
            } else {
                this.isPanning = true;
                this.lastTouch = { x: e.touches[0].clientX, y: e.touches[0].clientY };
            }
        },

        onTouchMove(e) {
            e.preventDefault();
            if (e.touches.length === 2 && this.pinchDist) {
                const dist = Math.hypot(
                    e.touches[0].clientX - e.touches[1].clientX,
                    e.touches[0].clientY - e.touches[1].clientY
                );
                const midX = (e.touches[0].clientX + e.touches[1].clientX) / 2;
                const midY = (e.touches[0].clientY + e.touches[1].clientY) / 2;
                const rect = e.currentTarget.getBoundingClientRect();
                this.zoom(dist / this.pinchDist, midX - rect.left - rect.width / 2, midY - rect.top - rect.height / 2);
                this.pinchDist = dist;
            } else if (this.isPanning && this.lastTouch) {
                this.panX += e.touches[0].clientX - this.lastTouch.x;
                this.panY += e.touches[0].clientY - this.lastTouch.y;
                this.lastTouch = { x: e.touches[0].clientX, y: e.touches[0].clientY };
            }
        },

        onTouchEnd() {
            this.pinchDist = null;
            this.isPanning = false;
        }
    }"
>
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

    {{-- ── 3D Viewport ───────────────────────────────────────── --}}
    <div
        class="relative flex-1 overflow-hidden"
        style="min-height: 55vh; perspective: 1200px; cursor: grab;"
        @wheel.prevent="onWheel($event)"
        @touchstart.prevent="onTouchStart($event)"
        @touchmove.prevent="onTouchMove($event)"
        @touchend="onTouchEnd()"
    >
        {{-- Pan + Scale wrapper (centered) --}}
        <div class="absolute inset-0 flex items-start justify-center" style="padding-top: 8%;">
            <div
                :style="`transform: scale(${scale}) translate(${panX / scale}px, ${panY / scale}px);`"
                style="transform-origin: center top; will-change: transform;"
            >
                {{-- Tilted 3D canvas --}}
                <div
                    class="relative"
                    style="
                        width: 800px;
                        height: 600px;
                        transform: rotateX(40deg);
                        transform-origin: center top;
                        background-color: #1e293b;
                        background-image:
                            linear-gradient(rgba(255,255,255,0.035) 1px, transparent 1px),
                            linear-gradient(90deg, rgba(255,255,255,0.035) 1px, transparent 1px);
                        background-size: 40px 40px;
                        border-radius: 16px;
                        box-shadow:
                            0 80px 160px -20px rgba(0,0,0,0.95),
                            inset 0 0 0 1px rgba(255,255,255,0.05);
                    "
                >
                    {{-- Bühne / Stage als Referenzfläche oben --}}
                    <div
                        class="absolute left-1/2 -translate-x-1/2 flex items-center justify-center rounded-lg text-slate-400 text-xs font-medium tracking-widest uppercase"
                        style="
                            top: 10px;
                            width: 340px;
                            height: 36px;
                            background: rgba(255,255,255,0.04);
                            border: 1px solid rgba(255,255,255,0.08);
                            letter-spacing: 0.2em;
                        "
                    >Bühne</div>

                    {{-- Tische --}}
                    @foreach ($this->tableAvailability as $tableId => $info)
                        @php
                            $table      = $info['table'];
                            $isSelected = $selectedTableId === $table->id;
                            $isAvail    = $info['available'];

                            $bgColor   = $isSelected ? '#6366f1'
                                       : ($isAvail   ? '#22c55e' : '#ef4444');
                            $sideColor = $isSelected ? '#3730a3'
                                       : ($isAvail   ? '#15803d' : '#b91c1c');
                            $glow      = $isSelected ? '0 0 20px rgba(99,102,241,0.6)'
                                       : ($isAvail   ? '0 0 14px rgba(34,197,94,0.35)' : 'none');
                            $radius    = $table->shape === 'round' ? '50%' : '10px';
                            $cursor    = $isAvail ? 'cursor-pointer' : 'cursor-not-allowed opacity-80';
                        @endphp
                        <div
                            wire:key="vt-{{ $table->id }}"
                            @if ($isAvail)
                                wire:click="selectTable({{ $table->id }})"
                            @endif
                            class="absolute flex flex-col items-center justify-center text-white transition-all duration-150 select-none {{ $cursor }}"
                            style="
                                left: {{ $table->x }}px;
                                top: {{ $table->y }}px;
                                width: {{ $table->width }}px;
                                height: {{ $table->height }}px;
                                background: {{ $bgColor }};
                                border-radius: {{ $radius }};
                                box-shadow:
                                    0 10px 0 {{ $sideColor }},
                                    {{ $glow }};
                                transform: translateY(-5px);
                                {{ $isSelected ? 'outline: 3px solid #fbbf24; outline-offset: 4px;' : '' }}
                            "
                        >
                            <div class="pointer-events-none text-center leading-tight">
                                <div class="text-sm font-bold drop-shadow">{{ $table->label }}</div>
                                <div class="text-xs opacity-75">{{ $table->capacity }}P</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

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
