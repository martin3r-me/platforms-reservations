<?php

namespace Platform\Reservation\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
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

        return Event::forTeam($this->getTeamId())
            ->with(['venue', 'slots'])
            ->withCount(['eventRooms', 'bookings'])
            ->whereHas('bookings')
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
