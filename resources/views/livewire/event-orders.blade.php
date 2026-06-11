<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="'Küche – ' . $this->event->name" icon="heroicon-o-fire" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Termine', 'href' => route('reservation.events.index')],
            ['label' => $this->event->name],
        ]">
            <x-ui-button variant="secondary-outline" size="sm" onclick="window.print()">
                @svg('heroicon-o-printer', 'w-4 h-4')
                <span>Drucken</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
    <div class="pt-4 space-y-4">

    {{-- Kennzahlen --}}
    @php $totals = $this->slotStats->get(0); @endphp
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm p-3">
            <span class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)]">Termin</span>
            <p class="m-0 mt-1 text-sm font-bold text-[var(--ui-secondary)]">{{ $this->event->date->format('d.m.Y') }}</p>
        </div>
        <div class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm p-3">
            <span class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)]">Buchungen</span>
            <p class="m-0 mt-1 text-lg font-bold tabular-nums text-[var(--ui-secondary)]">{{ $totals?->bookings ?? 0 }}</p>
        </div>
        <div class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm p-3">
            <span class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)]">Gäste</span>
            <p class="m-0 mt-1 text-lg font-bold tabular-nums text-[var(--ui-secondary)]">{{ $totals?->guests ?? 0 }}</p>
        </div>
        <div class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm p-3">
            <span class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)]">Bestellte Artikel</span>
            <p class="m-0 mt-1 text-lg font-bold tabular-nums text-[var(--ui-secondary)]">{{ $this->totalQuantity }}</p>
        </div>
    </div>

    @if ($this->itemsByCategory->isEmpty())
        <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm">
            <div class="flex flex-col items-center justify-center py-16 text-[var(--ui-muted)]">
                @svg('heroicon-o-inbox', 'w-10 h-10 mb-3 opacity-40')
                <span class="text-sm font-medium text-[var(--ui-secondary)]">Noch keine Bestellungen</span>
                <span class="text-xs mt-1 opacity-70">Sobald Gäste vorbestellen, erscheint hier die Bereitstellungsliste.</span>
            </div>
        </section>
    @else
        {{-- Bereitstellungsliste --}}
        <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
                @svg('heroicon-o-fire', 'w-4 h-4 text-[var(--ui-muted)]')
                <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">
                    Bereitstellung je Pause
                </h2>
                <span class="ml-auto text-[11px] text-[var(--ui-muted)]">ohne stornierte Buchungen / No-Shows</span>
            </div>
            <x-ui-table compact="true">
                <x-ui-table-header>
                    <x-ui-table-header-cell compact="true">Artikel</x-ui-table-header-cell>
                    @foreach ($this->event->slots as $slot)
                        <x-ui-table-header-cell compact="true" align="center">
                            {{ $slot->name }}<br>
                            <span class="font-normal normal-case">{{ substr($slot->time_start, 0, 5) }} Uhr</span>
                        </x-ui-table-header-cell>
                    @endforeach
                    <x-ui-table-header-cell compact="true" align="center">Gesamt</x-ui-table-header-cell>
                </x-ui-table-header>
                <x-ui-table-body>
                    @foreach ($this->itemsByCategory as $categoryName => $items)
                        <tr>
                            <td colspan="{{ 2 + $this->event->slots->count() }}"
                                class="bg-[var(--ui-muted-5)] px-2 py-1.5 text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)]">
                                {{ $categoryName }}
                            </td>
                        </tr>
                        @foreach ($items as $item)
                            @php $bySlot = $this->quantities->get($item->id, collect()); @endphp
                            <x-ui-table-row compact="true" wire:key="order-item-{{ $item->id }}">
                                <x-ui-table-cell compact="true">
                                    <span class="font-medium text-[var(--ui-secondary)]">{{ $item->name }}</span>
                                    @if ($item->is_alcoholic)
                                        <x-ui-badge variant="info" size="xs">18+</x-ui-badge>
                                    @endif
                                </x-ui-table-cell>
                                @foreach ($this->event->slots as $slot)
                                    <x-ui-table-cell compact="true" align="center">
                                        <span class="tabular-nums {{ $bySlot->get($slot->id) ? 'text-[var(--ui-secondary)]' : 'text-[var(--ui-muted)] opacity-50' }}">
                                            {{ $bySlot->get($slot->id, 0) }}
                                        </span>
                                    </x-ui-table-cell>
                                @endforeach
                                <x-ui-table-cell compact="true" align="center">
                                    <span class="font-bold tabular-nums text-[var(--ui-secondary)]">{{ $bySlot->sum() }}</span>
                                </x-ui-table-cell>
                            </x-ui-table-row>
                        @endforeach
                    @endforeach

                    {{-- Summenzeile Buchungen/Gäste je Slot --}}
                    <tr class="border-t border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)]">
                        <td class="px-2 py-1.5 text-xs font-semibold text-[var(--ui-secondary)]">Buchungen / Gäste</td>
                        @foreach ($this->event->slots as $slot)
                            @php $stat = $this->slotStats->get($slot->id); @endphp
                            <td class="px-2 py-1.5 text-center text-xs tabular-nums text-[var(--ui-muted)]">
                                {{ $stat?->bookings ?? 0 }} / {{ $stat?->guests ?? 0 }}
                            </td>
                        @endforeach
                        <td class="px-2 py-1.5 text-center text-xs font-semibold tabular-nums text-[var(--ui-secondary)]">
                            {{ $totals?->bookings ?? 0 }} / {{ $totals?->guests ?? 0 }}
                        </td>
                    </tr>
                </x-ui-table-body>
            </x-ui-table>
        </section>
    @endif

    </div>
    </x-ui-page-container>
</x-ui-page>
