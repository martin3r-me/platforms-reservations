<?php

namespace Platform\Reservation\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\MenuItem;

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
 * Abgegrenzt wie die Finanzen: bestätigte und abgeschlossene Buchungen.
 * Stünde hier eine andere Menge als dort ein Umsatz, wäre das die erste Frage,
 * die jemand stellt.
 */
class ProductStats extends Component
{
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $activePreset = 'month';

    /** menge | umsatz */
    public string $sortBy = 'menge';

    public function mount(): void
    {
        $this->setPreset('month');
    }

    /** Dieselben Zeiträume wie im Export – man soll sie nicht zweimal lernen. */
    public function setPreset(string $preset): void
    {
        $this->activePreset = $preset;

        [$this->dateFrom, $this->dateTo] = match ($preset) {
            'last_week'  => [
                now()->subWeek()->startOfWeek()->toDateString(),
                now()->subWeek()->endOfWeek()->toDateString(),
            ],
            'last_month' => [
                now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                now()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ],
            'quarter'    => [now()->startOfQuarter()->toDateString(), now()->endOfQuarter()->toDateString()],
            'year'       => [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()],
            'last_year'  => [
                now()->subYear()->startOfYear()->toDateString(),
                now()->subYear()->endOfYear()->toDateString(),
            ],
            'all'        => [
                self::alsDatum(Booking::where('team_id', $this->teamId())->min('date'), now()->startOfYear()),
                self::alsDatum(Booking::where('team_id', $this->teamId())->max('date'), now()->endOfYear()),
            ],
            default      => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
        };
    }

    public static function presets(): array
    {
        return [
            'last_week'  => 'Letzte Woche',
            'month'      => 'Dieser Monat',
            'last_month' => 'Letzter Monat',
            'quarter'    => 'Dieses Quartal',
            'year'       => 'Dieses Jahr',
            'last_year'  => 'Letztes Jahr',
            'all'        => 'Alles',
        ];
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

    public function sortNach(string $feld): void
    {
        $this->sortBy = in_array($feld, ['menge', 'umsatz'], true) ? $feld : 'menge';
    }

    protected function teamId(): int
    {
        return (int) (Auth::user()?->current_team_id ?? 0);
    }

    /** Positionen bezahlter Buchungen im Zeitraum. */
    protected function positionen()
    {
        return DB::table('reservation_booking_items as bi')
            ->join('reservation_bookings as b', 'b.id', '=', 'bi.booking_id')
            ->where('b.team_id', $this->teamId())
            ->whereIn('b.status', [Booking::STATUS_CONFIRMED, Booking::STATUS_COMPLETED])
            ->when($this->dateFrom, fn ($q) => $q->whereDate('b.date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('b.date', '<=', $this->dateTo));
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

        return $zeilen
            ->map(fn ($z) => [
                'id'           => (int) $z->menu_item_id,
                'name'         => $artikel[$z->menu_item_id]?->name ?? 'Gelöschter Artikel',
                'category'     => $artikel[$z->menu_item_id]?->category?->name,
                'menge'        => (int) $z->menge,
                'menge_bundle' => (int) $z->menge_bundle,
                'umsatz'       => (float) $z->umsatz,
                'anteil'       => $gesamt > 0 ? (float) $z->umsatz / $gesamt * 100 : 0.0,
            ])
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
        ];
    }

    public function render()
    {
        return view('reservation::livewire.product-stats')
            ->layout('platform::layouts.app');
    }
}
