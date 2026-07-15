{{--
    Wiederverwendbarer Tischplan (Draufsicht) mit Pan/Zoom.
    Genutzt von FloorPlanViewer + Gast-CheckoutWizard.

    Erwartet:
    - $tableStates: array<int, array{table: \Platform\Reservation\Models\Table, state: string, remaining: ?int}>
        state: free | partial | full | selected
        remaining: Restplätze (null = keine Anzeige, dann Kapazität)
    - $clickAction: Livewire-Methodenname für klickbare Tische (state != full)
--}}
<div
    class="relative flex-1 overflow-hidden"
    style="min-height: 55vh; cursor: grab;"
    x-data="{
        scale: 1,
        panX: 0,
        panY: 0,
        isPanning: false,
        lastTouch: null,
        pinchDist: null,

        zoom(factor, cx, cy) {
            const newScale = Math.min(3, Math.max(0.4, this.scale * factor));
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
    @if (count($tableStates) === 0)
        {{-- Raum ohne Tische --}}
        <div class="absolute inset-0 flex flex-col items-center justify-center text-slate-400">
            @svg('heroicon-o-map', 'w-10 h-10 mb-3 opacity-40')
            <span class="text-sm">Für diesen Raum sind noch keine Tische hinterlegt.</span>
        </div>
    @else
    {{-- Pan + Zoom wrapper (zentriert) --}}
    <div class="absolute inset-0 flex items-center justify-center p-4 sm:p-6">
        <div
            class="w-full"
            style="max-width: 900px;"
            :style="`transform: scale(${scale}) translate(${panX / scale}px, ${panY / scale}px); will-change: transform;`"
        >
            @php
                $bg  = $backgroundUrl ?? null;
                $rot = ((((int) ($rotation ?? 0)) % 360) + 360) % 360;
                // Seitenverhältnis der Fläche = angezeigtes Grundriss-Verhältnis (rotationsbewusst).
                $aspect = $aspect ?? (4 / 3);
            @endphp
            {{-- Plan-Fläche mit Bild-Seitenverhältnis (kein Letterbox); responsiv --}}
            <div
                class="relative w-full overflow-hidden"
                @if ($bg)
                    style="aspect-ratio: {{ $aspect }}; background-color:#1e293b; border-radius:16px; box-shadow: inset 0 0 0 1px rgba(255,255,255,0.06);"
                @else
                    style="aspect-ratio: {{ $aspect }}; background-color:#1e293b; border-radius:16px; box-shadow: inset 0 0 0 1px rgba(255,255,255,0.06);
                        background-image:
                            linear-gradient(rgba(255,255,255,0.04) 1px, transparent 1px),
                            linear-gradient(90deg, rgba(255,255,255,0.04) 1px, transparent 1px);
                        background-size: 5% 6.66%;"
                @endif
            >
                @if ($bg)
                    {{-- Grundriss-Layer (rotationsbewusst, füllt die Fläche) --}}
                    <img
                        src="{{ $bg }}"
                        alt="Grundriss"
                        x-data="{ rot: {{ $rot }}, w: 0, h: 0, fit() { const p = $el.parentElement; const cw = p.clientWidth, ch = p.clientHeight; if (this.rot % 180 === 0) { this.w = cw; this.h = ch; } else { this.w = ch; this.h = cw; } } }"
                        x-init="fit(); new ResizeObserver(() => fit()).observe($el.parentElement)"
                        :style="`position:absolute; left:50%; top:50%; width:${w}px; height:${h}px; object-fit:contain; transform:translate(-50%,-50%) rotate(${rot}deg); pointer-events:none; user-select:none;`"
                    />
                @endif
                {{-- Bühne / Referenzfläche oben (nur ohne Grundriss) --}}
                @unless ($bg)
                <div
                    class="absolute left-1/2 top-[3%] h-[6%] w-[40%] -translate-x-1/2 flex items-center justify-center rounded-lg text-slate-400 text-xs font-medium uppercase"
                    style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); letter-spacing: 0.2em;"
                >Bühne</div>
                @endunless

                {{-- Tische (normalisierte Koordinaten -> identisch zum Editor) --}}
                @foreach ($tableStates as $info)
                    @php
                        $table = $info['table'];
                        $state = $info['state'];
                        $remaining = $info['remaining'] ?? null;

                        [$bgColor, $ringColor, $glow] = match ($state) {
                            'selected' => ['#6366f1', '#312e81', '0 0 0 3px #fbbf24'],
                            'free'     => ['#22c55e', '#15803d', '0 0 12px rgba(34,197,94,0.35)'],
                            'partial'  => ['#f59e0b', '#b45309', '0 0 12px rgba(245,158,11,0.35)'],
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
                            {{ $table->surfaceStyle() }}
                            background: {{ $bgColor }};
                            border-radius: {{ $radius }};
                            box-shadow:
                                inset 0 0 0 1px {{ $ringColor }},
                                {{ $glow }};
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
    @endif
</div>
