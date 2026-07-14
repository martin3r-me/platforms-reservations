@php
    $accent   = config('reservation.guest.accent', '#285567');
    $logoUrl  = config('reservation.guest.logo_url') ?: route('reservation.guest.brand.logo');
    $eyebrow  = config('reservation.guest.eyebrow', 'PausePlus');
    $intro    = config('reservation.guest.intro');
@endphp

<div class="min-h-screen bg-white" style="--accent: {{ $accent }};">
    {{-- Cormorant Garamond für den Hero (Culinaria-Look) --}}
    <style>@import url('https://fonts.bunny.net/css?family=cormorant-garamond:600i,700i&display=swap');</style>

    {{-- Sticky-Navigation: Logo + Suche --}}
    <header class="sticky top-0 z-30 border-b border-gray-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-3 px-4 py-3">
            <img src="{{ $logoUrl }}" alt="Culinaria" class="h-9 w-auto shrink-0" />

            <div class="ml-auto flex flex-1 flex-wrap items-center justify-end gap-2">
                <div class="relative min-w-[180px] flex-1 sm:max-w-xs">
                    @svg('heroicon-o-magnifying-glass', 'pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400')
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Veranstaltung suchen…"
                        class="w-full rounded-full border border-gray-300 py-2 pl-9 pr-3 text-sm focus:border-[var(--accent)] focus:outline-none focus:ring-1 focus:ring-[var(--accent)]" />
                </div>
                <input type="date" wire:model.live="filterDate"
                    class="rounded-full border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-[var(--accent)] focus:outline-none focus:ring-1 focus:ring-[var(--accent)]" />
                @if ($search !== '' || $filterDate !== '')
                    <button wire:click="resetFilters" class="text-sm text-gray-500 hover:text-gray-800">Zurücksetzen</button>
                @endif
            </div>
        </div>
    </header>

    {{-- Hero --}}
    <section class="mx-auto max-w-3xl px-4 pt-12 pb-8 text-center sm:pt-16">
        <p class="text-xs font-semibold uppercase tracking-[0.2em]" style="color: var(--accent);">{{ $eyebrow }}</p>
        <h1 class="mx-auto mt-3 max-w-2xl text-3xl italic leading-tight sm:text-4xl md:text-[2.75rem]"
            style="font-family: 'Cormorant Garamond', Georgia, serif; color: var(--accent);">
            {{ $intro }}
        </h1>
        <div class="mx-auto mt-6 h-px w-24" style="background: var(--accent);"></div>
    </section>

    {{-- Termine --}}
    <section class="mx-auto max-w-6xl px-4 pb-16">
        @if ($this->events->isEmpty())
            <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 py-20 text-center">
                <div class="mb-4 text-5xl">🎭</div>
                <h2 class="text-lg font-semibold text-gray-800">
                    @if ($search !== '' || $filterDate !== '')
                        Keine Veranstaltung gefunden
                    @else
                        Aktuell keine buchbaren Termine
                    @endif
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    @if ($search !== '' || $filterDate !== '')
                        Bitte Suche oder Datum anpassen.
                    @else
                        Schauen Sie bald wieder vorbei.
                    @endif
                </p>
                @if ($search !== '' || $filterDate !== '')
                    <button wire:click="resetFilters" class="mt-4 text-sm font-medium underline" style="color: var(--accent);">Filter zurücksetzen</button>
                @endif
            </div>
        @else
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->events as $event)
                    <a wire:key="event-{{ $event->id }}"
                        href="{{ \Illuminate\Support\Facades\Route::has('reservation.guest.checkout') ? route('reservation.guest.checkout', $event->uuid) : '#' }}"
                        class="group overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg">
                        @if ($event->image_context_file_id && $event->imageFile)
                            <img src="{{ $event->imageUrl('medium_16_9') }}" alt=""
                                class="aspect-video w-full object-cover transition group-hover:scale-[1.02]" />
                        @else
                            <div class="flex aspect-video w-full items-center justify-center text-4xl" style="background: color-mix(in srgb, var(--accent) 10%, white);">
                                🎭
                            </div>
                        @endif
                        <div class="p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide" style="color: var(--accent);">
                                {{ $event->date->locale('de')->isoFormat('dd, D. MMMM Y') }}
                            </p>
                            <h2 class="mt-1 text-lg font-semibold text-gray-900">{{ $event->name }}</h2>
                            @if ($event->venue)
                                <p class="text-sm text-gray-500">{{ $event->venue->name }}</p>
                            @endif
                            @if ($event->slots->isNotEmpty())
                                <p class="mt-2 text-xs text-gray-500">
                                    {{ $event->slots->map(fn ($s) => $s->name . ' ' . substr($s->time_start, 0, 5) . ' Uhr')->implode(' · ') }}
                                </p>
                            @endif
                            @if (!$event->isOrderable())
                                <p class="mt-2 inline-block rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-700">
                                    Bestellschluss erreicht
                                </p>
                            @else
                                <p class="mt-3 text-sm font-medium group-hover:underline" style="color: var(--accent);">
                                    Jetzt vorbestellen →
                                </p>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
</div>
