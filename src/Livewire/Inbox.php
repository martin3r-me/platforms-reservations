<?php

namespace Platform\Reservation\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Platform\Reservation\Models\Order;
use Platform\Reservation\Services\OrderCancellationService;

/**
 * Posteingang: chronologischer Feed der relevanten Vorgänge (neue Bestellungen,
 * Stornos, fehlgeschlagene Zahlungen, Storno-Anfragen). Team-geteilter
 * "gesehen"-Status (einzeln + Bulk); Storno-Anfragen direkt freigeben/ablehnen.
 */
class Inbox extends Component
{
    use WithPagination;

    /** Einträge je Seite – wie in „Alle Buchungen". */
    public const JE_SEITE = 25;

    public bool $unseenOnly = true;

    /** @var array<int,int> ausgewählte Order-IDs für Bulk */
    public array $selected = [];

    protected function teamId(): int
    {
        return (int) (Auth::user()?->current_team_id ?? 0);
    }

    /**
     * Die Vorgänge, seitenweise.
     *
     * Vorher stand hier limit(200)->get(). Das schnitt ab einem gewissen Punkt
     * lautlos ab: Der 201. Vorgang war nicht etwa auf einer zweiten Seite,
     * sondern weg - ohne Hinweis, ohne Zähler, ohne Weg dorthin. Bei ein paar
     * Terminen im Jahr dauert das, aber es kommt sicher, und dann sucht jemand
     * eine Bestellung, die es scheinbar nicht gibt.
     *
     * Jetzt dieselbe Blätterung wie in „Alle Buchungen", damit sich beide
     * Listen gleich verhalten.
     */
    #[Computed]
    public function entries(): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = Order::where('team_id', $this->teamId())
            ->whereIn('status', Order::INBOX_STATUSES)
            ->with(['event', 'payment', 'bookings'])
            ->orderByDesc('updated_at');

        if ($this->unseenOnly) {
            $query->whereNull('seen_at');
        }

        return $query->paginate(self::JE_SEITE);
    }

    #[Computed]
    public function unseenCount(): int
    {
        return Order::ungeseheneImPosteingang($this->teamId());
    }

    protected function order(int $id): ?Order
    {
        return Order::where('team_id', $this->teamId())->find($id);
    }

    protected function refresh(): void
    {
        unset($this->entries, $this->unseenCount);
    }

    public function markSeen(int $id): void
    {
        $this->order($id)?->update(['seen_at' => now()]);
        $this->refresh();
    }

    public function markSelectedSeen(): void
    {
        if ($this->selected) {
            Order::where('team_id', $this->teamId())->whereIn('id', $this->selected)->update(['seen_at' => now()]);
        }
        $this->selected = [];
        $this->refresh();
    }

    public function markAllSeen(): void
    {
        Order::where('team_id', $this->teamId())
            ->whereIn('status', Order::INBOX_STATUSES)
            ->whereNull('seen_at')
            ->update(['seen_at' => now()]);
        $this->selected = [];

        // Nach "alle gesehen" ist die gefilterte Liste leer - dann gehört man
        // auf Seite 1 und nicht auf eine Seite, die es nicht mehr gibt.
        $this->resetPage();
        $this->refresh();
    }

    public function approveCancellation(int $id, OrderCancellationService $service): void
    {
        $order = $this->order($id);

        if ($order && $order->status === Order::STATUS_CANCELLATION_REQUESTED) {
            $service->approveAndCancel($order);
            $order->update(['seen_at' => now()]);
            session()->flash('inbox_message', 'Storno freigegeben & Rückerstattung ausgelöst.');
        }

        $this->refresh();
    }

    public function rejectCancellation(int $id): void
    {
        $order = $this->order($id);

        if ($order && $order->status === Order::STATUS_CANCELLATION_REQUESTED) {
            $order->update([
                'status'                    => Order::STATUS_CONFIRMED,
                'cancellation_requested_at' => null,
                'seen_at'                   => now(),
            ]);
            session()->flash('inbox_message', 'Storno-Anfrage abgelehnt – Bestellung bleibt bestätigt.');
        }

        $this->refresh();
    }

    public function updatedUnseenOnly(): void
    {
        $this->selected = [];

        // Zurück auf Seite 1: Der Filter ändert die Menge, und wer auf Seite 4
        // stand, sähe sonst eine leere Tabelle einer Liste, die nur noch zwei
        // Seiten hat - was aussieht, als wären die Daten weg.
        $this->resetPage();
    }

    public function render()
    {
        return view('reservation::livewire.inbox')->layout('platform::layouts.app');
    }
}
