<?php

namespace Platform\Reservation\Livewire;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\BookingItem;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Support\Vat;

/**
 * Finanzen: Umsatz nach Monaten und Terminen mit frei wählbarem Zeitraum.
 *
 * Umsatz = Summe der Bestellpositionen (Menge × eingefrorener Einzelpreis)
 * aller aktiven Buchungen (ohne storniert/No-Show). Bis zur Mollie-Integration
 * ist das der Bestellwert, nicht der bestätigte Zahlungseingang.
 */
class Finance extends Component
{
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $activePreset = 'year';

    public function mount(): void
    {
        $this->setPreset('year');
    }

    public function setPreset(string $preset): void
    {
        $this->activePreset = $preset;

        [$this->dateFrom, $this->dateTo] = match ($preset) {
            'last_year' => [
                now()->subYear()->startOfYear()->toDateString(),
                now()->subYear()->endOfYear()->toDateString(),
            ],
            'last_12'   => [now()->subMonths(11)->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
            'all'       => [
                Booking::forTeam($this->getTeamId())->min('date') ?? now()->startOfYear()->toDateString(),
                now()->endOfYear()->toDateString(),
            ],
            default     => [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()],
        };
    }

    public function updatedDateFrom(): void
    {
        $this->activePreset = 'custom';
    }

    public function updatedDateTo(): void
    {
        $this->activePreset = 'custom';
    }

    protected function getTeamId(): int
    {
        return (int) (Auth::user()?->current_team_id ?? 0);
    }

    /**
     * Status, die Umsatz sind.
     *
     * Ausstehende Buchungen gehören NICHT dazu: Das ist bestellt, aber nicht
     * bezahlt und nicht bestätigt. Vorher zählte alles außer Storno und
     * No-Show mit, und der Umsatz war damit zu hoch – eine Bestellung, die im
     * Zahlungsvorgang liegen bleibt, sah aus wie Geld.
     */
    protected const UMSATZ_STATUS = [Booking::STATUS_CONFIRMED, Booking::STATUS_COMPLETED];

    /** Basis-Query: Positionen bezahlter/bestätigter Buchungen im Zeitraum. */
    protected function itemsQuery()
    {
        return $this->zeitraum()->whereIn('b.status', self::UMSATZ_STATUS);
    }

    /** Dieselbe Abgrenzung, aber nur die ausstehenden – zum Ausweisen daneben. */
    protected function pendingQuery()
    {
        return $this->zeitraum()->where('b.status', Booking::STATUS_PENDING);
    }

    /** Gemeinsamer Rumpf: Team und Zeitraum. */
    protected function zeitraum()
    {
        return BookingItem::query()
            ->join('reservation_bookings as b', 'b.id', '=', 'reservation_booking_items.booking_id')
            ->where('b.team_id', $this->getTeamId())
            ->when($this->dateFrom, fn ($q) => $q->whereDate('b.date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('b.date', '<=', $this->dateTo));
    }

    /** @return \Illuminate\Support\Collection<string, object{revenue: float, bookings: int}> Key "YYYY-MM" */
    #[Computed]
    public function monthlyRevenue(): \Illuminate\Support\Collection
    {
        $rows = $this->itemsQuery()
            ->groupByRaw("DATE_FORMAT(b.date, '%Y-%m')")
            ->selectRaw("DATE_FORMAT(b.date, '%Y-%m') as ym,
                SUM(reservation_booking_items.quantity * reservation_booking_items.unit_price) as revenue,
                COUNT(DISTINCT b.id) as bookings")
            ->orderBy('ym')
            ->get()
            ->keyBy('ym');

        if ($rows->isEmpty() || !$this->dateFrom || !$this->dateTo) {
            return $rows->map(fn ($r) => (object) ['revenue' => (float) $r->revenue, 'bookings' => (int) $r->bookings]);
        }

        // Lückenlose Monatsreihe über den gewählten Zeitraum
        $result = collect();
        $cursor = Carbon::parse($this->dateFrom)->startOfMonth();
        $end = Carbon::parse($this->dateTo)->startOfMonth();

        while ($cursor->lte($end)) {
            $ym = $cursor->format('Y-m');
            $row = $rows->get($ym);
            $result->put($ym, (object) [
                'revenue'  => (float) ($row->revenue ?? 0),
                'bookings' => (int) ($row->bookings ?? 0),
            ]);
            $cursor->addMonth();
        }

        return $result;
    }

    /** Umsatz je Termin (inkl. "Ohne Termin" für den Alt-Flow). */
    #[Computed]
    public function eventRevenue(): \Illuminate\Support\Collection
    {
        $revenue = $this->itemsQuery()
            ->groupBy('b.event_id')
            ->selectRaw('b.event_id,
                SUM(reservation_booking_items.quantity * reservation_booking_items.unit_price) as revenue,
                COUNT(DISTINCT b.id) as bookings')
            ->get()
            ->keyBy(fn ($r) => $r->event_id ?? 0);

        // Dieselbe Abgrenzung wie beim Umsatz: Sonst stünden in einer Zeile
        // Gäste, deren Geld nicht mitgezählt ist.
        $guests = Booking::query()
            ->where('team_id', $this->getTeamId())
            ->whereIn('status', self::UMSATZ_STATUS)
            ->when($this->dateFrom, fn ($q) => $q->whereDate('date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('date', '<=', $this->dateTo))
            ->groupBy('event_id')
            ->selectRaw('event_id, SUM(guest_count) as guests')
            ->pluck('guests', 'event_id');

        $events = Event::whereIn('id', $revenue->keys()->filter())->get()->keyBy('id');

        return $revenue
            ->map(function ($row, $eventId) use ($events, $guests) {
                $event = $eventId ? $events->get($eventId) : null;

                return (object) [
                    'event_id' => $eventId ?: null,
                    'name'     => $event?->name ?? 'Ohne Termin (freie Buchung)',
                    'date'     => $event?->date,
                    'revenue'  => (float) $row->revenue,
                    'bookings' => (int) $row->bookings,
                    'guests'   => (int) ($guests->get($eventId ?: null) ?? 0),
                ];
            })
            ->sortByDesc('revenue')
            ->values();
    }

    /** Umsatz je MwSt-Satz (für die Buchhaltung). */
    #[Computed]
    public function taxBreakdown(): \Illuminate\Support\Collection
    {
        return $this->itemsQuery()
            ->groupBy('reservation_booking_items.tax_rate')
            ->selectRaw('reservation_booking_items.tax_rate,
                SUM(reservation_booking_items.quantity * reservation_booking_items.unit_price) as revenue')
            ->orderByDesc('revenue')
            ->get()
            ->map(function ($r) {
                // unit_price ist brutto → Netto-/Steueranteil je Satz extrahieren.
                $split = Vat::fromGross((float) $r->revenue, (float) $r->tax_rate);

                return (object) (['tax_rate' => $r->tax_rate] + $split);
            });
    }

    #[Computed]
    public function totals(): object
    {
        $monthly = $this->monthlyRevenue;
        $revenue = $monthly->sum('revenue');
        $bookings = $monthly->sum('bookings');
        $best = $monthly->sortByDesc('revenue')->filter(fn ($m) => $m->revenue > 0);

        // Das Ausstehende daneben ausweisen statt stillschweigend weglassen:
        // Sonst sieht es aus, als wäre Geld verschwunden.
        $offen = $this->pendingQuery()
            ->selectRaw('SUM(reservation_booking_items.quantity * reservation_booking_items.unit_price) as revenue,
                COUNT(DISTINCT b.id) as bookings')
            ->first();

        return (object) [
            'revenue'          => $revenue,
            'bookings'         => $bookings,
            'average'          => $bookings > 0 ? $revenue / $bookings : 0,
            'best_month'       => $best->keys()->first(),
            'max_month'        => (float) ($monthly->max('revenue') ?: 0),
            'pending'          => (float) ($offen->revenue ?? 0),
            'pending_bookings' => (int) ($offen->bookings ?? 0),
        ];
    }

    public function render()
    {
        return view('reservation::livewire.finance')
            ->layout('platform::layouts.app');
    }
}
