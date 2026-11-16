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

    /**
     * Vorgänge, bei denen jemand entscheiden muss.
     *
     * Vorher stand hier die Zahl der UNGESEHENEN Vorgänge. Bei Culinaria war
     * das 126 - davon 102 neue Bestellungen, 24 gescheiterte Zahlungen und
     * NULL Entscheidungen. Eine Zahl, die seit Wochen dreistellig in der
     * Leiste steht, liest niemand mehr; eine Stornierungsanfrage wäre darin
     * untergegangen.
     *
     * Eine neue Bestellung ist eine Nachricht. Eine Stornierungsanfrage ist
     * eine Aufgabe - da wartet ein Gast auf eine Entscheidung. Nur die zählt
     * hier. Meistens steht also gar kein Punkt, und genau deshalb fällt er
     * auf, wenn er da ist.
     *
     * Über den Status und nicht über seen_at: Sonst liesse „Alle als gesehen"
     * eine offene Entscheidung aus der Zahl verschwinden, ohne sie zu
     * entscheiden.
     */
    #[Computed]
    public function inboxCount(): int
    {
        if (! $this->teamId()) {
            return 0;
        }

        return Order::where('team_id', $this->teamId())->zuEntscheiden()->count();
    }

    /**
     * Zahl für eine Plakette, gekappt.
     *
     * Hier, weil die Leiste sie an sechs Stellen zeichnet - breit und schmal,
     * je drei Plaketten. Die schmale Fassung kappte schon bei 99, die breite
     * gar nicht: dieselbe Regel in zwei Fassungen, und eine davon fehlte.
     *
     * 99 und nicht 999: Seit der Posteingang zwei Plaketten nebeneinander
     * tragen kann, zaehlt jedes Zeichen Breite. Und keine dieser Zahlen wird
     * oberhalb von hundert noch genauer gelesen - „mehr als 99" ist dort die
     * ganze Aussage.
     */
    public function badge(int $zahl): string
    {
        return $zahl > 99 ? '99+' : (string) $zahl;
    }

    /**
     * Ungesehene Vorgänge – die Nachrichten.
     *
     * Bleibt sichtbar, aber LEISE: gedeckte Plakette, keine Signalfarbe.
     * Dass etwas hereingekommen ist, will man sehen; es ist nur nichts, was
     * jemand tun muss. Die farbige Plakette daneben ist fuer das, was
     * jemand tun muss.
     */
    #[Computed]
    public function neuesCount(): int
    {
        if (! $this->teamId()) {
            return 0;
        }

        return Order::ungeseheneImPosteingang($this->teamId());
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
