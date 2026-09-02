<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="PausePlus" icon="heroicon-o-home" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Dashboard'],
        ]">
            <x-nx-button variant="primary" :href="route('reservation.events.index')" wire:navigate>
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Termin anlegen</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
    <div class="space-y-6">

    {{-- Kennzahlen --}}
    <x-nx-stat-grid>
        {{-- Posteingang statt „Offene Buchungen": Die alte Kachel zählte
             ausstehende Buchungen und schrieb „warten auf Bestätigung" darunter -
             eine Aufgabe, die es nicht gibt. Bestätigt wird durch Mollie oder gar
             nicht. Hier steht jetzt etwas, das man tun kann.

             Der Akzent zieht nur an, wenn wirklich etwas liegt; bei null bleibt
             die Kachel ruhig, statt mit Warnfarbe Aufmerksamkeit zu fordern. --}}
        <x-nx-stat label="Posteingang" :value="(string) $this->stats->unseen_inbox"
            :hint="$this->stats->unseen_inbox === 0
                ? 'alles gesehen'
                : ($this->stats->unseen_inbox === 1 ? 'Vorgang ungesehen' : 'Vorgänge ungesehen')"
            icon="heroicon-o-inbox"
            :accent="$this->stats->unseen_inbox > 0 ? 'var(--nx-warning)' : 'var(--nx-muted)'"
            :href="route('reservation.inbox.index')" wire:navigate />
        <x-nx-stat label="Kommende Termine" :value="(string) $this->stats->upcoming_events"
            icon="heroicon-o-ticket" accent="var(--nx-accent)" :href="route('reservation.events.index')" wire:navigate />
        <x-nx-stat label="Umsatz im Monat" :value="number_format($this->stats->month_revenue, 2, ',', '.') . ' €'" :hint="now()->locale('de')->isoFormat('MMMM Y')"
            icon="heroicon-o-banknotes" accent="var(--nx-success)" :href="route('reservation.finance.index')" wire:navigate />
        <x-nx-stat label="Freigegebene Artikel" :value="$this->stats->approved_items . ' / ' . $this->stats->total_items"
            :hint="$this->stats->awaiting_me > 0
                ? ($this->stats->awaiting_me === 1 ? '1 wartet auf Ihre Freigabe' : $this->stats->awaiting_me . ' warten auf Ihre Freigabe')
                : ($this->stats->four_eyes ? 'Vier-Augen-Freigabe' : 'Freigabe ohne Vier-Augen-Prinzip')"
            icon="heroicon-o-rectangle-stack" accent="var(--nx-info)" :href="route('reservation.menu.index')" wire:navigate />
    </x-nx-stat-grid>

    {{-- Die beiden Karten stehen nebeneinander und sollen gleich hoch sein.

         Gleich viele Zeilen genuegt dafuer nicht: Eine Status-Marke ist hoeher
         als der Text daneben und zieht ihre Zeile mit hoch. Rechts hat JEDE
         Buchung eine, links nur der seltene abgesagte Termin - acht kleine
         Unterschiede ergeben einen sichtbaren.

         Eine Mindesthoehe allein reicht dagegen nicht: Sie hebt die kurzen
         Zeilen an, laesst die hohen aber hoch. Also bekommt die ERSTE ZEILE
         eine feste Hoehe. Die Marke wird darin mittig gesetzt und waechst nicht
         mehr in die Zeilenhoehe hinein; beide Kartenzeilen sind damit gleich
         hoch, unabhaengig davon, ob eine Marke darin steht. Die Mindesthoehe
         bleibt als Boden fuer Zeilen ohne zweite Textzeile.

         Nebenbei springt die linke Karte nicht mehr, sobald ein Termin
         abgesagt wird.

         Als style-Attribut und NICHT als Tailwind-Klasse: Die App erzeugt ihr
         CSS beim Bauen und liest dafuer auch die Ansichten der Module
         (@source in resources/css/app.css). Ein Modul-Bump tauscht aber nur
         PHP - eine Klasse, die es im gebauten Stylesheet noch nicht gibt,
         bleibt bis zum naechsten CSS-Build wirkungslos. Genau das ist beim
         ersten Anlauf passiert. Vorhandene Klassen sind unbedenklich, neue
         nicht; im Zweifel steht die Regel direkt am Element. --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {{-- Nächste Termine --}}
        <x-nx-card flush>
            <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
                @svg('heroicon-o-ticket', 'w-4 h-4 text-[color:var(--nx-muted)]')
                <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-muted)]">Nächste Termine</h2>
                <a href="{{ route('reservation.events.index') }}" wire:navigate class="ml-auto text-xs text-[color:var(--nx-muted)] transition-colors hover:text-[color:var(--nx-text)]">Alle</a>
            </div>
            <div>
                @forelse ($this->upcomingEvents as $event)
                    <a href="{{ route('reservation.events.dashboard', $event->id) }}" wire:navigate wire:key="dash-event-{{ $event->id }}"
                        style="min-height:3.5rem"
                        class="flex items-center justify-between gap-3 border-b border-[color:var(--nx-line)] px-4 py-2.5 transition-colors last:border-0 hover:bg-[color:var(--nx-hover)]">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2" style="height:1.25rem">
                                <span class="truncate text-sm font-medium text-[color:var(--nx-text)]">{{ $event->name }}</span>
                                {{-- Der Status, wie er heisst - nicht "alles ausser
                                     veroeffentlicht ist ein Entwurf".

                                     Hier stand fest "Entwurf", und damit trug ein
                                     ABGESAGTER Termin dieselbe Marke wie ein
                                     unfertiger. Wer die Kachel las, sah einen
                                     Termin, der noch kommt und nur noch
                                     veroeffentlicht werden muss - dabei findet er
                                     gar nicht statt. Dasselbe galt fuer "Bald
                                     verfuegbar" und "Bestellschluss".

                                     Beschriftung und Farbe kommen aus EventStatus,
                                     wo sie ohnehin fuer die Terminliste stehen. Eine
                                     zweite Zuordnung hier liefe wieder auseinander.

                                     Veroeffentlicht bleibt ohne Marke: Das ist der
                                     Normalfall, und fuenf gruene Marken untereinander
                                     sagen weniger als eine rote dazwischen. --}}
                                @if ($event->status->value !== 'published')
                                    <x-nx-badge :variant="$event->status->badgeVariant()">{{ $event->status->label() }}</x-nx-badge>
                                @endif
                            </div>
                            <p class="m-0 mt-0.5 text-xs text-[color:var(--nx-muted)]">
                                {{ $event->date->locale('de')->isoFormat('dd, D. MMM') }}
                                @if ($event->venue) · {{ $event->venue->name }} @endif
                                · {{ $event->bookings_count }} {{ $event->bookings_count === 1 ? 'Buchung' : 'Buchungen' }}
                            </p>
                        </div>
                        @svg('heroicon-o-chevron-right', 'w-4 h-4 shrink-0 text-[color:var(--nx-faint)]')
                    </a>
                @empty
                    <x-nx-empty icon="heroicon-o-ticket">
                        Keine kommenden Termine
                        <x-slot name="action">
                            <a href="{{ route('reservation.events.index') }}" wire:navigate class="text-xs text-[color:var(--nx-text)] hover:underline">Termin anlegen</a>
                        </x-slot>
                    </x-nx-empty>
                @endforelse
            </div>
        </x-nx-card>

        {{-- Neueste Buchungen --}}
        <x-nx-card flush>
            <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
                @svg('heroicon-o-calendar-days', 'w-4 h-4 text-[color:var(--nx-muted)]')
                <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-muted)]">Neueste Buchungen</h2>
                <a href="{{ route('reservation.bookings.index') }}" wire:navigate class="ml-auto text-xs text-[color:var(--nx-muted)] transition-colors hover:text-[color:var(--nx-text)]">Alle</a>
            </div>
            <div>
                @forelse ($this->recentBookings as $booking)
                    <div wire:key="dash-booking-{{ $booking->id }}" style="min-height:3.5rem"
                        class="flex items-center justify-between gap-3 border-b border-[color:var(--nx-line)] px-4 py-2.5 last:border-0">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2" style="height:1.25rem">
                                <span class="truncate text-sm font-medium text-[color:var(--nx-text)]">{{ $booking->guest_name }}</span>
                                @php
                                    [$statusLabel, $statusVariant] = [
                                        'pending'   => ['Ausstehend', 'warning'],
                                        'confirmed' => ['Bestätigt', 'success'],
                                        'cancelled' => ['Storniert', 'danger'],
                                        'no_show'   => ['No-Show', 'neutral'],
                                        'completed' => ['Abgeschlossen', 'info'],
                                    ][$booking->status] ?? [ucfirst($booking->status), 'neutral'];
                                @endphp
                                <x-nx-badge :variant="$statusVariant">{{ $statusLabel }}</x-nx-badge>
                            </div>
                            <p class="m-0 mt-0.5 text-xs text-[color:var(--nx-muted)]">
                                {{ $booking->date->format('d.m.Y') }}
                                @if ($booking->event) · {{ $booking->event->name }} @endif
                                @if ($booking->zielortLabel()) · {{ $booking->zielortLabel() }}@if ($booking->zielortFehlt())<span class="ml-1 text-[11px] text-[color:var(--nx-faint)]">(gelöscht)</span>@endif @endif
                                · {{ $booking->guest_count }} P.
                            </p>
                        </div>
                        {{-- Betrag und darunter, wann bestellt wurde.

                             Die Karte sortiert nach dem Bestellzeitpunkt (latest()), zeigte
                             ihn aber nirgends: Links steht das Datum der VERANSTALTUNG, und
                             wenn alle Zeilen zum selben Abend gehoeren, sieht die Reihenfolge
                             willkuerlich aus. Jetzt ist der Sortierschluessel sichtbar.

                             Rechts und nicht in der Zeile links, damit die ohnehin lange
                             Zeile aus Datum, Termin, Ort und Personenzahl nicht umbricht. --}}
                        <div class="shrink-0 whitespace-nowrap text-right">
                            @if ($booking->items_count > 0)
                                <span class="text-xs font-semibold tabular-nums text-[color:var(--nx-text)]">
                                    {{ number_format($booking->total_amount, 2, ',', '.') }} €
                                </span>
                            @endif
                            @if ($booking->created_at)
                                <span class="block text-[11px] tabular-nums text-[color:var(--nx-faint)]">
                                    {{ $booking->created_at->format('d.m.') }} · {{ $booking->created_at->format('H:i') }} Uhr
                                </span>
                            @endif
                        </div>
                    </div>
                @empty
                    <x-nx-empty icon="heroicon-o-inbox">Noch keine Buchungen</x-nx-empty>
                @endforelse
            </div>
        </x-nx-card>
    </div>

    </div>
    </x-ui-page-container>
</x-ui-page>
