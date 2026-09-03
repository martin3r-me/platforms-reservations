<?php

namespace Platform\Reservation\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Carbon\CarbonImmutable;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\CheckoutSetting;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Models\MenuItem;
use Platform\Reservation\Models\SalesList;
use Platform\Reservation\Support\Zeitraum;

/**
 * Was wurde verkauft – Mengen und Umsätze je Artikel in einem Zeitraum.
 *
 * Eigene Seite und nicht Teil der Finanzen, weil es eine andere Frage für
 * andere Leute ist: Die Finanzen sagen, wie viel Geld hereinkam (Buchhaltung),
 * hier steht, was über die Theke geht (Küche, Einkauf, Speisekarte).
 *
 * GEZÄHLT WERDEN BESTANDTEILE, nicht Bundles. Ein Bundle zerfällt beim
 * Bestellen ohnehin in seine Teile, und für Einkauf und Küche ist genau das die
 * Wahrheit: Drei Bier im Paket sind drei Bier. Damit das Bundle trotzdem nicht
 * unsichtbar wird, führt jede Zeile mit, wie viel davon über ein Bundle
 * verkauft wurde – und darunter steht, wie oft welches Bundle über den Tisch
 * ging.
 *
 * Abgegrenzt wie die Finanzen – über dieselbe Stelle
 * (CheckoutSetting::umsatzStatus), damit auch die Einstellung zu den No-Shows
 * hier greift. Stünde hier eine andere Menge als dort ein Umsatz, wäre das die
 * erste Frage, die jemand stellt.
 */
class ProductStats extends Component
{
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $activePreset = 'month';

    /** menge | umsatz */
    public string $sortBy = 'menge';

    /** Nur ein Termin statt eines Zeitraums. */
    public ?int $eventId = null;

    /** Nicht verkaufte Artikel einblenden – aus, weil die Liste lang wird. */
    public bool $showUnsold = false;

    public function mount(): void
    {
        $this->setPreset('month');
    }

    /** Dieselben Zeiträume wie im Export – man soll sie nicht zweimal lernen. */
    public function setPreset(string $preset): void
    {
        $this->activePreset = $preset;

        // Gemeinsame Zeiträume aus Support\Zeitraum; „Alles" beantwortet jede
        // Seite selbst - hier über die tatsächlich vorhandenen Buchungen.
        [$this->dateFrom, $this->dateTo] = Zeitraum::spanne($preset) ?? match ($preset) {
            'all'   => [
                self::alsDatum(Booking::where('team_id', $this->teamId())->min('date'), now()->startOfYear()),
                self::alsDatum(Booking::where('team_id', $this->teamId())->max('date'), now()->endOfYear()),
            ],
            default => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
        };
    }

    public static function presets(): array
    {
        return Zeitraum::beschriftungen() + ['all' => 'Alles'];
    }

    protected static function alsDatum($wert, $ersatz): string
    {
        return $wert ? \Carbon\CarbonImmutable::parse($wert)->toDateString() : $ersatz->toDateString();
    }

    public function updatedDateFrom(): void
    {
        $this->activePreset = '';
    }

    public function updatedDateTo(): void
    {
        $this->activePreset = '';
    }

    /**
     * Termin gewählt: Der Zeitraum springt auf seinen Tag.
     *
     * Sonst stünden zwei Filter nebeneinander, die sich widersprechen können –
     * ein Termin im Juni und ein Zeitraum im Mai ergeben immer null.
     */
    public function updatedEventId(): void
    {
        $termin = $this->eventId ? $this->events->firstWhere('id', $this->eventId) : null;

        if ($termin?->date) {
            $this->dateFrom     = $termin->date->toDateString();
            $this->dateTo       = $termin->date->toDateString();
            $this->activePreset = '';
        }
    }

    /** Termine mit Buchungen – nur die sind hier eine sinnvolle Wahl. */
    #[Computed]
    public function events(): \Illuminate\Support\Collection
    {
        return Event::query()
            ->where('team_id', $this->teamId())
            ->whereIn('id', Booking::where('team_id', $this->teamId())->select('event_id'))
            ->orderByDesc('date')
            ->get(['id', 'name', 'date']);
    }

    public function sortNach(string $feld): void
    {
        $this->sortBy = in_array($feld, ['menge', 'umsatz'], true) ? $feld : 'menge';
    }

    protected function teamId(): int
    {
        return (int) (Auth::user()?->current_team_id ?? 0);
    }

    /**
     * Abgrenzung aus den Einstellungen – dieselbe wie in den Finanzen.
     *
     * Einmal je Request gemerkt: positionen() laeuft fuer Zeitraum und
     * Vorzeitraum und noch ein paar Mal daneben.
     *
     * @return array<int, string>
     */
    protected function umsatzStatus(): array
    {
        return $this->umsatzStatusCache ??= CheckoutSetting::forTeam($this->teamId())->umsatzStatus();
    }

    /** @var array<int, string>|null */
    private ?array $umsatzStatusCache = null;

    /** Positionen bezahlter Buchungen im Zeitraum. */
    protected function positionen(?string $von = null, ?string $bis = null)
    {
        $von ??= $this->dateFrom;
        $bis ??= $this->dateTo;

        return DB::table('reservation_booking_items as bi')
            ->join('reservation_bookings as b', 'b.id', '=', 'bi.booking_id')
            ->where('b.team_id', $this->teamId())
            ->whereIn('b.status', $this->umsatzStatus())
            ->when($this->eventId, fn ($q) => $q->where('b.event_id', $this->eventId))
            ->when($von, fn ($q) => $q->whereDate('b.date', '>=', $von))
            ->when($bis, fn ($q) => $q->whereDate('b.date', '<=', $bis));
    }

    /**
     * Der Zeitraum davor, gleich lang.
     *
     * Bei einem vollen Monat, Quartal oder Jahr wird der vorige genommen und
     * nicht um die Tageszahl zurückgeschoben: Ein August hat 31 Tage, ein Juli
     * auch, ein Juni nicht – zurückgeschoben käme "30. Juni bis 30. Juli"
     * heraus, und niemand vergleicht so.
     *
     * @return array{0: string, 1: string}|null
     */
    #[Computed]
    public function vorzeitraum(): ?array
    {
        // Bei einem einzelnen Termin gibt es nichts zu vergleichen.
        if ($this->eventId || ! $this->dateFrom || ! $this->dateTo) {
            return null;
        }

        $von = CarbonImmutable::parse($this->dateFrom);
        $bis = CarbonImmutable::parse($this->dateTo);

        if ($von->isSameDay($von->startOfMonth()) && $bis->isSameDay($bis->endOfMonth()) && $von->isSameMonth($bis)) {
            $davor = $von->subMonthNoOverflow();

            return [$davor->startOfMonth()->toDateString(), $davor->endOfMonth()->toDateString()];
        }

        if ($von->isSameDay($von->startOfQuarter()) && $bis->isSameDay($bis->endOfQuarter()) && $von->quarter === $bis->quarter && $von->year === $bis->year) {
            $davor = $von->subQuarterNoOverflow();

            return [$davor->startOfQuarter()->toDateString(), $davor->endOfQuarter()->toDateString()];
        }

        if ($von->isSameDay($von->startOfYear()) && $bis->isSameDay($bis->endOfYear()) && $von->year === $bis->year) {
            $davor = $von->subYear();

            return [$davor->startOfYear()->toDateString(), $davor->endOfYear()->toDateString()];
        }

        $tage = $von->diffInDays($bis) + 1;

        return [$von->subDays($tage)->toDateString(), $von->subDay()->toDateString()];
    }

    /** Mengen des Vorzeitraums je Artikel. */
    #[Computed]
    public function vorher(): \Illuminate\Support\Collection
    {
        $zeitraum = $this->vorzeitraum;

        if (! $zeitraum) {
            return collect();
        }

        return $this->positionen($zeitraum[0], $zeitraum[1])
            ->groupBy('bi.menu_item_id')
            ->selectRaw('bi.menu_item_id, SUM(bi.quantity) as menge, SUM(bi.quantity * bi.unit_price) as umsatz')
            ->get()
            ->keyBy('menu_item_id');
    }

    /**
     * Artikel mit Menge, Umsatz und Bundle-Anteil.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function articles(): \Illuminate\Support\Collection
    {
        $zeilen = $this->positionen()
            ->groupBy('bi.menu_item_id')
            ->selectRaw('bi.menu_item_id,
                SUM(bi.quantity) as menge,
                SUM(bi.quantity * bi.unit_price) as umsatz,
                SUM(CASE WHEN bi.bundle_menu_item_id IS NULL THEN 0 ELSE bi.quantity END) as menge_bundle')
            ->get();

        if ($zeilen->isEmpty()) {
            return collect();
        }

        $artikel = MenuItem::withoutGlobalScope('team')
            ->with('category')
            ->whereIn('id', $zeilen->pluck('menu_item_id')->filter())
            ->get()
            ->keyBy('id');

        $gesamt = (float) $zeilen->sum('umsatz');
        $vorher = $this->vorher;

        return $zeilen
            ->map(function ($z) use ($artikel, $gesamt, $vorher) {
                $davor = $vorher->get($z->menu_item_id);

                return [
                    'id'           => (int) $z->menu_item_id,
                    'name'         => $artikel[$z->menu_item_id]?->name ?? 'Gelöschter Artikel',
                    'category'     => $artikel[$z->menu_item_id]?->category?->name,
                    'menge'        => (int) $z->menge,
                    'menge_bundle' => (int) $z->menge_bundle,
                    'umsatz'       => (float) $z->umsatz,
                    'anteil'       => $gesamt > 0 ? (float) $z->umsatz / $gesamt * 100 : 0.0,
                    // null heißt: kein Vorzeitraum im Blick (einzelner Termin).
                    'menge_davor'  => $vorher->isEmpty() && ! $this->vorzeitraum ? null : (int) ($davor->menge ?? 0),
                ];
            })
            ->sortByDesc($this->sortBy)
            ->values();
    }

    /**
     * Verkaufte Bundles.
     *
     * Zwei Stufen, und die erste ist der Grund: bundle_quantity steht auf JEDER
     * Position eines Bundles, nicht nur einmal. Wer einfach summiert, zählt ein
     * Dreier-Paket dreifach. Deshalb zuerst je bundle_ref ein Wert, dann erst
     * die Summe je Bundle.
     *
     * Der Umsatz stimmt dabei von selbst: Der Bundle-Preis ist beim Bestellen
     * cent-genau auf die Bestandteile verteilt worden, ihre Summe ergibt ihn
     * wieder.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function bundles(): \Illuminate\Support\Collection
    {
        $jeInstanz = $this->positionen()
            ->whereNotNull('bi.bundle_menu_item_id')
            ->groupBy('bi.bundle_ref', 'bi.bundle_menu_item_id')
            ->selectRaw('bi.bundle_ref, bi.bundle_menu_item_id,
                MAX(bi.bundle_quantity) as menge,
                SUM(bi.quantity * bi.unit_price) as umsatz');

        $zeilen = DB::query()
            ->fromSub($jeInstanz, 'x')
            ->groupBy('x.bundle_menu_item_id')
            ->selectRaw('x.bundle_menu_item_id, SUM(x.menge) as menge, SUM(x.umsatz) as umsatz')
            ->get();

        if ($zeilen->isEmpty()) {
            return collect();
        }

        $namen = MenuItem::withoutGlobalScope('team')
            ->whereIn('id', $zeilen->pluck('bundle_menu_item_id')->filter())
            ->pluck('name', 'id');

        return $zeilen
            ->map(fn ($z) => [
                'id'     => (int) $z->bundle_menu_item_id,
                'name'   => $namen[$z->bundle_menu_item_id] ?? 'Gelöschtes Bundle',
                'menge'  => (int) $z->menge,
                'umsatz' => (float) $z->umsatz,
            ])
            ->sortByDesc($this->sortBy)
            ->values();
    }

    /**
     * Artikel, die im Zeitraum ANGEBOTEN, aber nicht verkauft wurden.
     *
     * Angeboten heißt: Sie standen auf der Verkaufsliste eines Termins im
     * Zeitraum. Alle Artikel des Teams zu nehmen wäre falsch – was gar nicht
     * auf der Karte stand, konnte auch niemand kaufen, und die Liste wäre
     * voller Rauschen.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function unsold(): \Illuminate\Support\Collection
    {
        $termine = Event::query()
            ->where('team_id', $this->teamId())
            ->when($this->eventId, fn ($q) => $q->where('id', $this->eventId))
            ->when(! $this->eventId && $this->dateFrom, fn ($q) => $q->whereDate('date', '>=', $this->dateFrom))
            ->when(! $this->eventId && $this->dateTo, fn ($q) => $q->whereDate('date', '<=', $this->dateTo))
            ->get(['id', 'sales_list_id']);

        if ($termine->isEmpty()) {
            return collect();
        }

        // Termine ohne eigene Liste laufen auf die Standardliste des Teams.
        $listen = $termine->pluck('sales_list_id')->filter()->unique();

        if ($termine->contains(fn ($e) => ! $e->sales_list_id)) {
            $standard = SalesList::where('team_id', $this->teamId())->where('is_default', true)->value('id');

            if ($standard) {
                $listen = $listen->push($standard)->unique();
            }
        }

        if ($listen->isEmpty()) {
            return collect();
        }

        $verkauft = $this->articles->pluck('id');

        return MenuItem::query()
            ->where('team_id', $this->teamId())
            ->whereIn('id', DB::table('reservation_sales_list_items')
                ->whereIn('sales_list_id', $listen)
                ->select('menu_item_id'))
            ->whereNotIn('id', $verkauft->isEmpty() ? [0] : $verkauft)
            // Bundles gehören nicht in diese Liste: Gezählt werden Bestandteile,
            // ein unverkauftes Bundle stünde sonst neben seinen Teilen.
            ->where('is_bundle', false)
            ->with('category')
            ->orderBy('name')
            ->get()
            ->map(fn (MenuItem $i) => [
                'id'       => $i->id,
                'name'     => $i->name,
                'category' => $i->category?->name,
                'price'    => (float) $i->price,
            ]);
    }

    /** Kopfzahlen. */
    #[Computed]
    public function totals(): object
    {
        $artikel = $this->articles;

        return (object) [
            'menge'        => (int) $artikel->sum('menge'),
            'umsatz'       => (float) $artikel->sum('umsatz'),
            'sorten'       => $artikel->count(),
            'menge_bundle' => (int) $artikel->sum('menge_bundle'),
            'menge_davor'  => $this->vorzeitraum ? (int) $this->vorher->sum('menge') : null,
            'umsatz_davor' => $this->vorzeitraum ? (float) $this->vorher->sum('umsatz') : null,
        ];
    }

    public function render()
    {
        return view('reservation::livewire.product-stats')
            ->layout('platform::layouts.app');
    }
}
