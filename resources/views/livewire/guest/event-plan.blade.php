{{--
    Küche und Laufzettel für Veranstaltungsleiter – ohne Konto, per Link mit PIN.

    Bewusst OHNE Buchungsliste: Die enthält Gästenamen und E-Mail-Adressen, und
    ein Link wandert per Weiterleitung weiter, als gedacht. Was hier steht, ist
    das, was jemand vor Ort braucht: was wann vorbereitet wird und wohin es geht.

    Rein lesend – es gibt auf dieser Seite nichts zu ändern.
--}}
<div class="min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="mx-auto max-w-3xl px-4 py-10">
        @php $event = $this->event; @endphp

        {{-- Kopf --}}
        <div class="mb-6">
            <p class="m-0 text-xs uppercase tracking-[0.15em] text-gray-500">PausePlus</p>
            <h1 class="m-0 mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ $event->name }}</h1>
            <p class="m-0 mt-1 text-sm text-gray-500">
                {{ $event->date?->format('d.m.Y') }}
                @if ($event->venue) · {{ $event->venue->name }} @endif
            </p>
        </div>

        @unless ($this->unlocked)
            {{-- PIN-Hürde: Der Link allein soll nicht genügen. --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-8 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="m-0 text-base font-semibold text-gray-900 dark:text-white">PIN eingeben</h2>
                <p class="m-0 mt-1 text-sm text-gray-500">
                    Sie haben die PIN zusammen mit diesem Link erhalten.
                </p>

                <form wire:submit="submitPin" class="mt-5 flex flex-wrap items-start gap-3">
                    <div>
                        <input
                            type="text"
                            wire:model="pin"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            maxlength="6"
                            placeholder="000000"
                            class="w-40 rounded-lg border border-gray-300 px-4 py-2.5 text-center text-lg tracking-[0.3em] tabular-nums dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        >
                        @if ($pinError)
                            <p class="m-0 mt-2 text-sm text-red-600">{{ $pinError }}</p>
                        @endif
                    </div>
                    <button
                        type="submit"
                        class="rounded-lg bg-gray-900 px-5 py-2.5 text-sm font-medium text-white hover:bg-gray-700 dark:bg-white dark:text-gray-900"
                    >
                        Öffnen
                    </button>
                </form>
            </div>
        @else
            {{-- Reiter: dieselben zwei Ansichten wie im Backoffice --}}
            <div class="mb-5 border-b border-gray-200 dark:border-gray-800">
                <nav class="-mb-px flex gap-1">
                    @foreach ([['kitchen', 'Küche'], ['function', 'Laufzettel']] as [$key, $label])
                        <button
                            type="button"
                            wire:click="setTab('{{ $key }}')"
                            class="border-b-2 px-4 py-2 text-sm font-medium transition-colors
                                {{ $tab === $key
                                    ? 'border-gray-900 text-gray-900 dark:border-white dark:text-white'
                                    : 'border-transparent text-gray-500 hover:text-gray-900 dark:hover:text-white' }}"
                        >{{ $label }}</button>
                    @endforeach
                </nav>
            </div>

            @if ($tab === 'kitchen')
                @php $totals = $this->slotStats->get(0); @endphp
                <div class="mb-5 flex flex-wrap items-center gap-x-8 gap-y-2 border-b border-gray-200 pb-4 dark:border-gray-800">
                    @foreach ([['Buchungen', $totals?->bookings ?? 0], ['Gäste', $totals?->guests ?? 0], [$event->slots->count() === 1 ? 'Pause' : 'Pausen', $event->slots->count()]] as [$label, $wert])
                        <div>
                            <div class="text-xl font-bold leading-none tabular-nums text-gray-900 dark:text-white">{{ $wert }}</div>
                            <div class="mt-1 text-xs text-gray-500">{{ $label }}</div>
                        </div>
                    @endforeach
                    <span class="ml-auto text-xs text-gray-400">ohne Stornos / No-Shows</span>
                </div>

                @forelse ($this->prepBySlot as $slot)
                    <div class="mb-4 overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900" wire:key="slot-{{ $loop->index }}">
                        <div class="flex flex-wrap items-center gap-x-3 border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                            <h2 class="m-0 text-sm font-semibold text-gray-900 dark:text-white">{{ $slot['slot']->displayLabel() }}</h2>
                            <span class="text-xs tabular-nums text-gray-400">{{ $slot['total'] }} Artikel gesamt</span>
                        </div>
                        <div class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($slot['groups'] as $g)
                                <div class="px-4 py-3" wire:key="g-{{ $loop->parent->index }}-{{ $loop->index }}">
                                    <div class="mb-2 flex flex-wrap items-center gap-2">
                                        <span class="h-3 w-3 shrink-0 rounded-full" style="background: {{ $g['color'] ?: '#9b9a97' }}"></span>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $g['name'] }}</span>
                                        @if ($g['target_time'])
                                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold" style="color: {{ $g['color'] ?: '#374151' }}; background: {{ ($g['color'] ?: '#9ca3af') }}1a">
                                                zubereiten ab {{ $g['target_time'] }} Uhr
                                            </span>
                                        @else
                                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500 dark:bg-gray-800">vorab / jederzeit</span>
                                        @endif
                                        <span class="ml-auto text-xs tabular-nums text-gray-400">{{ $g['total'] }} Stück</span>
                                    </div>
                                    @foreach ($g['items'] as $item)
                                        <div class="flex items-center justify-between py-1 text-sm">
                                            <span class="text-gray-700 dark:text-gray-200">{{ $item['name'] }}</span>
                                            <span class="font-semibold tabular-nums text-gray-900 dark:text-white">{{ $item['qty'] }}×</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-gray-300 py-16 text-center text-sm text-gray-500 dark:border-gray-700">
                        Noch keine Vorbestellungen für diese Veranstaltung.
                    </div>
                @endforelse

            @else
                @php $sheet = $this->sheet; @endphp
                @forelse ($sheet['pauses'] as $pause)
                    <div class="mb-4 overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900" wire:key="fs-{{ $loop->index }}">
                        <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                            <h2 class="m-0 text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $pause['slot']['name'] }}@if ($pause['slot']['time_start']) · {{ $pause['slot']['time_start'] }} Uhr @endif
                            </h2>
                        </div>
                        <div class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($pause['runs'] as $run)
                                @php $color = $run['holding_class']['color'] ?? '#9b9a97'; @endphp
                                <div class="px-4 py-3" wire:key="run-{{ $loop->parent->index }}-{{ $loop->index }}">
                                    <div class="mb-2 flex flex-wrap items-center gap-2">
                                        <span class="h-3 w-3 shrink-0 rounded-full" style="background: {{ $color }}"></span>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $run['label'] }}</span>
                                        @if ($run['target_time'])
                                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold" style="color: {{ $color }}; background: {{ $color }}1a">
                                                platzieren bis {{ $run['target_time'] }} Uhr
                                            </span>
                                        @endif
                                    </div>
                                    {{-- Tisch → Buchung → Artikel, wie im Backoffice.
                                         Der Gastname steht dabei, weil der Service ohne ihn
                                         nicht weiß, wem er was hinstellt. --}}
                                    <div class="space-y-2">
                                        @foreach ($run['tables'] as $table)
                                            <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-800 dark:bg-gray-950">
                                                <div class="mb-1 flex items-baseline gap-2">
                                                    <span class="text-sm font-semibold text-gray-900 dark:text-white">Tisch {{ $table['table']['label'] ?? '—' }}</span>
                                                    @if ($table['room'])<span class="text-xs text-gray-400">{{ $table['room'] }}</span>@endif
                                                </div>
                                                @foreach ($table['bookings'] as $booking)
                                                    <div class="text-xs leading-relaxed">
                                                        <span class="text-gray-500">{{ $booking['guest_name'] }}:</span>
                                                        @foreach ($booking['items'] as $item)<span class="font-semibold tabular-nums text-gray-900 dark:text-white">{{ $item['quantity'] }}×</span> {{ $item['name'] }}@if (! $loop->last), @endif @endforeach
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @empty
                                <div class="px-4 py-6 text-sm text-gray-500">Keine Laufrunden für diese Pause.</div>
                            @endforelse
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-gray-300 py-16 text-center text-sm text-gray-500 dark:border-gray-700">
                        Noch keine Laufrunden für diese Veranstaltung.
                    </div>
                @endforelse
            @endif

            <p class="mt-8 text-center text-xs text-gray-400">
                Nur zur Ansicht · Der Zugang läuft am
                {{ $event->shareExpiresAt()?->format('d.m.Y') }} ab
            </p>
        @endunless
    </div>
</div>
