<?php

namespace Platform\Reservation\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\Event;

/**
 * Veranstaltungen (operative Durchführung): zeigt ausschließlich Termine MIT
 * Buchungen und bündelt die operativen Aktionen für den Abend – Küche,
 * Laufzettel, Übersicht. Die Verwaltung (Anlegen/Bearbeiten/Veröffentlichen)
 * liegt getrennt in „Termine" (EventManager).
 */
class EventOperations extends Component
{
    // Default: kommende (und heutige) Veranstaltungen – das ist die operative Sicht.
    public string $timeFilter = 'upcoming'; // upcoming|past|all

    protected function getTeamId(): ?int
    {
        return Auth::user()?->current_team_id;
    }

    #[Computed]
    public function events(): \Illuminate\Database\Eloquent\Collection
    {
        $today = now()->toDateString();

        // Gezaehlt wird wie auf dem VA-Dashboard: ohne Stornos, ohne No-Shows.
        //
        // Vorher zaehlte withCount() alles mit. In der Liste standen dann fuenf
        // Buchungen, auf dem Dashboard einen Klick weiter vier - die fuenfte war
        // storniert. Zwei Zahlen fuer dieselbe Sache, und die erste stimmte nicht.
        //
        // Dieselbe Bedingung filtert auch die Liste selbst: Ein Termin, dessen
        // Buchungen alle storniert sind, hat operativ nichts mehr zu tun und
        // stuende hier sonst mit "0 Buchungen". In "Termine" bleibt er sichtbar.
        $aktiv = fn ($q) => $q->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW]);

        return Event::forTeam($this->getTeamId())
            ->with(['venue', 'slots'])
            ->withCount(['eventRooms', 'bookings' => $aktiv])
            ->whereHas('bookings', $aktiv)
            ->when($this->timeFilter === 'upcoming', fn ($q) => $q->whereDate('date', '>=', $today))
            ->when($this->timeFilter === 'past', fn ($q) => $q->whereDate('date', '<', $today))
            ->orderBy('date')
            ->get();
    }

    public function render()
    {
        return view('reservation::livewire.event-operations')
            ->layout('platform::layouts.app');
    }
}
