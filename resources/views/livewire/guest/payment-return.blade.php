<div class="min-h-screen bg-gray-50 dark:bg-gray-950">
    <div class="mx-auto max-w-lg px-4 py-12">
        @php
            $B = \Platform\Reservation\Models\Booking::class;
            $booking = $this->booking;
            $paymentStatus = $booking?->payment?->status;
            $isPaid   = $booking && $booking->status === $B::STATUS_CONFIRMED;
            $isFailed = $booking && ($booking->status === $B::STATUS_CANCELLED
                        || in_array($paymentStatus, ['failed', 'canceled', 'expired'], true));
            $hasMollie = $booking && $booking->mollie_payment_id;
        @endphp

        @if (!$booking)
            <div class="rounded-2xl border border-dashed border-gray-300 bg-white py-16 text-center dark:border-gray-700 dark:bg-gray-900">
                <div class="text-5xl mb-4">🔍</div>
                <h1 class="text-lg font-semibold dark:text-white">Buchung nicht gefunden</h1>
                <a href="{{ route('reservation.guest.events.index') }}" class="mt-4 inline-block text-sm text-[var(--ui-primary)] hover:underline">Zur Terminübersicht</a>
            </div>
        @elseif ($isPaid)
            <div class="rounded-2xl border border-[var(--ui-border)]/40 bg-white p-8 text-center shadow-sm dark:bg-gray-900">
                <div class="text-5xl mb-4">✅</div>
                <h1 class="text-xl font-bold text-[var(--ui-secondary)] m-0">Zahlung erfolgreich!</h1>
                <p class="mt-2 text-sm text-[var(--ui-muted)]">
                    Vielen Dank, {{ $booking->guest_name }}. Ihre Bestellung für
                    <strong>{{ $booking->event?->name }}</strong> am {{ $booking->date->format('d.m.Y') }}
                    @if ($booking->slot) ({{ $booking->slot->name }}, {{ substr($booking->slot->time_start, 0, 5) }} Uhr) @endif
                    ist bezahlt und bestätigt.
                </p>
                <p class="mt-1 text-xs text-[var(--ui-muted)]">Buchungsnummer: <code class="rounded bg-[var(--ui-muted-5)] px-1.5 py-0.5">{{ $booking->uuid }}</code></p>
                <p class="mt-1 text-xs text-[var(--ui-muted)]">Eine Bestätigung erhalten Sie per E-Mail.</p>
                <a href="{{ route('reservation.guest.events.index') }}" class="mt-6 inline-block rounded-xl border border-[var(--ui-border)] px-6 py-3 text-sm font-medium text-[var(--ui-secondary)]">Zur Terminübersicht</a>
            </div>
        @elseif ($isFailed)
            <div class="rounded-2xl border border-[var(--ui-danger)]/30 bg-white p-8 text-center shadow-sm dark:bg-gray-900">
                <div class="text-5xl mb-4">⚠️</div>
                <h1 class="text-xl font-bold text-[var(--ui-secondary)] m-0">Zahlung nicht abgeschlossen</h1>
                <p class="mt-2 text-sm text-[var(--ui-muted)]">
                    Ihre Zahlung wurde nicht abgeschlossen, daher konnte die Bestellung nicht bestätigt werden.
                    Sie können es erneut versuchen.
                </p>
                <a href="{{ route('reservation.guest.checkout', $booking->event?->uuid) }}" class="mt-6 inline-block rounded-xl bg-[var(--ui-primary)] px-6 py-3 text-sm font-bold text-white hover:opacity-90">Erneut versuchen</a>
            </div>
        @elseif (!$hasMollie)
            {{-- Buchung ohne Mollie-Zahlung (Demo/0 €) – kein Poll, neutrale Bestätigung --}}
            <div class="rounded-2xl border border-[var(--ui-border)]/40 bg-white p-8 text-center shadow-sm dark:bg-gray-900">
                <div class="text-5xl mb-4">✅</div>
                <h1 class="text-xl font-bold text-[var(--ui-secondary)] m-0">Bestellung eingegangen</h1>
                <p class="mt-2 text-sm text-[var(--ui-muted)]">
                    Vielen Dank, {{ $booking->guest_name }}. Ihre Bestellung ist eingegangen.
                </p>
                <p class="mt-1 text-xs text-[var(--ui-muted)]">Buchungsnummer: <code class="rounded bg-[var(--ui-muted-5)] px-1.5 py-0.5">{{ $booking->uuid }}</code></p>
                <a href="{{ route('reservation.guest.events.index') }}" class="mt-6 inline-block rounded-xl border border-[var(--ui-border)] px-6 py-3 text-sm font-medium text-[var(--ui-secondary)]">Zur Terminübersicht</a>
            </div>
        @else
            {{-- offen / in Bearbeitung – poll per kurzem Reload --}}
            <div class="rounded-2xl border border-[var(--ui-border)]/40 bg-white p-8 text-center shadow-sm dark:bg-gray-900"
                wire:poll.4s="refresh">
                <div class="text-5xl mb-4">⏳</div>
                <h1 class="text-xl font-bold text-[var(--ui-secondary)] m-0">Zahlung wird verarbeitet…</h1>
                <p class="mt-2 text-sm text-[var(--ui-muted)]">
                    Wir warten auf die Bestätigung Ihrer Zahlung. Diese Seite aktualisiert sich automatisch.
                </p>
                <p class="mt-1 text-xs text-[var(--ui-muted)]">Buchungsnummer: <code class="rounded bg-[var(--ui-muted-5)] px-1.5 py-0.5">{{ $booking->uuid }}</code></p>
            </div>
        @endif
    </div>
</div>
