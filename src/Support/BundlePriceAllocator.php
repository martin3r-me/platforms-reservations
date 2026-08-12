<?php

namespace Platform\Reservation\Support;

/**
 * Verteilt den Bundle-Preis proportional auf seine Bestandteile.
 *
 * Warum überhaupt verteilt wird: Brezel 7 %, Bier 19 %. Ein Bundle mit einem
 * Gesamtpreis muss auf die Bestandteile aufgeteilt werden, sonst lässt sich die
 * Umsatzsteuer nicht je Satz ausweisen. Aufteilung PROPORTIONAL zum Einzelpreis
 * (Entscheidung mit der Steuerberatung abgestimmt).
 *
 * Warum in Cent gerechnet wird: die Summe der Bestandteile muss EXAKT dem
 * bezahlten Bundle-Preis entsprechen. Der Gast hat diesen Betrag bei Mollie
 * gezahlt; weicht der Beleg um einen Cent ab, stimmen Buchhaltung und Zahlung
 * nicht überein.
 *
 * Warum die Stückpreise nachjustiert werden: booking_items speichert Menge und
 * Stückpreis, die Summe entsteht überall im System als quantity * unit_price
 * (zehn Stellen, drei davon in rohem SQL). Ein separates "echte Summe"-Feld
 * müsste man überall nachziehen. Stattdessen werden hier Stückpreise gewählt,
 * die mit ihrer Menge multipliziert exakt aufgehen – notfalls, indem ein
 * Bestandteil in zwei Positionen mit unterschiedlichem Stückpreis zerlegt wird.
 */
class BundlePriceAllocator
{
    /**
     * @param  int  $bundleGrossCents  Bundle-Preis pro Stück, in Cent
     * @param  array<int, array{key: mixed, list_price_cents: int, quantity: int}>  $components
     * @param  int  $bundleQuantity    Wie oft das Bundle bestellt wurde
     *
     * @return array<int, array{key: mixed, quantity: int, unit_price_cents: int}>
     *         Positionen, deren Summe (quantity * unit_price) exakt
     *         bundleGrossCents * bundleQuantity ergibt.
     */
    public static function allocate(int $bundleGrossCents, array $components, int $bundleQuantity = 1): array
    {
        if ($components === [] || $bundleQuantity < 1) {
            return [];
        }

        $potCents = $bundleGrossCents * $bundleQuantity;

        // Gesamtmenge je Bestandteil über alle Bundle-Stück.
        $lines = [];
        foreach ($components as $c) {
            $quantity = max(1, (int) $c['quantity']) * $bundleQuantity;
            $lines[] = [
                'key'       => $c['key'],
                'quantity'  => $quantity,
                'reference' => max(0, (int) $c['list_price_cents']) * $quantity,
            ];
        }

        $referenceSum = array_sum(array_column($lines, 'reference'));

        // Alle Bestandteile kostenlos (oder Preise fehlen): gleichmäßig verteilen,
        // damit trotzdem eine exakte Summe entsteht.
        $targets = $referenceSum > 0
            ? self::largestRemainder($potCents, array_column($lines, 'reference'))
            : self::largestRemainder($potCents, array_fill(0, count($lines), 1));

        foreach ($lines as $i => &$line) {
            $line['target'] = $targets[$i];
        }
        unset($line);

        return self::toUnitPrices($lines, $potCents);
    }

    /**
     * Ganzzahlige Aufteilung ohne Rundungsverlust: erst abrunden, dann die
     * verbleibenden Cent an die größten Nachkomma-Reste vergeben.
     *
     * @param  array<int, int>  $weights
     * @return array<int, int>
     */
    protected static function largestRemainder(int $total, array $weights): array
    {
        $weightSum = array_sum($weights);

        if ($weightSum <= 0) {
            return array_fill(0, count($weights), 0);
        }

        $shares     = [];
        $remainders = [];
        $assigned   = 0;

        foreach ($weights as $i => $w) {
            $exact        = $total * $w / $weightSum;
            $shares[$i]   = (int) floor($exact);
            $remainders[$i] = $exact - $shares[$i];
            $assigned    += $shares[$i];
        }

        // Übrige Cent an die größten Reste; bei Gleichstand entscheidet der Index,
        // damit das Ergebnis reproduzierbar ist.
        $rest = $total - $assigned;
        arsort($remainders);

        foreach (array_keys($remainders) as $i) {
            if ($rest <= 0) {
                break;
            }
            $shares[$i]++;
            $rest--;
        }

        return $shares;
    }

    /**
     * Ziel-Summen in Positionen mit ganzzahligem Stückpreis überführen.
     *
     * Geht eine Zielsumme nicht glatt durch die Menge auf (z. B. 375 Cent auf
     * 2 Stück), wird der Bestandteil in zwei Positionen zerlegt: einige Stück
     * zum höheren, der Rest zum niedrigeren Preis. Damit bleibt die Summe exakt,
     * ohne dass irgendwo im System ein Sonderfeld nötig wird.
     *
     * @param  array<int, array{key: mixed, quantity: int, target: int}>  $lines
     * @return array<int, array{key: mixed, quantity: int, unit_price_cents: int}>
     */
    protected static function toUnitPrices(array $lines, int $potCents): array
    {
        $out = [];

        foreach ($lines as $line) {
            $quantity = $line['quantity'];
            $target   = $line['target'];

            $base      = intdiv($target, $quantity);
            $remainder = $target - $base * $quantity;   // 0 .. quantity-1

            if ($remainder === 0) {
                $out[] = ['key' => $line['key'], 'quantity' => $quantity, 'unit_price_cents' => $base];
                continue;
            }

            // $remainder Stück kosten einen Cent mehr – Summe stimmt wieder exakt.
            $out[] = ['key' => $line['key'], 'quantity' => $remainder, 'unit_price_cents' => $base + 1];
            $out[] = ['key' => $line['key'], 'quantity' => $quantity - $remainder, 'unit_price_cents' => $base];
        }

        // Sicherheitsnetz: darf nie zuschlagen, deckt aber einen Rechenfehler auf,
        // statt ihn stillschweigend in die Buchhaltung zu lassen.
        $sum = array_sum(array_map(fn ($l) => $l['quantity'] * $l['unit_price_cents'], $out));

        if ($sum !== $potCents) {
            throw new \LogicException(
                "Bundle-Aufteilung ergibt {$sum} statt {$potCents} Cent."
            );
        }

        return array_values(array_filter($out, fn ($l) => $l['quantity'] > 0));
    }
}
