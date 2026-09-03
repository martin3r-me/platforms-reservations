<?php

namespace Platform\Reservation\Support;

/**
 * Die Zeiträume der Auswertungen – Beschriftung und Spanne, an einer Stelle.
 *
 * Sie standen dreimal im Modul und jedes Mal anders: Die Artikel-Auswertung
 * kannte „Letzte Woche" bis „Alles", die Finanzen nur Jahre und „Letzte 12
 * Monate", die Bestellwege wieder die erste Liste. Wer in den Finanzen nach
 * „Dieser Monat" suchte, fand ihn nicht – und beim Nachrüsten wäre die vierte
 * Fassung entstanden.
 *
 * Was hier NICHT liegt, ist „Alles". Diese Spanne beantwortet jede Seite
 * anders, und zwar zu Recht: Die Finanzen rechnen bis zum Jahresende, weil
 * Buchungen auf künftige Termine zeigen; die Bestellwege bis heute, weil ein
 * Bestellweg in der Vergangenheit endet. Eine gemeinsame Antwort wäre für eine
 * der beiden falsch – und falsch in einer Zahl, der man es nicht ansieht.
 */
final class Zeitraum
{
    /**
     * Die Zeiträume, die jede Auswertung anbieten kann.
     *
     * Reihenfolge = Reihenfolge in der Leiste, von kurz nach lang.
     *
     * @return array<string, string>
     */
    public static function beschriftungen(): array
    {
        return [
            'last_week'  => 'Letzte Woche',
            'month'      => 'Dieser Monat',
            'last_month' => 'Letzter Monat',
            'quarter'    => 'Dieses Quartal',
            'year'       => 'Dieses Jahr',
            'last_year'  => 'Letztes Jahr',
        ];
    }

    /**
     * Von–bis für einen Zeitraum, oder null für einen, den es hier nicht gibt.
     *
     * Null statt eines Ersatzwerts: Wer „Alles" oder etwas Eigenes meint,
     * soll es selbst beantworten und nicht stillschweigend „dieses Jahr"
     * bekommen.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function spanne(string $preset): ?array
    {
        return match ($preset) {
            'last_week'  => [
                now()->subWeek()->startOfWeek()->toDateString(),
                now()->subWeek()->endOfWeek()->toDateString(),
            ],
            'month'      => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
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
            default      => null,
        };
    }
}
