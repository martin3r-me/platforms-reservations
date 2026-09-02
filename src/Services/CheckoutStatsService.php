<?php

namespace Platform\Reservation\Services;

use Illuminate\Support\Facades\DB;
use Platform\Reservation\Models\CheckoutStat;
use Platform\Reservation\Support\CheckoutSteps;

/**
 * Wo Bestellwege enden – über alle Termine hinweg.
 *
 * Beantwortet ausdrücklich NICHT „welcher Anteil hat Schritt 4 erreicht".
 * Diese Zahl wäre hier eine Erfindung: Ein Bestellweg mit einer Pause hat
 * vier Schritte, einer mit zweien fünf, und der Schritt „Pause" existiert im
 * ersten Fall gar nicht. Ein Prozentwert über beide Sorten hinweg hätte einen
 * Nenner, den es nicht gibt – er sähe nur so aus, als hätte er einen.
 *
 * Beantwortet stattdessen genau das, wonach gefragt war: WO wird am meisten
 * abgebrochen. Das ist eine Verteilung über die tatsächlichen Endpunkte und
 * braucht keinen erfundenen Nenner. Dazu die Quote insgesamt, die einen
 * echten hat: bestellt gegen alles.
 */
class CheckoutStatsService
{
    /**
     * Die drei Zahlen über allem.
     *
     * @return array{gesamt: int, bestellt: int, abgebrochen: int, quote: float}
     */
    public function zusammenfassung(int $teamId, string $von, string $bis): array
    {
        $zeilen = CheckoutStat::withoutGlobalScope('team')
            ->where('team_id', $teamId)
            ->imZeitraum($von, $bis)
            ->selectRaw('outcome, COUNT(*) as anzahl')
            ->groupBy('outcome')
            ->pluck('anzahl', 'outcome');

        $bestellt    = (int) ($zeilen[CheckoutStat::AUSGANG_BESTELLT] ?? 0);
        $abgebrochen = (int) ($zeilen[CheckoutStat::AUSGANG_ABGEBROCHEN] ?? 0);
        $gesamt      = $bestellt + $abgebrochen;

        return [
            'gesamt'      => $gesamt,
            'bestellt'    => $bestellt,
            'abgebrochen' => $abgebrochen,
            'quote'       => $gesamt > 0 ? round($bestellt / $gesamt * 100, 1) : 0.0,
        ];
    }

    /**
     * Abbrüche je Schritt, in der Reihenfolge des Bestellwegs.
     *
     * `anteil` ist der Anteil an ALLEN Abbrüchen, nicht an denen, die den
     * Schritt erreicht haben – siehe Klassenkommentar.
     *
     * `warenkorb` ist die Summe der liegengebliebenen Warenkörbe. Sie ist kein
     * entgangener Umsatz: Ein Gast, der beim Sitzplatz aufgibt, hätte den Korb
     * vielleicht nie bezahlt. Sie sagt nur, wo das Aufgeben teuer aussieht.
     *
     * @return array<int, array{step: string, label: string, anzahl: int, anteil: float, warenkorb: float, dauer: int}>
     */
    public function abbruecheJeSchritt(int $teamId, string $von, string $bis): array
    {
        $zeilen = CheckoutStat::withoutGlobalScope('team')
            ->where('team_id', $teamId)
            ->abgebrochen()
            ->imZeitraum($von, $bis)
            ->selectRaw('last_step, COUNT(*) as anzahl, SUM(cart_total) as warenkorb, AVG(duration_seconds) as dauer')
            ->groupBy('last_step')
            ->get();

        $summe = (int) $zeilen->sum('anzahl');

        return $zeilen
            ->map(fn ($z) => [
                'step'      => (string) $z->last_step,
                'label'     => CheckoutSteps::label((string) $z->last_step),
                'anzahl'    => (int) $z->anzahl,
                'anteil'    => $summe > 0 ? round((int) $z->anzahl / $summe * 100, 1) : 0.0,
                'warenkorb' => round((float) $z->warenkorb, 2),
                'dauer'     => (int) round((float) $z->dauer),
            ])
            ->sortBy(fn ($z) => CheckoutSteps::stelle($z['step']))
            ->values()
            ->all();
    }

    /**
     * Die Termine mit den meisten Abbrüchen.
     *
     * Sortiert nach der ZAHL, nicht nach der Quote: Ein Termin mit einem
     * Bestellweg und einem Abbruch hätte 100 % und stünde ganz oben, ohne dass
     * daran etwas zu sehen wäre.
     *
     * @return array<int, array{event_id: ?int, name: string, datum: ?string, abgebrochen: int, bestellt: int, quote: float}>
     */
    public function termine(int $teamId, string $von, string $bis, int $grenze = 10): array
    {
        $zeilen = CheckoutStat::withoutGlobalScope('team')
            ->from('reservation_checkout_stats as s')
            ->leftJoin('reservation_events as e', 'e.id', '=', 's.event_id')
            ->where('s.team_id', $teamId)
            ->whereBetween('s.ended_at', [$von . ' 00:00:00', $bis . ' 23:59:59'])
            ->selectRaw("
                s.event_id,
                MAX(e.name) as name,
                MAX(s.event_date) as event_date,
                SUM(CASE WHEN s.outcome = ? THEN 1 ELSE 0 END) as abgebrochen,
                SUM(CASE WHEN s.outcome = ? THEN 1 ELSE 0 END) as bestellt
            ", [CheckoutStat::AUSGANG_ABGEBROCHEN, CheckoutStat::AUSGANG_BESTELLT])
            ->groupBy('s.event_id')
            ->orderByDesc('abgebrochen')
            ->limit($grenze)
            ->get();

        return $zeilen->map(function ($z) {
            $gesamt = (int) $z->abgebrochen + (int) $z->bestellt;

            return [
                'event_id'    => $z->event_id ? (int) $z->event_id : null,
                // Der Termin darf geloescht worden sein - die Vorgaenge haben
                // trotzdem stattgefunden.
                'name'        => $z->name ?: 'Gelöschter Termin',
                'datum'       => $z->event_date,
                'abgebrochen' => (int) $z->abgebrochen,
                'bestellt'    => (int) $z->bestellt,
                'quote'       => $gesamt > 0 ? round((int) $z->bestellt / $gesamt * 100, 1) : 0.0,
            ];
        })->all();
    }
}
