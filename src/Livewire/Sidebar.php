<?php

namespace Platform\Reservation\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Models\Order;

/**
 * Modul-Sidebar (wird von der Platform-Hauptsidebar eingebunden).
 */
class Sidebar extends Component
{
    protected function teamId(): ?int
    {
        return Auth::user()?->current_team_id;
    }

    /** Ungesehene Vorgänge im Posteingang (team-geteilt). */
    #[Computed]
    public function inboxCount(): int
    {
        if (! $this->teamId()) {
            return 0;
        }

        return Order::where('team_id', $this->teamId())
            ->whereIn('status', Order::INBOX_STATUSES)
            ->whereNull('seen_at')
            ->count();
    }

    /** Kommende Veranstaltungen mit Buchungen (operative Sicht). */
    #[Computed]
    public function operationsCount(): int
    {
        if (! $this->teamId()) {
            return 0;
        }

        return Event::forTeam($this->teamId())
            ->whereHas('bookings')
            ->whereDate('date', '>=', now()->toDateString())
            ->count();
    }

    public function render()
    {
        return view('reservation::livewire.sidebar');
    }
}
