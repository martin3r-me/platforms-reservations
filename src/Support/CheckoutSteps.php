<?php

namespace Platform\Reservation\Support;

/**
 * Die Schritte des Bestellwegs, wie das Office sie benennt und ordnet.
 *
 * Eine Stelle, weil es sonst zwei wären: Die laufenden Bestellwege
 * (CheckoutSession) und ihre Auswertung (CheckoutStat) reden über dieselben
 * Namen, und stünden dort zwei Zuordnungen, hieße derselbe Schritt in der
 * Live-Sicht irgendwann anders als in der Statistik.
 *
 * Die REIHENFOLGE ist bewusst vollständig, obwohl ein einzelner Bestellweg
 * nicht alle Schritte hat – „Wann?" gibt es nur bei mehreren Pausen. Sie sagt
 * nicht, welche Schritte vorkommen, sondern nur, in welcher Reihenfolge die
 * Namen untereinander stehen sollen.
 *
 * Die Namen kommen aus dem Shop (CheckoutWizard::schritte()). Ein Name, den
 * diese Liste nicht kennt, verschwindet nicht: Er trägt sich selbst als
 * Beschriftung und landet hinten. Damit übersteht die Auswertung einen neuen
 * Schritt im Shop, ohne dass hier zuerst jemand nachziehen muss.
 */
final class CheckoutSteps
{
    public const REIHENFOLGE = ['party', 'when', 'products', 'seat', 'guest', 'pay'];

    /** Die Schritte, nach denen es nur noch die Zahlung gibt. */
    public const FAST_FERTIG = ['guest', 'pay'];

    public static function label(string $schritt): string
    {
        return match ($schritt) {
            'party'    => 'Personenzahl',
            'when'     => 'Pause',
            'products' => 'Produkte',
            'seat'     => 'Sitzplatz',
            'guest'    => 'Gastdaten',
            'pay'      => 'Bezahlung',
            default    => $schritt,
        };
    }

    /** Wo in der Reihenfolge ein Schrittname steht (unbekannte hinten). */
    public static function stelle(string $schritt): int
    {
        $stelle = array_search($schritt, self::REIHENFOLGE, true);

        return $stelle === false ? count(self::REIHENFOLGE) : (int) $stelle;
    }
}
