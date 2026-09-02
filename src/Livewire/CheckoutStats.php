<?php

namespace Platform\Reservation\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Reservation\Models\CheckoutStat;
use Platform\Reservation\Services\CheckoutStatsService;
use Platform\Reservation\Services\LiveCheckoutService;

/**
 * Wo Bestellwege enden – über alle Termine hinweg.
 *
 * Eigene Seite und ausdrücklich nicht im VA-Dashboard: Dort geht es um einen
 * Abend, hier um ein Muster über viele. Ein Termin allein hat zu wenige
 * Bestellwege, als dass „die meisten brechen beim Sitzplatz ab" mehr wäre als
 * ein Zufall.
 *
 * Zeiträume wie in der Artikel-Auswertung und im Export – man soll sie nicht
 * dreimal lernen.
 */
class CheckoutStats extends Component
{
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $activePreset = 'month';

    public function mount(): void
    {
        // Wer hier hersieht, soll den aktuellen Stand sehen.
        //
        // Verbucht wird ein Abbruch beim Aufraeumen, und das laeuft sonst nur
        // per Los beim Schreiben - also nur, wenn gerade jemand ANDERES
        // bestellt. Auf einer ruhigen Seite koennte diese Auswertung damit
        // beliebig lange hinterherhinken: Wer als Einziger einen Bestellweg
        // abbricht, loest nie das Los aus, das ihn verbuchen wuerde.
        //
        // Hier ohne Los, weil diese Seite selten geoeffnet wird und genau
        // deshalb geoeffnet wird.
        app(LiveCheckoutService::class)->aufraeumen();

        $this->setPreset('month');
    }

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
                self::alsDatum(CheckoutStat::where('team_id', $this->teamId())->min('ended_at'), now()->startOfYear()),
                now()->toDateString(),
            ],
            default      => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
        };
    }

    public static function presets(): array
    {
        return ProductStats::presets();
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

    protected function teamId(): int
    {
        return (int) (Auth::user()?->current_team_id ?? 0);
    }

    #[Computed]
    public function summe(): array
    {
        return app(CheckoutStatsService::class)->zusammenfassung($this->teamId(), $this->dateFrom, $this->dateTo);
    }

    #[Computed]
    public function schritte(): array
    {
        return app(CheckoutStatsService::class)->abbruecheJeSchritt($this->teamId(), $this->dateFrom, $this->dateTo);
    }

    #[Computed]
    public function termine(): array
    {
        return app(CheckoutStatsService::class)->termine($this->teamId(), $this->dateFrom, $this->dateTo);
    }

    /** Der Schritt mit den meisten Abbrüchen – für den Satz über der Liste. */
    #[Computed]
    public function schlimmster(): ?array
    {
        $schritte = $this->schritte;

        return $schritte === [] ? null : collect($schritte)->sortByDesc('anzahl')->first();
    }

    public function render()
    {
        return view('reservation::livewire.checkout-stats')
            ->layout('platform::layouts.app');
    }
}
