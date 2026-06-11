<div class="min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="mx-auto max-w-5xl px-4 py-10">
        {{-- Kopf --}}
        <div class="mb-8 text-center">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">PausePlus</h1>
            <p class="mt-2 text-gray-600 dark:text-gray-400">
                Bestellen Sie Ihre Pausen-Verpflegung bequem vor – und genießen Sie die Pause ohne Anstehen.
            </p>
        </div>

        @if ($this->events->isEmpty())
            <div class="rounded-2xl border border-dashed border-gray-300 bg-white py-20 text-center dark:border-gray-700 dark:bg-gray-900">
                <div class="text-5xl mb-4">🎭</div>
                <h2 class="text-lg font-semibold dark:text-white">Aktuell keine buchbaren Termine</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Schauen Sie bald wieder vorbei.</p>
            </div>
        @else
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->events as $event)
                    <a wire:key="event-{{ $event->id }}"
                        href="{{ \Illuminate\Support\Facades\Route::has('reservation.guest.checkout') ? route('reservation.guest.checkout', $event->uuid) : '#' }}"
                        class="group overflow-hidden rounded-2xl border bg-white shadow-sm transition hover:shadow-md dark:border-gray-700 dark:bg-gray-900">
                        @if ($event->image_context_file_id && $event->imageFile)
                            <img src="{{ $event->imageUrl('medium_16_9') }}" alt=""
                                class="aspect-video w-full object-cover transition group-hover:scale-[1.02]" />
                        @else
                            <div class="flex aspect-video w-full items-center justify-center bg-[var(--ui-primary-10)] text-4xl">
                                🎭
                            </div>
                        @endif
                        <div class="p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-primary)]">
                                {{ $event->date->locale('de')->isoFormat('dd, D. MMMM Y') }}
                            </p>
                            <h2 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $event->name }}</h2>
                            @if ($event->venue)
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $event->venue->name }}</p>
                            @endif
                            @if ($event->slots->isNotEmpty())
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $event->slots->map(fn ($s) => $s->name . ' ' . substr($s->time_start, 0, 5) . ' Uhr')->implode(' · ') }}
                                </p>
                            @endif
                            @if (!$event->isOrderable())
                                <p class="mt-2 inline-block rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-700 dark:bg-red-900/40 dark:text-red-300">
                                    Bestellschluss erreicht
                                </p>
                            @else
                                <p class="mt-3 text-sm font-medium text-[var(--ui-primary)] group-hover:underline">
                                    Jetzt vorbestellen →
                                </p>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
