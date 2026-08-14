<?php

namespace Platform\Reservation\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Services\KitchenPrepService;

/**
 * Küchen-Übersicht: Gesamtbestellungen eines Termins, aufgeschlüsselt
 * nach Pausen-Slot – damit die Küche bereitstellen kann.
 *
 * Zählt alle aktiven Buchungen (ohne storniert/No-Show).
 */
class EventOrders extends Component
{
    #[Locked]
    public int $eventId;

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
        $this->event; // Team-Scope prüfen (404 bei fremdem Team)
    }

    #[Computed]
    public function event(): Event
    {
        return Event::forTeam(Auth::user()?->current_team_id ?? 0)
            ->with('slots')
            ->findOrFail($this->eventId);
    }

    /**
     * Vorbereitungsplan je Pause – aus KitchenPrepService.
     *
     * Die Rechnung liegt im Service, weil der Freigabe-Link für
     * Veranstaltungsleiter dieselbe Ansicht ohne Anmeldung braucht.
     *
     * @return \Illuminate\Support\Collection<int, array{slot: mixed, total: int, groups: \Illuminate\Support\Collection}>
     */
    #[Computed]
    public function prepBySlot(): \Illuminate\Support\Collection
    {
        return app(KitchenPrepService::class)->prepBySlot($this->event);
    }

    /** Buchungen und Gäste je Pause; Schlüssel 0 trägt die Gesamtzahlen. */
    #[Computed]
    public function slotStats(): \Illuminate\Support\Collection
    {
        return app(KitchenPrepService::class)->slotStats($this->event);
    }

    public function render()
    {
        return view('reservation::livewire.event-orders')
            ->layout('platform::layouts.app');
    }
}
