<?php

namespace Platform\Reservation\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Platform\Reservation\Livewire\Concerns\PrintsBookingReceipt;
use Platform\Reservation\Livewire\Concerns\ShowsBookingDetail;
use Platform\Reservation\Models\Order;
use Platform\Reservation\Services\OrderCancellationService;

/**
 * Posteingang: chronologischer Feed der relevanten Vorgänge (neue Bestellungen,
 * Stornos, fehlgeschlagene Zahlungen, Storno-Anfragen). Team-geteilter
 * "gesehen"-Status (einzeln + Bulk); Storno-Anfragen direkt freigeben/ablehnen.
 */
class Inbox extends Component
{
    use PrintsBookingReceipt;
    use ShowsBookingDetail;
    use WithPagination;

    /**
     * Bestellung, deren Buchungen zur Auswahl aufgeklappt sind.
     *
     * Nur noetig, wenn eine Bestellung MEHRERE Buchungen hat - eine je Pause.
     * Dann hat ein Klick auf die Zeile kein eindeutiges Ziel, und die Zeile
     * fragt zurueck, statt zu raten. Bei einer Buchung passiert das nie: Da
     * oeffnet der Klick direkt.
     */
    public ?int $aufgeklappt = null;

    /** Einträge je Seite – wie in „Alle Buchungen". */
    public const JE_SEITE = 25;

    /**
     * Welcher Ausschnitt gezeigt wird.
     *
     * Vorher ein Ja/Nein („nur ungesehene"). Damit liessen sich zwei Dinge
     * nicht finden, die beide wichtig sind: die Vorgänge, bei denen jemand
     * ENTSCHEIDEN muss, und die, bei denen die ZAHLUNG nicht zustande kam.
     * Letztere lagen bei Culinaria zu 24 Stück im Stapel, ohne dass sie
     * irgendwo aufgefallen wären.
     */
    public const FILTER = ['unseen', 'decide', 'payment_failed', 'all'];

    public string $filter = 'unseen';

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

        $this->schaerfen($query);

        return $query->paginate(self::JE_SEITE);
    }

    /** Den gewählten Ausschnitt auf eine Abfrage legen. */
    protected function schaerfen($query, ?string $filter = null): void
    {
        match ($filter ?? $this->filter) {
            'decide'         => $query->zuEntscheiden(),
            'payment_failed' => $query->zahlungGescheitert(),
            'all'            => null,
            default          => $query->whereNull('seen_at'),
        };
    }

    /**
     * Die Zahl an jedem Filter.
     *
     * Ohne sie müsste man jeden anklicken, um zu sehen, ob etwas dahinter
     * liegt - und genau das tut niemand. Die 24 gescheiterten Zahlungen bei
     * Culinaria lagen monatelang da, weil nichts auf sie hinwies.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function zaehler(): array
    {
        $zahl = function (string $filter): int {
            $query = Order::where('team_id', $this->teamId())
                ->whereIn('status', Order::INBOX_STATUSES);

            $this->schaerfen($query, $filter);

            return $query->count();
        };

        return [
            'unseen'         => $zahl('unseen'),
            'decide'         => $zahl('decide'),
            'payment_failed' => $zahl('payment_failed'),
            'all'            => $zahl('all'),
        ];
    }

    #[Computed]
    public function unseenCount(): int
    {
        return Order::ungeseheneImPosteingang($this->teamId());
    }

    /**
     * Klick auf eine Zeile.
     *
     * Der Posteingang listet BESTELLUNGEN, das Fenster zeigt BUCHUNGEN - und
     * eine Bestellung kann mehrere haben, eine je Pause. Bei genau einer
     * oeffnet der Klick sie direkt; bei mehreren klappt die Zeile auf und
     * laesst waehlen. Raten waere hier falsch: Es gibt keinen Grund, warum
     * die erste Pause gemeint sein sollte.
     */
    public function oeffneVorgang(int $orderId): void
    {
        $order = $this->order($orderId);

        if (! $order) {
            return;
        }

        $buchungen = $order->bookings;

        if ($buchungen->count() === 1) {
            $this->aufgeklappt = null;
            $this->openDetail($buchungen->first()->id);

            return;
        }

        // Zweiter Klick schliesst wieder.
        $this->aufgeklappt = $this->aufgeklappt === $orderId ? null : $orderId;
    }

    /**
     * Die Bestellung zur gerade geoeffneten Buchung.
     *
     * Das Fenster kennt nur die Buchung; „gesehen" und die Entscheidung ueber
     * ein Storno haengen aber an der Bestellung. Ohne diesen Weg zurueck
     * koennte der Fuss des Fensters nichts davon anbieten.
     */
    #[Computed]
    public function detailOrder(): ?Order
    {
        $orderId = $this->detailBooking?->order_id;

        return $orderId ? $this->order($orderId) : null;
    }

    /** Als gesehen markieren, ohne das Fenster zu verlassen. */
    public function markSeenAusDetail(): void
    {
        if ($order = $this->detailOrder) {
            $order->update(['seen_at' => now()]);
        }

        $this->refresh();
    }

    protected function order(int $id): ?Order
    {
        return Order::where('team_id', $this->teamId())->find($id);
    }

    protected function refresh(): void
    {
        unset($this->entries, $this->unseenCount, $this->zaehler, $this->detailOrder);
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

        // Entschieden heisst auch: Das Fenster hat seinen Zweck erfuellt.
        if ($this->showDetail) {
            $this->closeDetail();
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

        if ($this->showDetail) {
            $this->closeDetail();
        }

        $this->refresh();
    }

    public function updatedFilter(): void
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
