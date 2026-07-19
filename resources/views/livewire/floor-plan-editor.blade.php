<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="'Tischplan – ' . $this->venue->name" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Venues & Tischpläne', 'href' => route('reservation.venues.index')],
            ['label' => 'Tischplan bearbeiten'],
        ]" />
    </x-slot>

    <x-ui-page-container>
    <div class="pt-4 space-y-4">

    {{-- Tischplan Name --}}
    <div class="flex items-center gap-2"
        x-data="{ saved: false }"
        x-on:floor-plan-saved.window="saved = true; clearTimeout($refs.t); $refs.t = setTimeout(() => saved = false, 2500)">
        <input
            type="text"
            wire:model="floorPlanName"
            placeholder="Name des Tischplans"
            class="flex-1 rounded-md border border-[var(--ui-border)] px-3 py-2 text-sm text-[var(--ui-secondary)]"
        />
        <x-ui-button variant="primary" size="sm" wire:click="saveFloorPlan" wire:loading.attr="disabled" wire:target="saveFloorPlan">
            <span wire:loading.remove wire:target="saveFloorPlan" class="flex items-center gap-1">
                @svg('heroicon-o-check', 'w-4 h-4')
                <span>Speichern</span>
            </span>
            <span wire:loading wire:target="saveFloorPlan">Speichert…</span>
        </x-ui-button>
        <span x-show="saved" x-cloak x-transition
            class="flex items-center gap-1 rounded-md bg-[var(--ui-success-10)] px-2.5 py-1.5 text-sm font-medium text-[var(--ui-success)]">
            @svg('heroicon-o-check-circle', 'w-4 h-4')
            Gespeichert
        </span>
        @error('floorPlanName')
            <span class="text-xs text-[var(--ui-danger)]">{{ $message }}</span>
        @enderror
    </div>

    @if ($floorPlanId)
        {{-- Grundriss-Upload --}}
        <div class="rounded-lg border border-[var(--ui-border)]/60 p-3 space-y-2">
            <div class="flex items-center gap-2">
                @svg('heroicon-o-map', 'w-5 h-5 text-[var(--ui-muted)]')
                <span class="text-sm font-medium text-[var(--ui-secondary)]">Grundriss (Hintergrundbild)</span>
                @if ($this->floorPlan?->background_context_file_id)
                    <div class="ml-auto flex items-center gap-2">
                        <span class="text-xs text-[var(--ui-muted)]">{{ (int) $this->floorPlan->background_rotation }}°</span>
                        <button wire:click="rotateBackground(-90)" type="button" title="Nach links drehen"
                            class="inline-flex items-center justify-center rounded-md border border-[var(--ui-border)] p-1.5 text-[var(--ui-secondary)] hover:bg-gray-50">
                            @svg('heroicon-o-arrow-uturn-left', 'w-4 h-4')
                        </button>
                        <button wire:click="rotateBackground(90)" type="button" title="Nach rechts drehen"
                            class="inline-flex items-center justify-center rounded-md border border-[var(--ui-border)] p-1.5 text-[var(--ui-secondary)] hover:bg-gray-50">
                            @svg('heroicon-o-arrow-uturn-right', 'w-4 h-4')
                        </button>
                        <button wire:click="removeBackground" wire:confirm="Grundriss entfernen?" type="button"
                            class="text-xs text-[var(--ui-danger)] hover:underline">Grundriss entfernen</button>
                    </div>
                @endif
            </div>
            @include('reservation::partials.image-upload', [
                'model' => 'background',
                'hint'  => 'JPG, PNG oder WebP · max. 20 MB. Die Tische liegen darüber.',
            ])
        </div>

        {{-- Atmosphäre-Bilder (Galerie, beliebig viele) --}}
        @if ($this->floorPlan)
            @php $atmosphere = $this->floorPlan->atmosphereImages(); @endphp
            <div class="rounded-lg border border-[var(--ui-border)]/60 p-3 space-y-2">
                <div class="flex items-center gap-2">
                    @svg('heroicon-o-photo', 'w-5 h-5 text-[var(--ui-muted)]')
                    <span class="text-sm font-medium text-[var(--ui-secondary)]">Atmosphäre-Bilder</span>
                    <span class="ml-auto text-xs text-[var(--ui-muted)]">erscheinen in der Gast-Ansicht / App ({{ count($atmosphere) }})</span>
                </div>

                @if (count($atmosphere))
                    <div class="grid grid-cols-3 gap-2 sm:grid-cols-5">
                        @foreach ($atmosphere as $img)
                            <div wire:key="atmo-{{ $img['id'] }}" class="relative aspect-square overflow-hidden rounded-lg border border-[var(--ui-border)]/50">
                                <img src="{{ $img['thumbnail'] }}" alt="" class="h-full w-full object-cover" />
                                <button wire:click="removeAtmosphereImage({{ $img['id'] }})" wire:confirm="Bild entfernen?" type="button"
                                    class="absolute right-1 top-1 rounded-full bg-black/60 px-1.5 text-xs leading-5 text-white hover:bg-black/80">✕</button>
                            </div>
                        @endforeach
                    </div>
                @endif

                <label class="block">
                    <input type="file" wire:model="atmosphereUploads" multiple accept="image/*"
                        class="block w-full text-sm text-[var(--ui-secondary)] file:mr-3 file:rounded-md file:border file:border-[var(--ui-border)] file:bg-gray-50 file:px-3 file:py-1.5 file:text-sm hover:file:bg-gray-100" />
                    <span class="mt-1 block text-[11px] text-[var(--ui-muted)]">Mehrere möglich · JPG, PNG oder WebP · max. 20 MB je Bild.</span>
                </label>
                <div wire:loading wire:target="atmosphereUploads" class="text-xs text-[var(--ui-muted)]">Lade hoch …</div>
                @error('atmosphereUploads.*') <p class="text-xs text-[var(--ui-danger)] m-0">{{ $message }}</p> @enderror
                @error('atmosphereUploads') <p class="text-xs text-[var(--ui-danger)] m-0">{{ $message }}</p> @enderror
            </div>
        @endif

        {{-- Canvas: Tischplan – Seitenverhältnis folgt dem Grundriss (kein Letterbox);
             Tische in normalisierten Koordinaten -> identisch zur Gast-Ansicht. --}}
        <div class="mx-auto w-full max-w-3xl">
            <div
                id="floor-plan-canvas"
                class="relative w-full overflow-hidden rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-900"
                style="aspect-ratio: {{ $this->floorPlan?->displayAspect() ?? (4 / 3) }};"
                x-data="floorPlanEditor()"
            >
                @if ($this->floorPlan?->backgroundUrl())
                    @php $rot = (int) ($this->floorPlan->background_rotation ?? 0); @endphp
                    {{-- Grundriss-Layer (rotierbar); Tische liegen darüber --}}
                    <img
                        wire:key="bg-{{ $rot }}"
                        src="{{ $this->floorPlan->backgroundUrl() }}"
                        alt="Grundriss"
                        x-data="rotatableBg({{ $rot }})"
                        :style="style"
                    />
                @endif

                @foreach ($this->tables as $table)
                    <div
                        wire:key="table-{{ $table->id }}"
                        class="group absolute flex cursor-move select-none items-center justify-center text-xs font-bold text-white shadow-md transition"
                        style="
                            {{ $table->surfaceStyle() }}
                            background-color: {{ $table->color ?? '#4F46E5' }};
                            border-radius: {{ $table->shape === 'round' ? '50%' : '8px' }};
                        "
                        x-on:dblclick="$wire.openTableForm({{ $table->id }})"
                        x-data="draggable({{ $table->id }}, {{ $table->x_pct }}, {{ $table->y_pct }}, {{ $table->w_pct }}, {{ $table->h_pct }}, {{ $table->shape === 'round' ? 'true' : 'false' }})"
                    >
                        <div class="pointer-events-none text-center leading-tight">
                            <div>{{ $table->label }}</div>
                            <div class="opacity-75">{{ $table->capacity }}P</div>
                        </div>

                        {{-- Resize-Griff (unten rechts) --}}
                        <div
                            data-resize-handle
                            title="Größe ändern"
                            class="absolute -bottom-1 -right-1 h-3.5 w-3.5 cursor-se-resize rounded-full bg-white opacity-0 shadow ring-2 ring-indigo-500 transition group-hover:opacity-100"
                            x-on:mousedown.stop.prevent="startResize($event.clientX, $event.clientY)"
                            x-on:touchstart.stop.prevent="startResize($event.touches[0].clientX, $event.touches[0].clientY)"
                        ></div>
                    </div>
                @endforeach

                {{-- Neuen Tisch hinzufügen --}}
                <button
                    wire:click="openTableForm()"
                    class="absolute bottom-4 right-4 flex items-center gap-1 rounded-full bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow hover:bg-indigo-700"
                >
                    + Tisch
                </button>
            </div>
            <p class="mt-2 text-center text-xs text-[var(--ui-muted)]">Tische ziehen zum Positionieren · Ecke ziehen zum Skalieren · Doppelklick zum Bearbeiten</p>
        </div>

        {{-- Tisch-Formular Modal --}}
        @if ($showTableForm)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900">
                    <h2 class="mb-4 text-lg font-semibold dark:text-white">
                        {{ $editingTableId ? 'Tisch bearbeiten' : 'Neuer Tisch' }}
                    </h2>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm text-gray-700 dark:text-gray-300">Label</label>
                            <input wire:model="tableLabel" type="text"
                                class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                            @error('tableLabel') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm text-gray-700 dark:text-gray-300">Kapazität</label>
                                <input wire:model="tableCapacity" type="number" min="1" max="50"
                                    class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                            </div>
                            <div>
                                <label class="block text-sm text-gray-700 dark:text-gray-300">Form</label>
                                <select wire:model="tableShape"
                                    class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                    <option value="square">Eckig</option>
                                    <option value="rectangle">Rechteck</option>
                                    <option value="round">Rund</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm text-gray-700 dark:text-gray-300">Farbe</label>
                            <input wire:model="tableColor" type="color"
                                class="mt-1 h-10 w-full rounded-md border" />
                        </div>
                    </div>

                    <div class="mt-6 flex justify-between">
                        @if ($editingTableId)
                            <button
                                wire:click="deleteTable({{ $editingTableId }})"
                                wire:confirm="Tisch wirklich löschen?"
                                class="rounded-md bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700"
                            >Löschen</button>
                        @else
                            <div></div>
                        @endif

                        <div class="flex gap-2">
                            <button
                                wire:click="$set('showTableForm', false)"
                                class="rounded-md border px-4 py-2 text-sm dark:border-gray-700 dark:text-white"
                            >Abbrechen</button>
                            <button
                                wire:click="saveTable"
                                class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700"
                            >Speichern</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @else
        <p class="text-sm text-gray-500">Speichere zuerst den Tischplan, um Tische hinzuzufügen.</p>
    @endif

    </div>
    </x-ui-page-container>
</x-ui-page>

@script
<script>
Alpine.data('floorPlanEditor', () => ({
    init() {}
}));

// Grundriss-Bild als rotierbarer Layer: passt sich bei 90°/270° an (Breite/Höhe getauscht),
// zentriert, object-contain – füllt den Canvas wie ein CSS-Background.
Alpine.data('rotatableBg', (rotation) => ({
    rot: ((rotation % 360) + 360) % 360,
    w: 0,
    h: 0,
    init() {
        const parent = this.$el.parentElement;
        const fit = () => {
            const cw = parent.clientWidth, ch = parent.clientHeight;
            if (this.rot % 180 === 0) { this.w = cw; this.h = ch; }
            else { this.w = ch; this.h = cw; }
        };
        fit();
        this._ro = new ResizeObserver(fit);
        this._ro.observe(parent);
    },
    destroy() {
        if (this._ro) this._ro.disconnect();
    },
    get style() {
        return `position:absolute; left:50%; top:50%; width:${this.w}px; height:${this.h}px;`
            + `object-fit:contain; transform:translate(-50%,-50%) rotate(${this.rot}deg);`
            + `pointer-events:none; user-select:none;`;
    },
}));

// Tisch verschieben/skalieren in NORMALISIERTEN Koordinaten (0…1).
// x/y = Mittelpunkt (Anteil der Flächenbreite/-höhe), w/h = Größe (Anteil).
// Deltas werden über die aktuelle Canvas-Pixelgröße in Anteile umgerechnet –
// dadurch stimmen die Positionen unabhängig von Bildschirm/Zoom.
Alpine.data('draggable', (tableId, initialX, initialY, initialW, initialH, uniform = false) => ({
    tableId,
    x: initialX, y: initialY,   // Mittelpunkt (0…1)
    w: initialW, h: initialH,   // Größe (Anteil 0…1)
    uniform,                    // runde Tische: pixel-quadratisch skalieren (Kreis bleibt Kreis)
    mode: null,                 // null | 'move' | 'resize'
    sx: 0, sy: 0,               // Start-Mausposition (px)
    ox: 0, oy: 0, ow: 0, oh: 0, // Start x/y/w/h (Anteile)

    init() {
        const el     = this.$el;
        const canvas = () => document.getElementById('floor-plan-canvas');
        const getRect = () => { const c = canvas(); return c ? c.getBoundingClientRect() : { width: 1, height: 1 }; };

        const apply = () => {
            el.style.left   = ((this.x - this.w / 2) * 100) + '%';
            el.style.top    = ((this.y - this.h / 2) * 100) + '%';
            el.style.width  = (this.w * 100) + '%';
            el.style.height = (this.h * 100) + '%';
        };

        const onMove = (cx, cy) => {
            const r = getRect();
            const dxp = (cx - this.sx) / r.width;
            const dyp = (cy - this.sy) / r.height;
            if (this.mode === 'move') {
                this.x = Math.min(1, Math.max(0, this.ox + dxp));
                this.y = Math.min(1, Math.max(0, this.oy + dyp));
            } else if (this.mode === 'resize') {
                if (this.uniform) {
                    const dPx = cx - this.sx; // gleicher Pixel-Zuwachs auf beiden Achsen
                    this.w = Math.min(1, Math.max(0.03, this.ow + dPx / r.width));
                    this.h = Math.min(1, Math.max(0.03, this.oh + dPx / r.height));
                } else {
                    this.w = Math.min(1, Math.max(0.03, this.ow + dxp));
                    this.h = Math.min(1, Math.max(0.03, this.oh + dyp));
                }
            }
            apply();
        };
        const onEnd = () => {
            if (!this.mode) return;
            const m = this.mode; this.mode = null;
            if (m === 'move') {
                this.$wire.updateTablePosition(this.tableId, this.x, this.y);
            } else {
                this.$wire.updateTableSize(this.tableId, this.w, this.h);
            }
        };

        // Verschieben (Klick auf den Tisch selbst, nicht auf den Resize-Griff)
        el.addEventListener('mousedown', (e) => {
            if (e.detail > 1 || e.button !== 0) return;
            if (e.target.dataset.resizeHandle !== undefined) return;
            this.mode = 'move';
            this.sx = e.clientX; this.sy = e.clientY;
            this.ox = this.x; this.oy = this.y;
            e.preventDefault();
        });
        el.addEventListener('touchstart', (e) => {
            if (e.touches.length !== 1) return;
            if (e.target.dataset.resizeHandle !== undefined) return;
            const t = e.touches[0];
            this.mode = 'move';
            this.sx = t.clientX; this.sy = t.clientY;
            this.ox = this.x; this.oy = this.y;
        }, { passive: true });

        document.addEventListener('mousemove', (e) => { if (this.mode) onMove(e.clientX, e.clientY); });
        document.addEventListener('mouseup', onEnd);
        document.addEventListener('touchmove', (e) => { if (this.mode) { e.preventDefault(); onMove(e.touches[0].clientX, e.touches[0].clientY); } }, { passive: false });
        document.addEventListener('touchend', onEnd);
    },

    // Vom Resize-Griff aufgerufen
    startResize(cx, cy) {
        this.mode = 'resize';
        this.sx = cx; this.sy = cy;
        this.ow = this.w; this.oh = this.h;
    },
}));
</script>
@endscript
