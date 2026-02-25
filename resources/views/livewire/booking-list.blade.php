<div class="p-4 space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold dark:text-white">Buchungen</h1>
        <a href="{{ route('reservation.bookings.create') }}"
            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            + Neue Buchung
        </a>
    </div>

    {{-- Filter --}}
    <div class="flex flex-wrap gap-2">
        <input type="date" wire:model.live="filterDate"
            class="rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
        <select wire:model.live="filterStatus"
            class="rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
            <option value="">Alle Status</option>
            <option value="pending">Ausstehend</option>
            <option value="confirmed">Bestätigt</option>
            <option value="cancelled">Storniert</option>
            <option value="no_show">No-Show</option>
            <option value="completed">Abgeschlossen</option>
        </select>
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Name oder E-Mail suchen…"
            class="flex-1 min-w-[200px] rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
    </div>

    {{-- Tabelle --}}
    <div class="overflow-x-auto rounded-xl border dark:border-gray-700">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Datum</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Uhrzeit</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Tisch</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Gast</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Personen</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Aktionen</th>
                </tr>
            </thead>
            <tbody class="divide-y dark:divide-gray-700">
                @forelse ($this->bookings as $booking)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="px-4 py-3 dark:text-white">{{ $booking->date->format('d.m.Y') }}</td>
                        <td class="px-4 py-3 dark:text-white">{{ $booking->time_start }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $booking->table?->label }}</td>
                        <td class="px-4 py-3">
                            <div class="font-medium dark:text-white">{{ $booking->guest_name }}</div>
                            @if ($booking->guest_email)
                                <div class="text-xs text-gray-500">{{ $booking->guest_email }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center dark:text-white">{{ $booking->guest_count }}</td>
                        <td class="px-4 py-3">
                            @php
                                $statusColors = [
                                    'pending'   => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
                                    'confirmed' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
                                    'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
                                    'no_show'   => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                    'completed' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
                                ];
                            @endphp
                            <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium {{ $statusColors[$booking->status] ?? '' }}">
                                {{ ucfirst($booking->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex gap-1">
                                @if ($booking->status === 'pending')
                                    <button wire:click="confirmBooking({{ $booking->id }})"
                                        class="rounded px-2 py-1 text-xs bg-green-600 text-white hover:bg-green-700">Bestätigen</button>
                                    <button wire:click="cancelBooking({{ $booking->id }})"
                                        wire:confirm="Buchung wirklich stornieren?"
                                        class="rounded px-2 py-1 text-xs bg-red-600 text-white hover:bg-red-700">Stornieren</button>
                                @elseif ($booking->status === 'confirmed')
                                    <button wire:click="markCompleted({{ $booking->id }})"
                                        class="rounded px-2 py-1 text-xs bg-blue-600 text-white hover:bg-blue-700">Abschließen</button>
                                    <button wire:click="markNoShow({{ $booking->id }})"
                                        class="rounded px-2 py-1 text-xs bg-gray-500 text-white hover:bg-gray-600">No-Show</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                            Keine Buchungen gefunden.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $this->bookings->links() }}
    </div>
</div>
