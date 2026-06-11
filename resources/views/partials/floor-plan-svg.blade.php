{{--
    Wiederverwendbarer 3D-Tischplan mit Pan/Zoom (FloorPlanViewer + Gast-CheckoutWizard).

    Erwartet:
    - $tableStates: array<int, array{table: \Platform\Reservation\Models\Table, state: string, remaining: ?int}>
        state: free | partial | full | selected
        remaining: Restplätze (null = keine Anzeige, dann Kapazität)
    - $clickAction: Livewire-Methodenname für klickbare Tische (state != full)
--}}
<div
    class="relative flex-1 overflow-hidden"
    style="min-height: 55vh; perspective: 1200px; cursor: grab;"
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
                @foreach ($tableStates as $info)
                    @php
                        $table = $info['table'];
                        $state = $info['state'];
                        $remaining = $info['remaining'] ?? null;

                        [$bgColor, $sideColor, $glow] = match ($state) {
                            'selected' => ['#6366f1', '#3730a3', '0 0 20px rgba(99,102,241,0.6)'],
                            'free'     => ['#22c55e', '#15803d', '0 0 14px rgba(34,197,94,0.35)'],
                            'partial'  => ['#f59e0b', '#b45309', '0 0 14px rgba(245,158,11,0.35)'],
                            default    => ['#ef4444', '#b91c1c', 'none'],
                        };
                        $clickable = $state !== 'full';
                        $radius    = $table->shape === 'round' ? '50%' : '10px';
                        $cursor    = $clickable ? 'cursor-pointer' : 'cursor-not-allowed opacity-80';
                    @endphp
                    <div
                        wire:key="vt-{{ $table->id }}"
                        @if ($clickable)
                            wire:click="{{ $clickAction }}({{ $table->id }})"
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
                            {{ $state === 'selected' ? 'outline: 3px solid #fbbf24; outline-offset: 4px;' : '' }}
                        "
                    >
                        <div class="pointer-events-none text-center leading-tight">
                            <div class="text-sm font-bold drop-shadow">{{ $table->label }}</div>
                            <div class="text-xs opacity-75">
                                @if ($remaining !== null)
                                    {{ $remaining }} frei
                                @else
                                    {{ $table->capacity }}P
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
