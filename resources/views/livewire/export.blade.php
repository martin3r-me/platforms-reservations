<div class="p-4 space-y-6">
    <h1 class="text-xl font-semibold dark:text-white">Export</h1>

    <div class="rounded-xl border p-4 space-y-4 dark:border-gray-700">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm text-gray-700 dark:text-gray-300">Von</label>
                <input wire:model.live="dateFrom" type="date"
                    class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
            </div>
            <div>
                <label class="block text-sm text-gray-700 dark:text-gray-300">Bis</label>
                <input wire:model.live="dateTo" type="date"
                    class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm text-gray-700 dark:text-gray-300">Status</label>
                <select wire:model.live="filterStatus"
                    class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    <option value="">Alle Status</option>
                    <option value="pending">Ausstehend</option>
                    <option value="confirmed">Bestätigt</option>
                    <option value="cancelled">Storniert</option>
                    <option value="no_show">No-Show</option>
                    <option value="completed">Abgeschlossen</option>
                </select>
            </div>
            <div>
                <label class="block text-sm text-gray-700 dark:text-gray-300">Format</label>
                <select wire:model="format"
                    class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    <option value="csv">CSV (Excel)</option>
                    <option value="json">JSON</option>
                </select>
            </div>
        </div>

        <div class="flex items-center justify-between rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                <strong class="text-gray-900 dark:text-white">{{ $this->previewCount }}</strong>
                Buchungen im Zeitraum
            </p>
            <button
                wire:click="export"
                class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                @if ($this->previewCount === 0) disabled @endif
            >
                Exportieren
            </button>
        </div>
    </div>

    {{-- Felder-Übersicht --}}
    <div class="rounded-xl border p-4 dark:border-gray-700">
        <h2 class="mb-3 text-sm font-semibold dark:text-white">Exportierte Felder</h2>
        <div class="flex flex-wrap gap-2">
            @foreach(['Buchungs-ID', 'Datum', 'Uhrzeit', 'Tisch', 'Venue', 'Gast', 'E-Mail', 'Telefon', 'Personen', 'Status', 'Betrag', 'Zahlungsart', 'Mollie-ID', 'Steuersatz', 'Erstellt'] as $field)
                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                    {{ $field }}
                </span>
            @endforeach
        </div>
    </div>
</div>
