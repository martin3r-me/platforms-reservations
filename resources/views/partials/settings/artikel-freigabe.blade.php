{{-- Vier-Augen-Prinzip bei der Artikelfreigabe.

     Kein Häkchen am Speichern-Knopf: Der Schalter IST die Kontrolle. Wer ihn
     beiläufig mit allen anderen Einstellungen umlegen kann, hat die Kontrolle
     nicht – dann wäre das Abschalten der bequemste Weg, den eigenen Artikel
     durchzuwinken. Deshalb drei getrennte Handlungen und eine geschützte
     Richtung: Abschalten braucht zwei Menschen, Einschalten wirkt sofort. --}}
@php
    $fs      = $this->freigabeSetting;
    $gilt    = $fs->fourEyesRequired();
    $offen   = $fs->fourEyesOffPending();
    $istMein = $offen && (int) $fs->four_eyes_off_requested_by === (int) auth()->id();
@endphp

<x-nx-card flush>
    <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-3">
        @svg('heroicon-o-shield-check', 'w-4 h-4 text-[color:var(--nx-muted)]')
        <h2 class="m-0 text-xs font-semibold text-[color:var(--nx-text)]">Artikelfreigabe</h2>
        <span class="ml-auto text-[11px] text-[color:var(--nx-muted)]">Vier-Augen-Prinzip</span>
    </div>

    <div class="space-y-4 p-5">
        {{-- Eigene Fläche statt nackter Zeile: als Fließtext klebte die Meldung
             an der Statuszeile darunter und las sich wie deren erste Zeile. --}}
        @if ($freigabeMeldung !== '')
            <p class="m-0 rounded-md px-3 py-2 text-xs leading-relaxed"
               style="color: var({{ $freigabeMeldungIstFehler ? '--nx-danger' : '--nx-success' }});
                      background: {{ $freigabeMeldungIstFehler ? 'rgba(224,49,49,.08)' : 'rgba(47,158,68,.08)' }};">
                {{ $freigabeMeldung }}
            </p>
        @endif

        <div class="flex items-start gap-2">
            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" style="background:{{ $gilt ? '#2f9e44' : '#868e96' }}"></span>
            <div>
                <p class="m-0 text-sm font-medium text-[color:var(--nx-text)]">
                    {{ $gilt ? 'Freigabepflicht gilt' : 'Freigabepflicht ist abgeschaltet' }}
                </p>
                <p class="m-0 mt-0.5 text-[11px] leading-relaxed text-[color:var(--nx-muted)]">
                    {{ $gilt
                        ? 'Ein Artikel wird zur Prüfung eingereicht und muss von einer anderen Person freigegeben werden, bevor Gäste ihn bestellen können.'
                        : 'Wer einen Artikel einreicht, kann ihn auch selbst freigeben.' }}
                </p>
            </div>
        </div>

        {{-- Der Beleg bleibt stehen: Wer die Pflicht wann gelockert hat, soll
             dauerhaft lesbar sein – das macht den heimlichen Weg unattraktiv,
             ohne ihn zu versperren. --}}
        @if ($fs->four_eyes_changed_at)
            <p class="m-0 text-[11px] text-[color:var(--nx-faint)]">
                Zuletzt geändert am {{ $fs->four_eyes_changed_at->format('d.m.Y') }} um {{ $fs->four_eyes_changed_at->format('H:i') }} Uhr
                @if ($fs->changedBy) von {{ $fs->changedBy->name }} @endif
                @if ($fs->changedWith) · beantragt von {{ $fs->changedWith->name }} @endif
            </p>
        @endif

        @if ($gilt && ! $offen)
            <div class="border-t border-[color:var(--nx-line)] pt-4">
                <p class="m-0 mb-2 text-[11px] leading-relaxed text-[color:var(--nx-muted)]">
                    Abschalten geht nicht allein: Eine Person beantragt es, eine <strong>andere</strong> bestätigt.
                    Bis dahin gilt die Pflicht unverändert weiter.
                </p>
                <x-nx-button variant="secondary" wire:click="requestFourEyesOff">
                    Abschalten beantragen
                </x-nx-button>
            </div>
        @endif

        @if ($offen)
            <x-nx-callout variant="warning" title="Abschalten beantragt">
                {{ $fs->offRequestedBy?->name ?? 'Jemand' }} hat am
                {{ $fs->four_eyes_off_requested_at?->format('d.m.Y') }} um
                {{ $fs->four_eyes_off_requested_at?->format('H:i') }} Uhr beantragt, die Freigabepflicht
                abzuschalten. Sie gilt weiter, bis eine <strong>andere</strong> Person bestätigt.
            </x-nx-callout>

            <div class="flex flex-wrap items-center gap-2">
                @if ($istMein)
                    <p class="m-0 text-[11px] text-[color:var(--nx-muted)]">
                        Sie haben den Antrag gestellt – bestätigen muss ihn jemand anderes.
                    </p>
                @else
                    <x-nx-button variant="danger" wire:click="confirmFourEyesOff">
                        Abschalten bestätigen
                    </x-nx-button>
                @endif
                <x-nx-button variant="ghost" wire:click="withdrawFourEyesOff">
                    Antrag zurückziehen
                </x-nx-button>
            </div>
        @endif

        @if (! $gilt)
            <div class="border-t border-[color:var(--nx-line)] pt-4">
                <p class="m-0 mb-2 text-[11px] leading-relaxed text-[color:var(--nx-muted)]">
                    Wieder einschalten geht sofort und allein – strenger werden ist nie heikel.
                    Artikel, die schon freigegeben sind, bleiben es.
                </p>
                <x-nx-button variant="secondary" wire:click="enableFourEyes">
                    Freigabepflicht wieder einschalten
                </x-nx-button>
            </div>
        @endif
    </div>
</x-nx-card>
