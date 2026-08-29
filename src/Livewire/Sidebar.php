<?php

namespace Platform\Reservation\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Models\MenuItem;
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

        // Dieselbe Bedingung wie in der Liste (EventOperations): Stornos und
        // No-Shows zaehlen nicht. Sonst nennt die Zahl am Menuepunkt mehr
        // Veranstaltungen, als die Liste danach zeigt.
        return Event::forTeam($this->teamId())
            ->whereHas('bookings', fn ($q) => $q->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW]))
            ->whereDate('date', '>=', now()->toDateString())
            ->count();
    }

    /**
     * Artikel, die auf MEINE Freigabe warten.
     *
     * Bewusst nicht „alles im Review": Wer selbst eingereicht hat, kann seine
     * eigene Einreichung nicht freigeben – ein Zaehler, der sie mitzaehlt,
     * schickt ihn zu einer Aufgabe, die er nicht erledigen kann.
     */
    #[Computed]
    public function approvalCount(): int
    {
        $user = Auth::user();

        if (! $user || ! $this->teamId()) {
            return 0;
        }

        return MenuItem::forTeam($this->teamId())
            ->awaitingApprovalBy($user, $this->teamId())
            ->count();
    }

    public function render()
    {
        return view('reservation::livewire.sidebar');
    }
}
