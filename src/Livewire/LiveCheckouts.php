<?php

namespace Platform\Reservation\Livewire;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Services\LiveCheckoutService;

/**
 * Wer gerade im Bestellweg steht – als eigene Komponente im VA-Dashboard.
 *
 * Eigene Komponente mit Absicht: Die Karte lädt sich alle 15 Sekunden selbst
 * nach. Hinge das `wire:poll` am VA-Dashboard, rechnete es dabei jedes Mal
 * Buchungen, Auslastung und Raumempfehlung mit – vier Mal pro Minute, obwohl
 * sich davon nichts geändert hat. So bleibt das Nachladen eine Abfrage.
 *
 * Nur lesend. Es gibt hier nichts zu tun: Ein laufender Bestellweg gehört dem
 * Gast, das Haus schaut zu.
 */
class LiveCheckouts extends Component
{
    #[Locked]
    public int $eventId;

    /**
     * Der Termin im Team des Anwenders – oder null.
     *
     * Null statt 404: Die Komponente hängt in einer Seite, die den Zugriff
     * schon geprüft hat. Ein zweiter Abbruch an dieser Stelle würde beim
     * Nachladen die ganze Seite mitreißen.
     */
    #[Computed]
    public function event(): ?Event
    {
        return Event::forTeam(Auth::user()?->current_team_id ?? 0)->find($this->eventId);
    }

    /** @return Collection<int, \Platform\Reservation\Models\CheckoutSession> */
    #[Computed]
    public function laufende(): Collection
    {
        $event = $this->event;

        return $event ? app(LiveCheckoutService::class)->laufende($event) : collect();
    }

    /** @return array{anzahl: int, gaeste: int, warenkorb: float} */
    #[Computed]
    public function summe(): array
    {
        return app(LiveCheckoutService::class)->zusammenfassung($this->laufende);
    }

    public function render()
    {
        return view('reservation::livewire.live-checkouts');
    }
}
