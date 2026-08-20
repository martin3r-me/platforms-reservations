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
    {{-- Timer im x-data, nicht in $refs: $refs kennt nur Elemente mit x-ref,
         ein x-ref="t" gibt es hier nicht. Die Zuweisung warf deshalb
         "Cannot convert undefined or null to object" in der Konsole. --}}
    <div class="flex items-center gap-2"
        x-data="{ saved: false, timer: null }"
        x-on:floor-plan-saved.window="saved = true; clearTimeout(timer); timer = setTimeout(() => saved = false, 2500)">
        {{-- Speichert automatisch wie der Rest der Seite; nur ein noch nicht
             angelegter Plan braucht den Knopf (siehe updatedFloorPlanName()). --}}
        <input
            type="text"
            wire:model.live.debounce.600ms="floorPlanName"
            placeholder="Name des Tischplans"
            class="flex-1 rounded-md border border-[var(--ui-border)] px-3 py-2 text-sm text-[var(--ui-secondary)]"
        />

        @if (! $floorPlanId)
            <x-ui-button variant="primary" size="sm" wire:click="saveFloorPlan" wire:loading.attr="disabled" wire:target="saveFloorPlan">
                <span wire:loading.remove wire:target="saveFloorPlan" class="flex items-center gap-1">
                    @svg('heroicon-o-check', 'w-4 h-4')
                    <span>Tischplan anlegen</span>
                </span>
                <span wire:loading wire:target="saveFloorPlan">Legt an…</span>
            </x-ui-button>
        @endif

        <span wire:loading wire:target="floorPlanName" class="text-xs text-[var(--ui-muted)]">Speichert…</span>

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
                        {{-- Herunterladen: Der Grundriss liegt sonst nur hier. Öffnet in
                             einem neuen Tab, weil die Datei über eine signierte URL
                             mit kurzer Gültigkeit ausgeliefert wird. --}}
                        @if ($url = $this->floorPlan?->backgroundDownloadUrl())
                            <a href="{{ $url }}" target="_blank" rel="noopener" title="Grundriss herunterladen"
                                class="inline-flex items-center justify-center rounded-md border border-[var(--ui-border)] p-1.5 text-[var(--ui-secondary)] hover:bg-gray-50">
                                @svg('heroicon-o-arrow-down-tray', 'w-4 h-4')
                            </a>
                        @endif
                        <button wire:click="removeBackground" wire:confirm="Grundriss entfernen?" type="button"
                            class="inline-flex items-center gap-1.5 rounded-md border border-[var(--ui-border)] bg-white px-3 py-1.5 text-sm font-medium text-[var(--ui-danger)] transition-colors hover:border-[var(--ui-danger)] hover:bg-red-50 dark:bg-gray-900 dark:hover:bg-red-950/30">
                            @svg('heroicon-o-trash', 'w-4 h-4')
                            Grundriss entfernen
                        </button>
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

                @include('reservation::partials.image-upload', [
                    'model'    => 'atmosphereUploads',
                    'multiple' => true,
                    'hint'     => 'Mehrere möglich · JPG, PNG oder WebP · max. 20 MB je Bild.',
                ])
            </div>
        @endif

        {{-- Canvas: Tischplan – Seitenverhältnis folgt dem Grundriss (kein Letterbox);
             Tische in normalisierten Koordinaten -> identisch zur Gast-Ansicht. --}}
        <div class="mx-auto w-full max-w-3xl">
            {{-- Zoom über die BREITE des Canvas in einem Scroll-Container, nicht
                 per transform: scale(). Dadurch liefert getBoundingClientRect()
                 weiter echte Pixel und Ziehen/Skalieren rechnen unverändert
                 richtig – mit scale() müsste jede Pixelrechnung den Faktor
                 herausteilen. --}}
            <div class="relative" x-data="{ zoom: 100 }">
            <div class="overflow-auto rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-900">
            <div
                id="floor-plan-canvas"
                class="relative"
                style="aspect-ratio: {{ $this->floorPlan?->displayAspect() ?? (4 / 3) }};"
                :style="{ width: zoom + '%' }"
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

                @php
                    // Höhe folgt der Breite; die Regel liegt am FloorPlan, damit
                    // Editor, Ansicht und MCP-Tools dieselbe benutzen.
                    $plan = $this->floorPlan;
                @endphp

                {{-- RAUMUMRISS (Versuchsstand) – eigene Ebene, fasst die Tische nicht an.
                     Zum Entfernen: diesen Block, den Alpine-Abschnitt unten und
                     die Datei partials/floor-plan-room-layer.blade.php loeschen. --}}
                @php $roomPaths = $this->roomPaths; @endphp
                <div
                    wire:key="room-layer-{{ md5(json_encode($roomPaths)) }}"
                    class="absolute inset-0"
                    style="pointer-events: none;"
                    x-data="roomDraw(@js($roomPaths))"
                >
                    @include('reservation::partials.floor-plan-room-layer')
                </div>

                @foreach ($this->tables as $table)
                    <div
                        wire:key="table-{{ $table->id }}"
                        {{-- data-table: Marker, über den ein gezogener Tisch die
                             Mitten der anderen als Einrast-Ziele findet. --}}
                        data-table
                        {{-- touch-none: sonst scrollt die Seite beim Ziehen auf dem Tablet.
                             Kein "transition" hier: die Klasse animiert transform, und
                             transform gehört beim Ziehen der Geste (siehe paint()). --}}
                        class="group absolute flex touch-none cursor-move select-none items-center justify-center text-xs font-bold text-white"
                        style="{{ $table->surfaceStyle() }}"
                        x-on:dblclick="$wire.openTableForm({{ $table->id }})"
                        x-data="draggable({{ $table->id }}, {{ $table->x_pct }}, {{ $table->y_pct }}, {{ $table->w_pct }}, {{ $table->h_pct }}, {{ $plan ? $plan->heightFactor($table->shape) : (4 / 3) }})"
                    >
                        {{-- Die Fläche liegt in einer eigenen Ebene und trägt die
                             Drehung. Zwei Gründe: der transform des äußeren
                             Elements ist fürs Ziehen reserviert, und das Label
                             bleibt aufrecht statt mitzukippen. --}}
                        <div
                            class="pointer-events-none absolute inset-0 bg-indigo-600 shadow-md ring-2 ring-white/70 transition-colors group-hover:bg-indigo-500 dark:ring-gray-900/70"
                            style="
                                border-radius: {{ $table->shape === 'round' ? '50%' : '8px' }};
                                @if ((int) $table->rotation !== 0) transform: rotate({{ (int) $table->rotation }}deg); @endif
                            "
                        ></div>

                        <div class="pointer-events-none relative text-center leading-tight">
                            <div>{{ $table->label }}</div>
                            <div class="opacity-75">{{ $table->capacity }}P</div>
                        </div>

                        {{-- Kapazitäts-Badge oben rechts, wie in der Shop-Ansicht --}}
                        <span class="pointer-events-none absolute -right-1.5 -top-1.5 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-white px-1 text-[9px] font-semibold text-indigo-700 ring-1 ring-indigo-200">
                            {{ $table->capacity }}
                        </span>

                        {{-- Dreh-Leiste über dem Tisch: je ein Pfeil pro Richtung mit
                             dem aktuellen Winkel dazwischen, direkt im Plan klickbar.
                             dblclick.stop ist wesentlich – zweimal schnell klicken
                             ergibt 90°, und ohne das würde der Doppelklick am Tisch
                             landen und das Formular öffnen. --}}
                        @if ($table->shape !== 'round')
                            <div
                                data-rotate-handle
                                x-on:pointerdown.stop
                                x-on:dblclick.stop
                                class="absolute -top-9 left-1/2 flex -translate-x-1/2 touch-none items-center gap-0.5 rounded-lg border border-[var(--ui-border)] bg-white px-1 py-0.5 opacity-90 shadow-sm transition hover:opacity-100 dark:bg-gray-900"
                            >
                                <button
                                    type="button"
                                    title="45° nach links"
                                    wire:click="rotateTable({{ $table->id }}, -45)"
                                    class="inline-flex h-5 w-5 items-center justify-center rounded text-[var(--ui-secondary)] hover:bg-gray-100 dark:hover:bg-gray-800"
                                >
                                    @svg('heroicon-o-arrow-uturn-left', 'w-3.5 h-3.5')
                                </button>

                                <span class="min-w-[2rem] text-center text-[10px] font-medium tabular-nums text-[var(--ui-secondary)]">{{ (int) $table->rotation }}°</span>

                                <button
                                    type="button"
                                    title="45° nach rechts"
                                    wire:click="rotateTable({{ $table->id }}, 45)"
                                    class="inline-flex h-5 w-5 items-center justify-center rounded text-[var(--ui-secondary)] hover:bg-gray-100 dark:hover:bg-gray-800"
                                >
                                    @svg('heroicon-o-arrow-uturn-right', 'w-3.5 h-3.5')
                                </button>
                            </div>
                        @endif

                        {{-- Resize-Griff: dauerhaft sichtbar und deutlich größer als
                             vorher (14px, nur bei Hover) – sonst kaum zu treffen. --}}
                        <div
                            data-resize-handle
                            title="Größe ändern (oder im Formular eintragen)"
                            class="absolute -bottom-2 -right-2 h-5 w-5 touch-none cursor-se-resize rounded-full border-2 border-indigo-600 bg-white opacity-80 shadow transition hover:scale-110 hover:opacity-100"
                            x-on:pointerdown.stop="startResize($event)"
                        ></div>
                    </div>
                @endforeach

                {{-- Hilfslinien beim Ausrichten. Liegen nach den Tischen im DOM,
                     damit sie darüber sichtbar sind; gesteuert von draggable(). --}}
                {{-- Ausrichtungslinien: rot gestrichelt wie in PowerPoint --}}
                <div id="fp-guide-v" class="pointer-events-none absolute top-0 h-full border-l border-dashed border-red-500" style="display: none;"></div>
                <div id="fp-guide-h" class="pointer-events-none absolute left-0 w-full border-t border-dashed border-red-500" style="display: none;"></div>

                {{-- Gleiche Abstände: gestrichelte Strecke mit Doppelpfeil in
                     jeder der beiden Lücken, auf Höhe des gezogenen Tischs.
                     Pfeilspitzen sind CSS-Dreiecke (Rahmen-Trick); JS setzt nur
                     Position und Länge des Containers, das Innere macht Flex. --}}
                @foreach (['fp-gap-x1', 'fp-gap-x2'] as $gapId)
                    <div id="{{ $gapId }}" class="pointer-events-none absolute flex -translate-y-1/2 items-center" style="display: none;">
                        <span class="h-0 w-0 border-y-[3px] border-r-[5px] border-y-transparent border-r-red-500"></span>
                        <span class="h-0 flex-1 border-t border-dashed border-red-500"></span>
                        <span class="h-0 w-0 border-y-[3px] border-l-[5px] border-y-transparent border-l-red-500"></span>
                    </div>
                @endforeach

                @foreach (['fp-gap-y1', 'fp-gap-y2'] as $gapId)
                    <div id="{{ $gapId }}" class="pointer-events-none absolute flex -translate-x-1/2 flex-col items-center" style="display: none;">
                        <span class="h-0 w-0 border-x-[3px] border-b-[5px] border-x-transparent border-b-red-500"></span>
                        <span class="w-0 flex-1 border-l border-dashed border-red-500"></span>
                        <span class="h-0 w-0 border-x-[3px] border-t-[5px] border-x-transparent border-t-red-500"></span>
                    </div>
                @endforeach
            </div>{{-- /Canvas --}}
            </div>{{-- /Scroll-Container --}}

            {{-- Bedienelemente liegen AUSSERHALB des Scroll-Containers, damit sie
                 beim Hineinzoomen nicht mit dem Inhalt wegscrollen. --}}
            <div class="pointer-events-none absolute inset-0" style="z-index: 30;">
                <div class="pointer-events-auto absolute bottom-3 left-3 flex items-center gap-2">
                {{-- Zoom. Mitte zeigt den Wert und setzt zurück. --}}
                <div class="flex items-center gap-0.5 rounded-lg border border-[var(--ui-border)] bg-white px-1 py-0.5 shadow-sm dark:bg-gray-900">
                    <button
                        type="button"
                        title="Verkleinern"
                        x-on:click="zoom = Math.max(50, zoom - 25)"
                        :disabled="zoom <= 50"
                        class="inline-flex h-6 w-6 items-center justify-center rounded text-[var(--ui-secondary)] hover:bg-gray-100 disabled:opacity-40 dark:hover:bg-gray-800"
                    >
                        @svg('heroicon-o-minus', 'w-4 h-4')
                    </button>

                    <button
                        type="button"
                        title="Auf 100 % zurücksetzen"
                        x-on:click="zoom = 100"
                        class="min-w-[3rem] rounded px-1 text-center text-[11px] font-medium tabular-nums text-[var(--ui-secondary)] hover:bg-gray-100 dark:hover:bg-gray-800"
                        x-text="zoom + '%'"
                    ></button>

                    <button
                        type="button"
                        title="Vergrößern"
                        x-on:click="zoom = Math.min(300, zoom + 25)"
                        :disabled="zoom >= 300"
                        class="inline-flex h-6 w-6 items-center justify-center rounded text-[var(--ui-secondary)] hover:bg-gray-100 disabled:opacity-40 dark:hover:bg-gray-800"
                    >
                        @svg('heroicon-o-plus', 'w-4 h-4')
                    </button>
                </div>

                {{-- Einrasten an/aus: gefüllt = an, blass = frei platzieren --}}
                <button
                    type="button"
                    x-on:click="$store.fpSnap.toggle()"
                    :title="$store.fpSnap.on ? 'Einrasten ausschalten – frei platzieren' : 'Einrasten einschalten'"
                    :class="$store.fpSnap.on
                        ? 'border-indigo-600 bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300'
                        : 'border-[var(--ui-border)] bg-white text-[var(--ui-muted)] dark:bg-gray-900'"
                    class="inline-flex h-7 items-center gap-1.5 rounded-lg border px-2 text-[11px] font-medium shadow-sm transition-colors"
                >
                    @svg('heroicon-o-squares-2x2', 'w-3.5 h-3.5')
                    <span x-text="$store.fpSnap.on ? 'Einrasten' : 'Frei'"></span>
                </button>

                {{-- RAUMUMRISS (Versuchsstand) – Auszeichnung wie beim Einrasten daneben --}}
                <button
                    type="button"
                    wire:click="toggleRoomMode()"
                    @class([
                        'inline-flex h-7 items-center gap-1.5 rounded-lg border px-2 text-[11px] font-medium shadow-sm transition-colors',
                        'border-indigo-600 bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300' => $roomMode,
                        'border-[var(--ui-border)] bg-white text-[var(--ui-muted)] dark:bg-gray-900' => ! $roomMode,
                    ])
                    title="{{ $roomMode ? 'Zeichnen beenden' : 'Wände über dem Grundriss nachzeichnen' }}"
                >
                    @svg('heroicon-o-pencil', 'w-3.5 h-3.5')
                    <span>{{ $roomMode ? 'Zeichnen' : 'Raum' }}</span>
                </button>

                {{-- Grundriss ein-/ausblenden: zeigt, ob der Umriss allein schon trägt.
                     Nur sinnvoll, wenn überhaupt ein Bild hinterlegt ist. --}}
                @if ($this->floorPlan?->backgroundUrl())
                    <button
                        type="button"
                        x-on:click="$store.fpBg.toggle()"
                        :title="$store.fpBg.on ? 'Grundriss ausblenden' : 'Grundriss einblenden'"
                        :class="$store.fpBg.on
                            ? 'border-[var(--ui-border)] bg-white text-[var(--ui-muted)] dark:bg-gray-900'
                            : 'border-indigo-600 bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300'"
                        class="inline-flex h-7 w-7 items-center justify-center rounded-lg border shadow-sm transition-colors"
                    >
                        <span x-show="$store.fpBg.on">@svg('heroicon-o-eye', 'w-3.5 h-3.5')</span>
                        <span x-show="! $store.fpBg.on" style="display: none;">@svg('heroicon-o-eye-slash', 'w-3.5 h-3.5')</span>
                    </button>
                @endif
                </div>

                {{-- Neuen Tisch hinzufügen: unten rechts, bleibt beim Zoomen sichtbar --}}
                <button
                    wire:click="openTableForm()"
                    class="pointer-events-auto absolute bottom-3 right-3 flex items-center gap-1 rounded-full bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow hover:bg-indigo-700"
                >
                    + Tisch
                </button>
            </div>
            </div>{{-- /Rahmen --}}
    @if ($roomMode)
        {{-- RAUMUMRISS (Versuchsstand) --}}
        <p class="mt-2 text-center text-xs text-[var(--ui-muted)]">
            Von Ecke zu Ecke ziehen zeichnet eine Wand · an einem <strong>gefüllten Ende</strong> geht es weiter ·
            neue Wände richten sich an vorhandenen aus (mit „Einrasten") · <strong>Shift</strong> für schräge Wände<br>
            Hohle Punkte lassen sich verschieben, Alt-Klick löscht · Griff in der Mitte verschiebt den Zug,
            Rechtsklick darauf löscht ihn
            <button type="button" wire:click="clearRoomPaths()"
                wire:confirm="Alle gezeichneten Linien löschen?"
                class="ml-2 underline">alles löschen</button>
        </p>
    @else
        <p class="mt-2 text-center text-xs text-[var(--ui-muted)]">
                Tische ziehen zum Positionieren · Ecke ziehen oder Größe im Formular eintragen ·
                Doppelklick zum Bearbeiten, Duplizieren und Übertragen auf alle
            </p>
    @endif
        </div>

        {{-- Tisch-Formular: Plattform-Modal statt handgebautes Overlay --}}
        <x-ui-modal size="md" wire:model="showTableForm" :backdropClosable="true" :escClosable="true">
            <x-slot name="header">
                <div class="flex items-center gap-3">
                    <div class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-600/10">
                        @svg('heroicon-o-squares-2x2', 'w-5 h-5 text-indigo-600')
                    </div>
                    <div class="min-w-0">
                        <h3 class="m-0 text-base font-semibold leading-tight text-[var(--ui-secondary)]">
                            {{ $editingTableId ? 'Tisch bearbeiten' : 'Neuer Tisch' }}
                        </h3>
                        <p class="m-0 mt-0.5 text-[12px] text-[var(--ui-muted)]">
                            Bezeichnung, Kapazität, Form und Größe
                        </p>
                    </div>
                </div>
            </x-slot>

            <div class="space-y-4">
                <x-ui-input-text name="tableLabel" label="Label" wire:model="tableLabel" />

                <div class="grid grid-cols-2 gap-3">
                    <x-ui-input-number name="tableCapacity" label="Kapazität" wire:model="tableCapacity" min="1" max="50" />

                    <x-ui-input-select
                        name="tableShape"
                        label="Form"
                        :options="[
                            ['value' => 'square', 'label' => 'Eckig'],
                            ['value' => 'rectangle', 'label' => 'Rechteck'],
                            ['value' => 'round', 'label' => 'Rund'],
                        ]"
                        wire:model.live="tableShape"
                    />
                </div>

                {{-- Größe: Slider und Zahlenfeld auf denselben Wert; die Höhe
                     folgt automatisch aus Form und Seitenverhältnis. --}}
                <div class="rounded-xl border border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)]/40 p-3">
                    <div class="flex items-baseline justify-between">
                        <h4 class="m-0 text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)]">Größe</h4>
                        <span class="text-[12px] tabular-nums text-[var(--ui-muted)]">{{ $tableSizePct }} % der Planbreite</span>
                    </div>

                    <div class="mt-2 flex items-center gap-3">
                        <input
                            wire:model.live.debounce.250ms="tableSizePct"
                            type="range" min="2" max="40" step="1"
                            class="h-1.5 flex-1 cursor-pointer accent-indigo-600"
                        />
                        <div class="w-20 shrink-0">
                            <x-ui-input-number name="tableSizePct" label="" wire:model.live.debounce.250ms="tableSizePct" min="2" max="40" />
                        </div>
                    </div>
                    @error('tableSizePct') <p class="mt-1 text-xs text-[var(--ui-danger)]">{{ $message }}</p> @enderror

                    <div class="mt-3 flex items-center justify-between gap-3">
                        <p class="m-0 text-[12px] text-[var(--ui-muted)]">
                            Höhe wird passend zur Form berechnet.
                        </p>
                        <div class="shrink-0">
                            <x-ui-button
                                variant="secondary-outline"
                                size="xs"
                                wire:click="applySizeToAll"
                                wire:confirm="Diese Größe auf alle Tische übertragen?"
                            >Auf alle Tische</x-ui-button>
                        </div>
                    </div>

                    {{-- Ausrichtung: runde Tische sehen gedreht gleich aus. --}}
                    @if ($tableShape !== 'round')
                        <div class="mt-3 flex items-center justify-between gap-3 border-t border-[var(--ui-border)]/40 pt-3">
                            <div class="min-w-0">
                                <h4 class="m-0 text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)]">Ausrichtung</h4>
                                <p class="m-0 mt-0.5 text-[12px] text-[var(--ui-muted)]">90° stellt quer, 45° diagonal.</p>
                            </div>

                            <div class="flex shrink-0 items-center gap-1">
                                <button wire:click="rotateTableBy(-45)" type="button" title="45° nach links"
                                    class="inline-flex items-center justify-center rounded-md border border-[var(--ui-border)] p-1.5 text-[var(--ui-secondary)] hover:bg-gray-50">
                                    @svg('heroicon-o-arrow-uturn-left', 'w-4 h-4')
                                </button>

                                <span class="w-12 text-center text-sm tabular-nums text-[var(--ui-secondary)]">{{ $tableRotation }}°</span>

                                <button wire:click="rotateTableBy(45)" type="button" title="45° nach rechts"
                                    class="inline-flex items-center justify-center rounded-md border border-[var(--ui-border)] p-1.5 text-[var(--ui-secondary)] hover:bg-gray-50">
                                    @svg('heroicon-o-arrow-uturn-right', 'w-4 h-4')
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <x-slot name="footer">
                <div class="flex items-center justify-between gap-2">
                    @if ($editingTableId)
                        <div class="shrink-0">
                            {{-- action= wird als Methodenname aufgerufen, nicht als
                                 wire:click-Ausdruck – Argumente in Klammern landen
                                 als Teil des Namens beim Server. Daher parameterlos. --}}
                            <x-ui-confirm-button
                                action="deleteTableAndCloseModal"
                                text="Löschen"
                                confirmText="Wirklich löschen?"
                                variant="danger-outline"
                                size="sm"
                            />
                        </div>
                    @else
                        <div></div>
                    @endif

                    <div class="flex shrink-0 items-center gap-2">
                        @if ($editingTableId)
                            <x-ui-button
                                variant="secondary-outline"
                                size="sm"
                                wire:click="duplicateTable"
                            >Duplizieren</x-ui-button>
                        @endif

                        <x-ui-button variant="secondary-outline" size="sm" wire:click="$set('showTableForm', false)">
                            Abbrechen
                        </x-ui-button>

                        <x-ui-button
                            variant="primary"
                            size="sm"
                            wire:click="saveTable"
                            wire:loading.attr="disabled"
                            wire:target="saveTable"
                        >Speichern</x-ui-button>
                    </div>
                </div>
            </x-slot>
        </x-ui-modal>
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
            + `pointer-events:none; user-select:none;`
            + `opacity:${this.$store.fpBg.on ? 1 : 0}; transition:opacity .15s;`;
    },
}));

// Zahlen kurz halten: (0.6 - 0.5) * 1000 ergibt sonst 99.99999999999997.
// pct() spiegelt das %.4f-Format von Table::surfaceStyle(), damit der vom
// Client geschriebene Wert identisch zu dem des Servers ist.
const round2 = (v) => Math.round(v * 100) / 100;
const pct    = (v) => (Math.round(v * 1000000) / 10000) + '%';

// Fangbereich beim Ausrichten, in Bildschirm-Pixeln – fühlt sich damit bei
// jeder Zoomstufe gleich an.
const SNAP_PX = 6;

// Einrasten an/aus. Als Store, weil Umschalter und die draggable-Komponenten
// verschiedene Alpine-Scopes sind: eine einfache Variable wäre nicht reaktiv,
// der Knopf könnte seinen Zustand also nicht anzeigen.
Alpine.store('fpSnap', {
    on: true,
    toggle() { this.on = !this.on; },
});

// Grundriss ein-/ausblenden – zum Prüfen, ob der nachgezeichnete Umriss
// alleine schon trägt. Reine Ansichtssache, nichts wird gespeichert.
Alpine.store('fpBg', {
    on: true,
    toggle() { this.on = !this.on; },
});

// Tisch verschieben/skalieren in NORMALISIERTEN Koordinaten (0…1).
// x/y = Mittelpunkt (Anteil der Flächenbreite/-höhe), w/h = Größe (Anteil).
// Deltas werden über die aktuelle Canvas-Pixelgröße in Anteile umgerechnet –
// dadurch stimmen die Positionen unabhängig von Bildschirm/Zoom.
Alpine.data('draggable', (tableId, initialX, initialY, initialW, initialH, hFactor = 1) => ({
    tableId,
    x: initialX, y: initialY,   // Mittelpunkt (0…1)
    w: initialW, h: initialH,   // Größe (Anteil 0…1)
    hFactor,                    // h = w * hFactor (Seitenverhältnis x Form) – hält die Proportion

    mode: null,                 // null | 'move' | 'resize'
    sx: 0, sy: 0,               // Zeigerposition beim Start (px)
    ox: 0, oy: 0, ow: 0,        // x/y/w beim Start (Anteile)

    /**
     * Wurzelelement (der Tisch) EINMAL festhalten.
     *
     * $el darf zur Laufzeit nicht benutzt werden: wird eine Methode aus dem
     * Handler des Resize-Griffs aufgerufen, zeigt $el auf den Griff – dann
     * bekommt der kleine Punkt die neue Größe statt der Tisch.
     */
    root: null,

    /** Canvas-Maße beim Start gemerkt – getBoundingClientRect() pro
     *  Zeigerbewegung erzwingt sonst jedes Mal ein Layout. */
    rect: null,

    /** Letzte Zeigerposition + rAF-Handle: pro Frame wird höchstens einmal
     *  gezeichnet, egal wie viele pointermove-Events hereinkommen. */
    pending: null,
    frame: null,

    /** Einrast-Kandidaten (Anteile 0…1), beim Gestenstart eingesammelt. */
    snapXs: [], snapYs: [],

    init() {
        this.root = this.$el;

        // Alle Listener hängen am Element selbst, nicht an document. Damit
        // verschwinden sie automatisch mit dem Element – vorher blieben sie
        // bei jedem Re-Render liegen und summierten sich (Ursache des Ruckelns).
        this.root.addEventListener('pointerdown', (e) => {
            // closest() statt e.target.dataset: der Zeiger trifft bei der
            // Dreh-Leiste das SVG IM Button, dort sitzt das data-Attribut nicht.
            if (e.target.closest && e.target.closest('[data-resize-handle],[data-rotate-handle]')) return;
            this.begin('move', e);
        });

        // Dank Pointer-Capture landen move/up hier, auch wenn der Zeiger den
        // Tisch verlässt – kein Abriss beim schnellen Ziehen.
        this.root.addEventListener('pointermove', (e) => this.onMove(e));
        this.root.addEventListener('pointerup', () => this.end());
        this.root.addEventListener('pointercancel', () => this.end());
        this.root.addEventListener('lostpointercapture', () => this.end());
    },

    destroy() {
        this.cancelFrame();
    },

    begin(mode, e) {
        if (this.mode) return;                                   // schon aktiv
        if (e.detail > 1) return;                                // Doppelklick -> Formular
        if (e.pointerType === 'mouse' && e.button !== 0) return;  // nur links

        const canvas = document.getElementById('floor-plan-canvas');
        if (!canvas) return;

        this.mode = mode;
        this.rect = canvas.getBoundingClientRect();
        this.sx = e.clientX; this.sy = e.clientY;
        this.ox = this.x; this.oy = this.y; this.ow = this.w;

        if (mode === 'move' && Alpine.store('fpSnap').on) this.collectSnapTargets(canvas);

        try { this.root.setPointerCapture(e.pointerId); } catch (_) {}

        // Die Tailwind-Klasse "transition" animiert u.a. transform über 150ms –
        // beim Ziehen würde der Tisch dem Zeiger sichtbar nachlaufen.
        this.root.style.transition = 'none';
        this.root.style.willChange = 'transform';

        // Kein preventDefault(): das würde laut Pointer-Events-Spec die
        // Kompatibilitäts-Mausevents unterdrücken und damit den Doppelklick
        // zum Bearbeiten kaputtmachen. Scrollen verhindert touch-none,
        // Textauswahl select-none – beides per CSS am Tisch.
        e.stopPropagation();
    },

    /**
     * Mitten der anderen Tische als Einrast-Ziele sammeln, plus die Canvas-Mitte.
     *
     * Bewusst über getBoundingClientRect() der Elemente statt über gerenderte
     * data-Attribute: updateTablePosition() läuft mit skipRender(), die Attribute
     * wären nach dem ersten Verschieben veraltet. Die Rects sind immer aktuell,
     * weil Alpine die Position als inline-style schreibt.
     */
    collectSnapTargets(canvas) {
        const cr = canvas.getBoundingClientRect();
        const halfW = this.w / 2;
        const halfH = this.h / 2;

        // Kandidat = { value, guide, gaps? }. value = Mittelpunkt, auf den
        // eingerastet wird; guide = wo die Linie liegt (bei Mitten identisch,
        // bei Abständen null, dort werden die Lücken angezeigt).
        const centersX = [{ value: 0.5, guide: 0.5 }];
        const centersY = [{ value: 0.5, guide: 0.5 }];

        const others = [];
        canvas.querySelectorAll('[data-table]').forEach((el) => {
            if (el === this.root) return;

            const r = el.getBoundingClientRect();
            others.push({
                left:   (r.left - cr.left) / cr.width,
                right:  (r.left + r.width - cr.left) / cr.width,
                top:    (r.top - cr.top) / cr.height,
                bottom: (r.top + r.height - cr.top) / cr.height,
            });
        });

        for (const o of others) {
            const cx = (o.left + o.right) / 2;
            const cy = (o.top + o.bottom) / 2;
            centersX.push({ value: cx, guide: cx });
            centersY.push({ value: cy, guide: cy });
        }

        // Abstände: gleiche Lücke wie zwischen zwei anderen Tischen. Diese
        // Kandidaten tragen KEINE Linie (guide: null), sondern die beiden
        // gleichen Lücken als gaps – eine Linie würde nichts über Abstand sagen.
        const spacingX = this.spacingTargets(others, 'left', 'right', halfW, this.w);
        const spacingY = this.spacingTargets(others, 'top', 'bottom', halfH, this.h);

        // Reihenfolge = Rangfolge bei gleichem Abstand: Mitte vor Abstand.
        this.snapXs = [...centersX, ...spacingX];
        this.snapYs = [...centersY, ...spacingY];
    },

    /**
     * Kandidaten für gleiche Abstände auf einer Achse.
     *
     * Zwei Fälle, beide aus PowerPoint bekannt:
     * - die Lücke zwischen A und B rechts von B (bzw. links von A) fortsetzen
     * - mittig zwischen A und B einsetzen, links und rechts gleiche Lücke
     */
    spacingTargets(others, nearSide, farSide, half, size) {
        const out = [];
        const sorted = [...others].sort((a, b) => a[nearSide] - b[nearSide]);

        for (const A of sorted) {
            for (const B of sorted) {
                if (A === B) continue;

                const gap = B[nearSide] - A[farSide];
                if (gap <= 0.002) continue;   // überlappend oder bündig

                // Muster nach "hinten" fortsetzen: |A| gap |B| gap |D|
                out.push({
                    value: B[farSide] + gap + half,
                    guide: null,
                    gaps: [[A[farSide], B[nearSide]], [B[farSide], B[farSide] + gap]],
                });

                // Muster nach "vorne" fortsetzen: |D| gap |A| gap |B|
                out.push({
                    value: A[nearSide] - gap - half,
                    guide: null,
                    gaps: [[A[nearSide] - gap, A[nearSide]], [A[farSide], B[nearSide]]],
                });

                // Mittig hineinsetzen, sofern der Tisch überhaupt passt.
                const free = gap - size;
                if (free > 0.002) {
                    const g = free / 2;
                    out.push({
                        value: A[farSide] + g + half,
                        guide: null,
                        gaps: [[A[farSide], A[farSide] + g], [B[nearSide] - g, B[nearSide]]],
                    });
                }
            }
        }

        return out;
    },

    /**
     * Nächster Kandidat innerhalb der Toleranz, sonst null.
     * Bei genau gleichem Abstand gewinnt der zuerst einsortierte – die Liste
     * beginnt mit den Mitten, die haben damit Vorrang vor Kanten.
     */
    nearest(value, candidates, tolerance) {
        let best = null;
        let bestDistance = Infinity;

        for (const c of candidates) {
            const d = Math.abs(value - c.value);
            if (d <= tolerance && d < bestDistance) { bestDistance = d; best = c; }
        }

        return best;
    },

    /** Hilfslinien ein-/ausblenden (null = aus). */
    guides(sx, sy) {
        const v = document.getElementById('fp-guide-v');
        const h = document.getElementById('fp-guide-h');

        if (v) {
            v.style.display = sx === null || sx === undefined ? 'none' : 'block';
            if (sx !== null && sx !== undefined) v.style.left = pct(sx);
        }
        if (h) {
            h.style.display = sy === null || sy === undefined ? 'none' : 'block';
            if (sy !== null && sy !== undefined) h.style.top = pct(sy);
        }
    },

    /**
     * Die beiden gleich großen Lücken anzeigen – bei Abstands-Treffern sagt eine
     * Linie nichts aus, sichtbar sein müssen die Abstände selbst.
     * crossCenter = Position auf der ANDEREN Achse, damit die Balken auf Höhe
     * des gezogenen Tischs liegen.
     */
    gapBars(axis, candidate, crossCenter) {
        const ids = axis === 'x' ? ['fp-gap-x1', 'fp-gap-x2'] : ['fp-gap-y1', 'fp-gap-y2'];
        const gaps = candidate && candidate.gaps ? candidate.gaps : null;

        ids.forEach((id, i) => {
            const el = document.getElementById(id);
            if (!el) return;

            const g = gaps ? gaps[i] : null;
            if (!g) { el.style.display = 'none'; return; }

            el.style.display = 'block';
            if (axis === 'x') {
                el.style.left  = pct(g[0]);
                el.style.width = pct(g[1] - g[0]);
                el.style.top   = pct(crossCenter);
            } else {
                el.style.top    = pct(g[0]);
                el.style.height = pct(g[1] - g[0]);
                el.style.left   = pct(crossCenter);
            }
        });
    },

    /** Alle Anzeigen aus. */
    clearGuides() {
        this.guides(null, null);
        this.gapBars('x', null, 0);
        this.gapBars('y', null, 0);
    },

    onMove(e) {
        if (!this.mode) return;

        this.pending = { cx: e.clientX, cy: e.clientY };

        if (this.frame !== null) return;
        const mode = this.mode;
        this.frame = requestAnimationFrame(() => {
            this.frame = null;
            if (this.mode && this.pending) this.compute(this.pending.cx, this.pending.cy, mode);
        });
    },

    /**
     * Der Modus wird ÜBERGEBEN, nicht aus this.mode gelesen. Sonst hängt das
     * Ergebnis davon ab, wann mode zurückgesetzt wurde – genau daran ist der
     * letzte Stand gescheitert: end() nullte mode vor dem Abschluss-compute,
     * wodurch beim Loslassen einer Verschiebung die Resize-Logik lief und der
     * Tisch seine Größe änderte.
     */
    compute(cx, cy, mode) {
        const dxp = (cx - this.sx) / this.rect.width;
        const dyp = (cy - this.sy) / this.rect.height;

        if (mode === 'move') {
            this.x = Math.min(1, Math.max(0, this.ox + dxp));
            this.y = Math.min(1, Math.max(0, this.oy + dyp));

            if (Alpine.store('fpSnap').on) {
                // Einrasten auf Mitte oder Kante eines anderen Tischs bzw. des
                // Plans. Toleranz in Pixeln, damit sie bei jedem Zoom gleich wirkt.
                const sx = this.nearest(this.x, this.snapXs, SNAP_PX / this.rect.width);
                const sy = this.nearest(this.y, this.snapYs, SNAP_PX / this.rect.height);

                if (sx) this.x = sx.value;
                if (sy) this.y = sy.value;

                this.guides(sx ? sx.guide : null, sy ? sy.guide : null);
                this.gapBars('x', sx, this.y);
                this.gapBars('y', sy, this.x);
            } else {
                this.clearGuides();   // frei platzieren
            }
        } else if (mode === 'resize') {
            // Nur die Breite kommt aus der Zeigerbewegung; die Höhe folgt
            // daraus. Egal wie schief man zieht, die Proportion bleibt.
            // Grenzen identisch zu FloorPlanEditor::updateTableSize(), damit
            // Anzeige und gespeicherter Wert nie auseinanderlaufen.
            this.w = Math.min(0.4, Math.max(0.02, this.ow + dxp));
            this.h = Math.min(0.9, Math.max(0.02, this.w * this.hFactor));
        } else {
            return;
        }

        this.paint(mode);
    },

    /**
     * Laufende Vorschau während der Geste – bewusst this.root, nicht $el.
     *
     * Beim Verschieben NUR transform: das läuft im Compositor, ohne Layout.
     * left/top in Prozent zu schreiben würde pro Frame ein Layout des Canvas
     * samt aller Tische erzwingen – das war das restliche Ruckeln.
     * Der Versatz kommt aus dem geclampten x/y, nicht aus der rohen
     * Zeigerbewegung, damit der Tisch am Rand wirklich stehenbleibt.
     */
    paint(mode) {
        const s = this.root.style;

        if (mode === 'move') {
            const dx = round2((this.x - this.ox) * this.rect.width);
            const dy = round2((this.y - this.oy) * this.rect.height);
            s.transform = `translate3d(${dx}px, ${dy}px, 0)`;
            return;
        }

        // Skalieren ändert die Größe – dabei ist Layout unvermeidbar.
        s.width  = pct(this.w);
        s.height = pct(this.h);
        s.left   = pct(this.x - this.w / 2);
        s.top    = pct(this.y - this.h / 2);
    },

    /**
     * Endzustand festschreiben: transform auflösen und die Position wieder als
     * Prozent setzen – dasselbe Format, das der Server rendert. Sonst würde ein
     * späteres Re-Render die Prozentwerte setzen und der stehengebliebene
     * transform-Versatz käme obendrauf.
     */
    commit(mode) {
        const s = this.root.style;

        // transform weg, Position als Prozent – transition bleibt vorerst aus.
        s.transform = '';

        if (mode === 'resize') {
            s.width  = pct(this.w);
            s.height = pct(this.h);
        }

        s.left = pct(this.x - this.w / 2);
        s.top  = pct(this.y - this.h / 2);

        // Transition ERST im nächsten Frame zurückgeben. Zusammen mit dem
        // Wegfall des transform in einem Durchlauf würde der Browser diesen
        // über 150ms nach null animieren, während left/top sofort springen –
        // der Tisch federt dann aus der alten Richtung nach.
        requestAnimationFrame(() => {
            s.transition = '';
            s.willChange = '';
        });
    },

    cancelFrame() {
        if (this.frame !== null) {
            cancelAnimationFrame(this.frame);
            this.frame = null;
        }
        this.pending = null;
    },

    end() {
        if (!this.mode) return;

        const m = this.mode;
        this.mode = null;

        // Letzte Zeigerposition noch anwenden, falls der Frame noch aussteht –
        // sonst geht die letzte Bewegung vor dem Loslassen verloren. Modus
        // explizit mitgeben, da this.mode hier schon zurückgesetzt ist.
        if (this.pending) this.compute(this.pending.cx, this.pending.cy, m);
        this.cancelFrame();
        this.clearGuides();   // Linien und Abstandsbalken immer ausblenden

        // transform -> Prozent, damit der DOM-Zustand dem entspricht, was der
        // Server beim nächsten Rendern ausgibt.
        this.commit(m);

        // Nichts bewegt? Dann auch nicht speichern. Ein Klick (und damit jeder
        // Doppelklick zum Bearbeiten) durchläuft diesen Pfad ebenfalls und
        // hätte sonst jedes Mal einen Request mit unveränderten Werten erzeugt.
        const eps = 0.0005;
        const changed = m === 'move'
            ? (Math.abs(this.x - this.ox) > eps || Math.abs(this.y - this.oy) > eps)
            : Math.abs(this.w - this.ow) > eps;

        if (!changed) return;

        // Pro Geste genau ein Request (nur beim Loslassen) – Livewire
        // serialisiert Aufrufe derselben Komponente, ein eigener Sende-Guard
        // würde nur riskieren, eine Änderung stillschweigend zu verwerfen.
        if (m === 'move') {
            this.$wire.updateTablePosition(this.tableId, this.x, this.y);
        } else {
            // Nur die Breite senden – die Höhe rechnet der Server aus der Form.
            this.$wire.updateTableSize(this.tableId, this.w);
        }
    },

    // Vom Resize-Griff aufgerufen
    startResize(e) {
        this.begin('resize', e);
    },
}));

/* =======================================================================
   RAUMUMRISS – VERSUCHSSTAND, rückstandsfrei entfernbar.
   Zum Entfernen: diesen Abschnitt, den Include im Markup und die Klasse
   Support\RoomLayout löschen. Der Tisch-Teil oben bleibt unberührt.
   ======================================================================= */
Alpine.data('roomDraw', (initialPaths) => ({
    paths: initialPaths || [],
    zug: null,          // { von, bis } – Wand, die gerade gezogen wird
    flaeche: null,      // Element mit der Zeiger-Erfassung während des Ziehens
    menu: null,         // { pi, x, y } – Menü am Verschiebe-Griff
    hatGezogen: false,  // unterdrückt den Klick nach einem Ziehen

    /** Wie nah (in Pixeln) ein Ende an ein vorhandenes einrastet. */
    FANG_PX: 14,

    /* --- Anzeige --------------------------------------------------------- */

    /**
     * Alle Wände als EIN d-Attribut. Ein Pfad darf über "M" mehrere Teilzüge
     * tragen – damit entfällt die Schleife im SVG, wo Alpine-Templates nicht
     * funktionieren (im SVG-Namensraum hat <template> kein .content).
     */
    get d() {
        return this.paths
            .filter(p => p.length > 1)
            .map(p => 'M' + p.map(pt => pt[0] + ' ' + pt[1]).join(' L'))
            .join(' ');
    },

    /** Die Wand, die gerade gezogen wird. */
    get dEntwurf() {
        if (!this.zug) { return ''; }
        return 'M' + this.zug.von[0] + ' ' + this.zug.von[1]
             + ' L' + this.zug.bis[0] + ' ' + this.zug.bis[1];
    },

    init() {
        this._esc = (e) => {
            if (e.key === 'Escape') { this.zug = null; this.menu = null; }
        };
        window.addEventListener('keydown', this._esc);
    },

    destroy() {
        window.removeEventListener('keydown', this._esc);
    },

    /* --- Geometrie ------------------------------------------------------- */

    /** Mausposition in normalisierte Koordinaten (0…1) der Zeichenfläche. */
    pos(e) {
        const r = this.$root.getBoundingClientRect();
        return [
            Math.min(1, Math.max(0, (e.clientX - r.left) / r.width)),
            Math.min(1, Math.max(0, (e.clientY - r.top) / r.height)),
        ];
    },

    /** Abstand zweier Punkte in Bildschirmpixeln. */
    abstandPx(a, b) {
        const r  = this.$root.getBoundingClientRect();
        const dx = (a[0] - b[0]) * r.width;
        const dy = (a[1] - b[1]) * r.height;
        return Math.sqrt(dx * dx + dy * dy);
    },

    /**
     * An ein vorhandenes Wandende einrasten.
     *
     * Dadurch wachsen einzeln gezogene Wände von selbst zu einem Zug zusammen,
     * ohne dass man Punkte aneinanderreihen muss.
     */
    fangEnde(pt) {
        let treffer = null;
        let best    = this.FANG_PX;
        const von   = this.zug?.von;

        for (const pfad of this.paths) {
            for (const kandidat of [pfad[0], pfad[pfad.length - 1]]) {
                // Nicht auf den eigenen Ausgangspunkt zurückrasten – sonst
                // klebt die Wand beim Weiterzeichnen an ihrem Anfang fest.
                if (von && kandidat[0] === von[0] && kandidat[1] === von[1]) { continue; }

                const dist = this.abstandPx(pt, kandidat);
                if (dist < best) { best = dist; treffer = [...kandidat]; }
            }
        }

        return treffer;
    },

    /**
     * Auf Waagerechte oder Senkrechte einrasten – Wände laufen fast immer im
     * rechten Winkel. Shift schaltet frei.
     *
     * Verglichen wird in BILDSCHIRMPIXELN: Bei einem breiten Saal entspricht
     * 0,1 waagerecht einer viel längeren Strecke als 0,1 senkrecht.
     */
    gerade(pt, von, e) {
        if (e.shiftKey) { return pt; }

        const r  = this.$root.getBoundingClientRect();
        const dx = Math.abs(pt[0] - von[0]) * r.width;
        const dy = Math.abs(pt[1] - von[1]) * r.height;

        return dx >= dy ? [pt[0], von[1]] : [von[0], pt[1]];
    },

    /**
     * Auf die Flucht vorhandener Wände ziehen.
     *
     * Ohne das steht jede kurze Wand ein paar Pixel versetzt, obwohl sie
     * baulich auf einer Linie liegt. Verglichen wird je Achse in
     * BILDSCHIRMPIXELN, damit die Toleranz überall gleich groß wirkt.
     *
     * Eine Achse wird nur eingerastet, wenn sie überhaupt frei ist: Beim
     * geraden Ziehen hält die andere bereits den Ausgangspunkt fest, und die
     * dürfte ein Nachbar nicht verbiegen.
     *
     * Liefert den eingerasteten Punkt sowie die Werte fuer die beiden
     * Hilfslinien (oder null, wenn keine gezeigt werden soll).
     */
    flucht(pt, von) {
        if (! this.$store.fpSnap.on) { return { pt, gx: null, gy: null }; }

        const r = this.$root.getBoundingClientRect();
        let [x, y] = pt;
        let gx = null, gy = null;

        const freiX = ! von || pt[0] !== von[0];
        const freiY = ! von || pt[1] !== von[1];

        let bestX = this.FANG_PX;
        let bestY = this.FANG_PX;

        for (const pfad of this.paths) {
            for (const kandidat of pfad) {
                if (freiX) {
                    const d = Math.abs(kandidat[0] - pt[0]) * r.width;
                    if (d < bestX) { bestX = d; x = kandidat[0]; gx = kandidat[0]; }
                }
                if (freiY) {
                    const d = Math.abs(kandidat[1] - pt[1]) * r.height;
                    if (d < bestY) { bestY = d; y = kandidat[1]; gy = kandidat[1]; }
                }
            }
        }

        return { pt: [x, y], gx, gy };
    },

    /**
     * Einrasten beim Verschieben einer Ecke.
     *
     * Zuerst auf die NACHBARN im selben Zug: Übernimmt die Ecke deren x oder y,
     * steht die betreffende Wand senkrecht bzw. waagerecht – und die Ecke wird
     * damit zum rechten Winkel. Genau das will man beim Begradigen.
     *
     * Danach, für noch freie Achsen, auf die Flucht der übrigen Wände.
     *
     * Es bleibt ein Einrasten, keine Zwangsführung: Wer weiter zieht, kommt
     * ohne Weiteres wieder heraus.
     */
    fangEcke(pt, pi, ii) {
        if (! this.$store.fpSnap.on) { return { pt, gx: null, gy: null }; }

        const pfad = this.paths[pi];
        const r    = this.$root.getBoundingClientRect();
        let [x, y] = pt;
        let gx = null, gy = null;

        // Beim Ring ist der doppelte Endpunkt kein eigener Nachbar.
        const n    = this.istGeschlossen(pfad) ? pfad.length - 1 : pfad.length;
        const vor  = ii > 0 ? pfad[ii - 1] : (this.istGeschlossen(pfad) ? pfad[n - 1] : null);
        const nach = ii < n - 1 ? pfad[ii + 1] : (this.istGeschlossen(pfad) ? pfad[0] : null);

        for (const nachbar of [vor, nach]) {
            if (! nachbar) { continue; }

            if (gx === null && Math.abs(nachbar[0] - pt[0]) * r.width < this.FANG_PX) {
                x = nachbar[0]; gx = nachbar[0];
            }
            if (gy === null && Math.abs(nachbar[1] - pt[1]) * r.height < this.FANG_PX) {
                y = nachbar[1]; gy = nachbar[1];
            }
        }

        // Übrige Achsen: an den anderen Wänden ausrichten.
        let bestX = this.FANG_PX;
        let bestY = this.FANG_PX;

        for (let k = 0; k < this.paths.length; k++) {
            for (let j = 0; j < this.paths[k].length; j++) {
                if (k === pi && j === ii) { continue; }
                const kandidat = this.paths[k][j];

                if (gx === null) {
                    const d = Math.abs(kandidat[0] - pt[0]) * r.width;
                    if (d < bestX) { bestX = d; x = kandidat[0]; gx = kandidat[0]; }
                }
                if (gy === null) {
                    const d = Math.abs(kandidat[1] - pt[1]) * r.height;
                    if (d < bestY) { bestY = d; y = kandidat[1]; gy = kandidat[1]; }
                }
            }
        }

        return { pt: [x, y], gx, gy };
    },

    /** Hilfslinien ein-/ausblenden – dieselben Elemente wie beim Tisch-Einrasten. */
    hilfslinien(gx, gy) {
        const v = document.getElementById('fp-guide-v');
        const h = document.getElementById('fp-guide-h');

        if (v) {
            v.style.display = gx === null ? 'none' : 'block';
            if (gx !== null) { v.style.left = (gx * 100) + '%'; }
        }
        if (h) {
            h.style.display = gy === null ? 'none' : 'block';
            if (gy !== null) { h.style.top = (gy * 100) + '%'; }
        }
    },

    istGeschlossen(pfad) {
        if (pfad.length < 4) { return false; }
        const a = pfad[0], b = pfad[pfad.length - 1];
        return a[0] === b[0] && a[1] === b[1];
    },

    /**
     * Griffe eines Zugs mit ihrem echten Index. Beim geschlossenen Zug entfällt
     * der letzte Punkt – er liegt auf dem ersten, zwei Griffe übereinander
     * ließen sich nicht auseinanderhalten.
     */
    griffe(pfad) {
        const bis = this.istGeschlossen(pfad) ? pfad.length - 1 : pfad.length;
        return pfad.slice(0, bis).map((pt, i) => ({ i, pt }));
    },

    /**
     * Ist dieser Punkt ein offenes Ende, an dem der Zug weitergehen kann?
     * Bei einem geschlossenen Ring gibt es keine offenen Enden mehr.
     */
    istEnde(pfad, i) {
        if (this.istGeschlossen(pfad)) { return false; }
        return i === 0 || i === pfad.length - 1;
    },

    /* --- Zeichnen: ziehen von A nach B ----------------------------------- */

    zeichnenStart(e) {
        if (this.menu) { this.menu = null; return; }

        const roh = this.pos(e);
        let   von = this.fangEnde(roh);

        if (! von) {
            // Kein vorhandenes Ende in der Nähe: dann wenigstens auf die
            // Flucht der übrigen Wände ziehen.
            const f = this.flucht(roh, null);
            von = f.pt;
            this.hilfslinien(f.gx, f.gy);
        }

        this.zug = { von, bis: von };

        // Erfassung auf die FLÄCHE, nicht auf $root: Der Wrapper ist ein
        // anderes Element, und mit Erfassung dort würden pointermove und
        // pointerup an ihn zugestellt – die Handler hängen aber an der Fläche
        // und bekämen nichts mehr mit.
        this.flaeche = e.currentTarget;
        try { this.flaeche.setPointerCapture(e.pointerId); } catch (_) {}
    },

    zeichnenBewegt(e) {
        if (!this.zug) { return; }

        const roh  = this.pos(e);
        const ende = this.fangEnde(roh);

        if (ende) {
            this.zug.bis = ende;
            this.hilfslinien(null, null);
            return;
        }

        const gerade = this.gerade(roh, this.zug.von, e);
        const f      = this.flucht(gerade, this.zug.von);

        this.zug.bis = f.pt;
        this.hilfslinien(f.gx, f.gy);
    },

    zeichnenEnde(e) {
        if (!this.zug) { return; }

        const { von, bis } = this.zug;
        this.zug = null;

        try { this.flaeche?.releasePointerCapture(e.pointerId); } catch (_) {}
        this.flaeche = null;

        this.hilfslinien(null, null);

        // Ein Klick ohne Ziehen ist keine Wand – sonst entstünden bei jedem
        // versehentlichen Klick Punkte im Raum.
        if (this.abstandPx(von, bis) < 5) { return; }

        this.wandAblegen(von, bis);
    },

    /**
     * Wand einsortieren: Schließt sie an ein vorhandenes Ende an, wächst der
     * Zug einfach weiter – so entsteht aus mehreren Zügen ein Umriss, ohne
     * dass man das erklären muss.
     */
    wandAblegen(von, bis) {
        const gleich = (a, b) => a[0] === b[0] && a[1] === b[1];

        for (const pfad of this.paths) {
            if (gleich(pfad[pfad.length - 1], von)) { pfad.push(bis); this.speichern(); return; }
            if (gleich(pfad[0], bis))               { pfad.unshift(von); this.speichern(); return; }
            if (gleich(pfad[pfad.length - 1], bis)) { pfad.push(von); this.speichern(); return; }
            if (gleich(pfad[0], von))               { pfad.unshift(bis); this.speichern(); return; }
        }

        this.paths.push([von, bis]);
        this.speichern();
    },

    /**
     * Vom offenen Ende aus weiterzeichnen.
     *
     * Die Griffe liegen genau auf den Ecken – wer dort ansetzt, will fast immer
     * die Wand fortsetzen und nicht den Punkt verschieben. Deshalb führt das
     * Drücken auf ein offenes ENDE ins Zeichnen; Punkte MITTEN im Zug lassen
     * sich weiterhin verschieben, dort ist Verschieben die sinnvolle Geste.
     */
    weiterZeichnen(e, pt) {
        e.preventDefault();

        const el = e.currentTarget;
        try { el.setPointerCapture(e.pointerId); } catch (_) {}

        this.zug = { von: [...pt], bis: [...pt] };

        const bewegen = (ev) => {
            const roh  = this.pos(ev);
            const ende = this.fangEnde(roh);

            if (ende) { this.zug.bis = ende; this.hilfslinien(null, null); return; }

            const f = this.flucht(this.gerade(roh, this.zug.von, ev), this.zug.von);
            this.zug.bis = f.pt;
            this.hilfslinien(f.gx, f.gy);
        };
        const loslassen = (ev) => {
            el.removeEventListener('pointermove', bewegen);
            el.removeEventListener('pointerup', loslassen);
            el.removeEventListener('pointercancel', loslassen);

            const zug = this.zug;
            this.zug = null;
            this.hilfslinien(null, null);

            if (zug && this.abstandPx(zug.von, zug.bis) >= 5) {
                this.wandAblegen(zug.von, zug.bis);
            }
        };

        el.addEventListener('pointermove', bewegen);
        el.addEventListener('pointerup', loslassen);
        el.addEventListener('pointercancel', loslassen);
    },

    /* --- Bearbeiten ------------------------------------------------------ */

    /**
     * Mitte eines Zugs – dort sitzt der Griff zum Verschieben.
     *
     * Über den umschließenden Kasten, NICHT über den Mittelwert der Punkte:
     * Beim geschlossenen Ring ist der Anfangspunkt doppelt enthalten und zöge
     * den Mittelwert zu sich. Bei einem Rechteck landete der Griff dadurch
     * sichtbar oben links statt in der Mitte. Der Kasten ist davon unabhängig
     * und liefert auch bei ungleich verteilten Ecken die optische Mitte.
     */
    mitte(pfad) {
        const xs = pfad.map(pt => pt[0]);
        const ys = pfad.map(pt => pt[1]);

        return [
            (Math.min(...xs) + Math.max(...xs)) / 2,
            (Math.min(...ys) + Math.max(...ys)) / 2,
        ];
    },

    /**
     * Ganzen Zug verschieben. Begrenzt wird die VERSCHIEBUNG, nicht jeder Punkt
     * einzeln – sonst verzöge sich die Form, sobald ein Ende den Rand erreicht.
     */
    pfadAnfassen(e, pi) {
        e.preventDefault();
        this.hatGezogen = false;
        e.target.setPointerCapture(e.pointerId);
        e.target.style.cursor = 'grabbing';

        const start = this.pos(e);
        const orig  = this.paths[pi].map(pt => [...pt]);
        const minX  = Math.min(...orig.map(pt => pt[0]));
        const maxX  = Math.max(...orig.map(pt => pt[0]));
        const minY  = Math.min(...orig.map(pt => pt[1]));
        const maxY  = Math.max(...orig.map(pt => pt[1]));

        const bewegen = (ev) => {
            this.hatGezogen = true;
            const jetzt = this.pos(ev);
            let dx = Math.min(1 - maxX, Math.max(-minX, jetzt[0] - start[0]));
            let dy = Math.min(1 - maxY, Math.max(-minY, jetzt[1] - start[1]));
            this.paths[pi] = orig.map(pt => [pt[0] + dx, pt[1] + dy]);
        };
        const loslassen = () => {
            e.target.style.cursor = 'grab';
            e.target.removeEventListener('pointermove', bewegen);
            e.target.removeEventListener('pointerup', loslassen);
            e.target.removeEventListener('pointercancel', loslassen);
            if (this.hatGezogen) { this.speichern(); }
        };

        e.target.addEventListener('pointermove', bewegen);
        e.target.addEventListener('pointerup', loslassen);
        e.target.addEventListener('pointercancel', loslassen);
    },

    griffAnfassen(e, pi, ii) {
        e.preventDefault();
        this.hatGezogen = false;
        e.target.setPointerCapture(e.pointerId);
        e.target.style.cursor = 'grabbing';

        const bewegen = (ev) => {
            this.hatGezogen = true;

            const f = this.fangEcke(this.pos(ev), pi, ii);
            this.paths[pi][ii] = f.pt;
            this.hilfslinien(f.gx, f.gy);

            // Beim Ring ist der letzte Punkt derselbe wie der erste – er muss
            // mitwandern, sonst reißt der Zug auf.
            if (ii === 0 && this.istGeschlossen(this.paths[pi])) {
                this.paths[pi][this.paths[pi].length - 1] = [...this.paths[pi][0]];
            }
        };
        const loslassen = () => {
            e.target.style.cursor = 'grab';
            e.target.removeEventListener('pointermove', bewegen);
            e.target.removeEventListener('pointerup', loslassen);
            e.target.removeEventListener('pointercancel', loslassen);
            this.hilfslinien(null, null);
            if (this.hatGezogen) { this.speichern(); }
        };

        e.target.addEventListener('pointermove', bewegen);
        e.target.addEventListener('pointerup', loslassen);
        e.target.addEventListener('pointercancel', loslassen);
    },

    menuOeffnen(e, pi) {
        const m = this.mitte(this.paths[pi]);
        this.menu = { pi, x: m[0], y: m[1] };
    },

    pfadLoeschen(pi) {
        this.paths.splice(pi, 1);
        this.menu = null;
        this.speichern();
    },

    punktLoeschen(pi, ii) {
        this.paths[pi].splice(ii, 1);
        if (this.paths[pi].length < 2) { this.paths.splice(pi, 1); }
        this.speichern();
    },

    speichern() {
        this.$wire.saveRoomPaths(this.paths);
    },
}));
</script>
@endscript
