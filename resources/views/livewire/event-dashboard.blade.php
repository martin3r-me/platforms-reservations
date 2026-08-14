<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$this->event->name" icon="heroicon-o-fire" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Veranstaltungen', 'href' => route('reservation.operations.index')],
            ['label' => $this->event->name],
        ]">
            {{-- Am Knopf ablesbar, ob schon ein Zugang besteht – sonst muesste man
                 den Dialog oeffnen, um es herauszufinden. Punktfarbe per style
                 statt Utility-Klasse: haengt so nicht am CSS-Build. --}}
            @php $zugang = $this->event; @endphp
            <x-nx-button wire:click="openShareModal()">
                @if ($zugang->shareIsActive())
                    <span class="h-2 w-2 rounded-full" style="background: var(--nx-success)"></span>
                    <span>Zugang aktiv · bis {{ $zugang->shareExpiresAt()?->format('d.m.') }}</span>
                @elseif ($zugang->share_token)
                    <span class="h-2 w-2 rounded-full" style="background: var(--nx-faint)"></span>
                    <span>Zugang abgelaufen</span>
                @else
                    @svg('heroicon-o-link', 'w-4 h-4')
                    <span>Zugang für Veranstaltungsleitung</span>
                @endif
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">

    {{-- Ultrawide-Ambient: generatives Bauhaus-Panel im Passepartout.
         Sichtbarkeit/Breite platz-getrieben (JS): füllt nur den echten freien Rand rechts,
         blendet aus sobald der Content ihn braucht (Sidebars offen). Hinter allem Interaktiven. --}}
    @verbatim
    <style>
        .pp-art{ position:fixed; top:88px; bottom:52px; right:0; z-index:0; pointer-events:none;
            display:none; padding:56px; background:#fff; border-left:1px solid var(--nx-line); }
        .pp-art .plate{ position:relative; width:100%; height:100%; overflow:hidden;
            background:var(--nx-bg); border:1px solid var(--nx-line); border-radius:2px; box-shadow:0 1px 3px rgba(0,0,0,.04); }
    </style>
    <script>
    (function(){
        if (window.__ppArtInit) return; window.__ppArtInit = true;
        var MIN = 380, MAX = 880, BUFFER = 24, t = 0;
        function apply(){
            var panel = document.querySelector('.pp-art');
            if (!panel) return;
            var content = document.querySelector('[data-nx-content]');
            if (!content){ panel.style.display = 'none'; return; }
            // freier Rand rechts der echten Content-Kante, minus Sicherheitsabstand
            var gap = window.innerWidth - content.getBoundingClientRect().right - BUFFER;
            if (gap >= MIN){ panel.style.display = 'block'; panel.style.width = Math.min(gap, MAX) + 'px'; }
            else { panel.style.display = 'none'; }
        }
        // Trailing-Debounce via setTimeout (feuert auch im Hintergrund-Tab, anders als rAF)
        // und wartet, bis der Livewire-Morph des Sidebar-Toggles eingerastet ist.
        function measure(){ clearTimeout(t); t = setTimeout(apply, 60); }
        // Neu berechnen bei jeder Layout-Änderung: Viewport, Sidebar-Toggle (DOM-Mutation,
        // meist ein instanter Livewire-Morph ohne Transition), evtl. Transition, Navigation.
        window.addEventListener('resize', measure);
        document.addEventListener('transitionend', measure, true);
        document.addEventListener('livewire:navigated', measure);
        try {
            new MutationObserver(measure).observe(document.body, {
                subtree: true, childList: true, attributes: true, attributeFilter: ['class', 'style'],
            });
        } catch (e) {}
        apply();
    })();
    </script>
    @endverbatim
    <div class="pp-art" aria-hidden="true">
        <div class="plate">
            <x-nx-bauhaus :seed="$artSeed" :count="7" />
        </div>
    </div>

    @php
        $currency = strtoupper((string) config('reservation.currency', 'EUR'));
        $sym = $currency === 'EUR' ? '€' : $currency;
        $s = $this->stats;
        $tiles = [
            ['Buchungen', $s['bookings']],
            ['Gäste', $s['guests']],
            ['Umsatz', number_format($s['revenue'], 2, ',', '.') . ' ' . $sym],
            [$s['pauses'] === 1 ? 'Pause' : 'Pausen', $s['pauses']],
        ];
        $statusColors = ['published' => '#2f9e44', 'draft' => '#868e96', 'closed' => '#e8590c', 'cancelled' => '#e03131'];
        $statusDot = $statusColors[$this->event->status->value] ?? '#868e96';
    @endphp

    <div class="space-y-6">

        {{-- Titel --}}
        <div>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                <h1 class="m-0 text-2xl font-bold tracking-tight text-[color:var(--nx-text)]">{{ $this->event->name }}</h1>
                <span class="inline-flex items-center gap-1.5 text-sm text-[color:var(--nx-muted)]">
                    <span class="h-2 w-2 rounded-full" style="background:{{ $statusDot }}"></span>{{ $this->event->status->label() }}
                </span>
                @if ($this->event->date->isToday())
                    <x-nx-badge variant="success">Heute</x-nx-badge>
                @endif
            </div>
            <p class="m-0 mt-1 text-sm text-[color:var(--nx-muted)]">
                {{ $this->event->date->format('d.m.Y') }}
                @if ($this->event->venue) · {{ $this->event->venue->name }} @endif
                @if ($this->event->slots->isNotEmpty()) · {{ $this->event->slots->map(fn ($sl) => $sl->displayLabel())->implode(', ') }} @endif
            </p>
        </div>

        @include('reservation::partials.event-tabs', ['event' => $this->event, 'active' => 'dashboard'])

        {{-- Kennzahlen --}}
        <div class="grid grid-cols-2 gap-x-4 gap-y-4 border-y border-[color:var(--nx-line)] py-4 sm:grid-cols-4">
            @foreach ($tiles as [$label, $value])
                <div wire:key="stat-{{ $loop->index }}">
                    <div class="text-2xl font-bold leading-none tabular-nums text-[color:var(--nx-text)]">{{ $value }}</div>
                    <div class="mt-1.5 text-xs text-[color:var(--nx-muted)]">{{ $label }}</div>
                </div>
            @endforeach
        </div>

        {{-- Buchungen nach Pause (eine VA kann mehrere Pausen haben) --}}
        <div class="space-y-5">
            @forelse ($this->bookingsBySlot as $group)
                <x-nx-card flush wire:key="slot-{{ $loop->index }}">
                    {{-- Pausen-Kopf --}}
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-[color:var(--nx-line)] px-4 py-3">
                        @svg('heroicon-o-clock', 'w-4 h-4 shrink-0 text-[color:var(--nx-muted)]')
                        <h2 class="m-0 text-sm font-semibold text-[color:var(--nx-text)]">{{ $group['label'] }}</h2>
                        <span class="text-xs tabular-nums text-[color:var(--nx-faint)]">
                            {{ $group['count'] }} {{ $group['count'] === 1 ? 'Buchung' : 'Buchungen' }} · {{ $group['guests'] }} Gäste · {{ number_format($group['revenue'], 2, ',', '.') }} {{ $sym }}
                        </span>
                    </div>

                    @if ($group['count'] === 0)
                        <div class="px-4 py-4 text-xs text-[color:var(--nx-faint)]">Noch keine Buchungen für diese Pause.</div>
                    @else
                        <x-nx-table>
                            <x-nx-table-header>
                                <x-nx-table-header-cell compact>Gast</x-nx-table-header-cell>
                                <x-nx-table-header-cell compact>Tisch</x-nx-table-header-cell>
                                <x-nx-table-header-cell compact align="center">Personen</x-nx-table-header-cell>
                                <x-nx-table-header-cell compact align="right">Bestellung</x-nx-table-header-cell>
                                <x-nx-table-header-cell compact>Status</x-nx-table-header-cell>
                            </x-nx-table-header>
                            <x-nx-table-body>
                                @foreach ($group['bookings'] as $b)
                                    <x-nx-table-row compact wire:key="b-{{ $b->id }}">
                                        <x-nx-table-cell compact>
                                            <span class="font-medium text-[color:var(--nx-text)]">{{ $b->guest_name }}</span>
                                            @if ($b->guest_email)<span class="block text-xs text-[color:var(--nx-faint)]">{{ $b->guest_email }}</span>@endif
                                        </x-nx-table-cell>
                                        <x-nx-table-cell compact class="text-[color:var(--nx-muted)]">{{ $b->table?->label ?? '–' }}</x-nx-table-cell>
                                        <x-nx-table-cell compact align="center" class="tabular-nums text-[color:var(--nx-muted)]">{{ $b->guest_count }}</x-nx-table-cell>
                                        <x-nx-table-cell compact align="right" class="tabular-nums text-[color:var(--nx-text)]">
                                            @if ($b->items_count > 0){{ $b->items_count }} Pos. · {{ number_format($b->total_amount, 2, ',', '.') }} {{ $sym }}@else<span class="text-[color:var(--nx-faint)]">–</span>@endif
                                        </x-nx-table-cell>
                                        <x-nx-table-cell compact>
                                            @php [$sl, $sv] = [
                                                'pending'   => ['Ausstehend', 'warning'],
                                                'confirmed' => ['Bestätigt', 'success'],
                                                'cancelled' => ['Storniert', 'danger'],
                                                'no_show'   => ['No-Show', 'neutral'],
                                                'completed' => ['Abgeschlossen', 'info'],
                                            ][$b->status] ?? [ucfirst($b->status), 'neutral']; @endphp
                                            <x-nx-badge :variant="$sv">{{ $sl }}</x-nx-badge>
                                        </x-nx-table-cell>
                                    </x-nx-table-row>
                                @endforeach
                            </x-nx-table-body>
                        </x-nx-table>
                    @endif
                </x-nx-card>
            @empty
                <x-nx-card>
                    <x-nx-empty icon="heroicon-o-calendar-days">Noch keine Pausen oder Buchungen für diesen Termin.</x-nx-empty>
                </x-nx-card>
            @endforelse
        </div>

    </div>
    </x-ui-page-container>

    {{-- Zugang für Veranstaltungsleitung: Link + PIN, nur lesend, nur Küche und
         Laufzettel. Die Buchungsliste bleibt bewusst außen vor – sie enthält
         Namen und E-Mail-Adressen, und ein Link wandert per Weiterleitung
         weiter, als gedacht. --}}
    <x-nx-modal size="md" wire:model="showShareModal">
        <x-slot name="header">Zugang für Veranstaltungsleitung</x-slot>

        @php $event = $this->event; @endphp

        {{-- Kein space-y: ui.css markiert alle Margin-Klassen !important und
             haengt damit Tailwinds space-y aus. Abstaende einzeln setzen. --}}
        <div class="text-sm">
            <p class="m-0 text-[color:var(--nx-muted)]">
                Ein Link ohne Konto, der nur <strong>Küche</strong> und <strong>Laufzettel</strong>
                dieser Veranstaltung zeigt – rein lesend. Zusätzlich gesichert durch eine PIN.
            </p>

            @if ($freshPin)
                <div class="mt-4">
                <x-nx-callout variant="warning">
                    <div class="font-semibold">PIN: <span class="tabular-nums tracking-[0.2em]">{{ $freshPin }}</span></div>
                    Bitte jetzt notieren – sie wird nur gehasht gespeichert und lässt sich später
                    nicht mehr anzeigen. Schicken Sie die PIN getrennt vom Link, etwa per Telefon.
                </x-nx-callout>
                </div>
            @endif

            @if ($event->shareIsActive())
                <div class="mt-4">
                    <div class="mb-1 text-xs uppercase tracking-wide text-[color:var(--nx-muted)]">Link</div>
                    {{-- Ganze Flaeche klickbar: Den Link von Hand zu markieren ist
                         fehleranfaellig, er ist lang und bricht ueber zwei Zeilen. --}}
                    <div
                        x-data="{ copied: false }"
                        x-on:click="navigator.clipboard.writeText('{{ $event->shareUrl() }}'); copied = true; setTimeout(() => copied = false, 2000)"
                        title="Link kopieren"
                        class="flex cursor-pointer items-start gap-2 rounded-[6px] bg-[color:var(--nx-hover)] px-3 py-2 transition-colors hover:bg-[color:var(--nx-active)]"
                    >
                        <span class="min-w-0 flex-1 break-all text-[12px] text-[color:var(--nx-text)]">{{ $event->shareUrl() }}</span>
                        <template x-if="!copied">
                            <span class="shrink-0 text-[color:var(--nx-muted)]">@svg('heroicon-o-clipboard-document', 'w-4 h-4')</span>
                        </template>
                        <template x-if="copied">
                            <span class="shrink-0 text-[color:var(--nx-success)]">@svg('heroicon-o-check', 'w-4 h-4')</span>
                        </template>
                    </div>
                    <p class="m-0 mt-2 text-xs text-[color:var(--nx-muted)]">
                        Gültig bis {{ $event->shareExpiresAt()?->format('d.m.Y') }} ·
                        erstellt {{ $event->share_created_at?->format('d.m.Y H:i') }} Uhr
                    </p>
                </div>

                @if ($this->shareAccesses->isNotEmpty())
                    <div class="mt-4">
                        <div class="mb-1 text-xs uppercase tracking-wide text-[color:var(--nx-muted)]">Letzte PIN-Eingaben</div>
                        <div class="divide-y divide-[color:var(--nx-line)] rounded-[6px] border border-[color:var(--nx-line)]">
                            @foreach ($this->shareAccesses as $zugriff)
                                <div class="flex items-center justify-between gap-3 px-3 py-1.5 text-xs" wire:key="acc-{{ $zugriff->id }}">
                                    <span class="text-[color:var(--nx-muted)]">{{ $zugriff->created_at?->format('d.m.Y H:i') }} · {{ $zugriff->ip ?: 'unbekannt' }}</span>
                                    <span class="{{ $zugriff->successful ? 'text-[color:var(--nx-success)]' : 'text-[color:var(--nx-danger)]' }}">
                                        {{ $zugriff->successful ? 'geöffnet' : 'PIN falsch' }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @elseif ($event->share_token)
                <div class="mt-4">
                <x-nx-callout variant="neutral">
                    Der Link ist abgelaufen (die Veranstaltung liegt zurück). Ein neuer lässt sich
                    jederzeit erzeugen.
                </x-nx-callout>
                </div>
            @else
                <p class="m-0 mt-4 text-[color:var(--nx-muted)]">Bisher ist kein Zugang eingerichtet.</p>
            @endif
        </div>

        <x-slot name="footer">
            @if ($event->share_token)
                <x-ui-confirm-button
                    action="revokeShareLink"
                    text="Zurückziehen"
                    confirmTitle="Zugang zurückziehen?"
                    confirmText="Der Link wird sofort ungültig. Wer ihn hat, kommt nicht mehr hinein."
                    confirmButtonText="Zurückziehen"
                />
            @endif
            <x-nx-button variant="primary" wire:click="issueShareLink()">
                {{ $event->share_token ? 'Neu erzeugen' : 'Zugang erstellen' }}
            </x-nx-button>
            <x-nx-button wire:click="closeShareModal()">Schließen</x-nx-button>
        </x-slot>
    </x-nx-modal>

</x-ui-page>
