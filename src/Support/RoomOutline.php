<?php

namespace Platform\Reservation\Support;

/**
 * Wandlinie eines Raumumrisses als fertiges SVG-d-Attribut.
 *
 * VERSUCHSSTAND, gehört zu RoomLayout und fällt mit ihm weg.
 *
 * Warum serverseitig: Der Shop soll den Umriss zeichnen können, ohne die
 * Geometrie nachzubauen. Er bekommt deshalb keine Rohdaten mit dem Auftrag,
 * daraus Türöffnungen zu rechnen, sondern die fertige Linie – und malt sie in
 * ein <svg viewBox="0 0 1 1">.
 *
 * ACHTUNG, ZWEITE FASSUNG: Der Editor rechnet dasselbe in JavaScript
 * (roomDraw.stueckMitLuecken in floor-plan-editor.blade.php). Das ist nicht
 * vermeidbar – beim Zeichnen muss die Lücke sofort erscheinen, ohne Runde zum
 * Server. Beide Fassungen benutzen daher dieselben Anteile statt fester Pixel,
 * und ein Test vergleicht sie Wert für Wert gegeneinander. Ändert sich hier
 * etwas, muss es dort mit.
 */
class RoomOutline
{
    /**
     * Halbe Breite einer Öffnung ohne eigene Fläche – als Anteil der Planbreite.
     *
     * Bewusst relativ und nicht in Pixeln: Sonst wäre dieselbe Tür im Editor
     * und im Shop verschieden breit, und beim Zoomen würde sie wandern.
     */
    public const OEFFNUNG_ANTEIL = 0.015;

    /** Wie nah eine Beschriftung an einer Wand liegen muss, damit sie sie öffnet. */
    public const TOLERANZ_ANTEIL = 0.002;

    /**
     * @param  array<int, array<int, array{0: float, 1: float}>>  $paths
     * @param  array<int, array<string, mixed>>  $markers
     * @param  float  $aspect  Breite/Höhe der Zeichenfläche (FloorPlan::displayAspect)
     */
    public static function path(array $paths, array $markers, float $aspect = 4 / 3): string
    {
        // Gerechnet wird in einem Raum, der so breit wie 1 und so hoch wie das
        // Seitenverhältnis ist. In normalisierten Werten allein wäre der Abstand
        // zu einer schrägen Wand verzerrt.
        $hoehe = $aspect > 0 ? 1 / $aspect : 0.75;

        $teile = [];

        foreach ($paths as $pfad) {
            if (! is_array($pfad) || count($pfad) < 2) {
                continue;
            }

            $pfad = array_values($pfad);

            for ($i = 0; $i < count($pfad) - 1; $i++) {
                foreach (self::stuecke($pfad[$i], $pfad[$i + 1], $markers, $hoehe) as [$von, $bis]) {
                    $teile[] = 'M' . self::zahl($von[0]) . ' ' . self::zahl($von[1])
                             . ' L' . self::zahl($bis[0]) . ' ' . self::zahl($bis[1]);
                }
            }
        }

        return implode(' ', $teile);
    }

    /**
     * Ein Wandstück in die Teile zerlegen, die nach Abzug der Öffnungen bleiben.
     *
     * @return array<int, array{0: array{0: float, 1: float}, 1: array{0: float, 1: float}}>
     */
    protected static function stuecke(array $a, array $b, array $markers, float $hoehe): array
    {
        $ax = (float) $a[0];
        $ay = (float) $a[1] * $hoehe;
        $bx = (float) $b[0];
        $by = (float) $b[1] * $hoehe;

        $laenge = hypot($bx - $ax, $by - $ay);

        if ($laenge <= 0.0) {
            return [[$a, $b]];
        }

        $ex = ($bx - $ax) / $laenge;
        $ey = ($by - $ay) / $laenge;

        $luecken = [];

        foreach ($markers as $m) {
            if (empty($m['gap'])) {
                continue;
            }

            $mx = (float) ($m['x'] ?? 0);
            $my = (float) ($m['y'] ?? 0) * $hoehe;

            $t = (($mx - $ax) * ($bx - $ax) + ($my - $ay) * ($by - $ay)) / ($laenge * $laenge);
            $t = min(1.0, max(0.0, $t));

            $fx = $ax + $t * ($bx - $ax);
            $fy = $ay + $t * ($by - $ay);

            // Nur wenn die Beschriftung wirklich auf DIESEM Stück sitzt.
            if (hypot($mx - $fx, $my - $fy) > self::TOLERANZ_ANTEIL) {
                continue;
            }

            $w = (float) ($m['w'] ?? 0);
            $h = (float) ($m['h'] ?? 0);

            // Die Öffnung ist so breit wie der Schatten, den die Fläche auf die
            // Wand wirft – ein zwei Meter breites Tor reißt zwei Meter Wand auf.
            $halb = ($w > 0 || $h > 0)
                ? abs($w / 2 * $ex) + abs($h * $hoehe / 2 * $ey)
                : self::OEFFNUNG_ANTEIL;

            $halb /= $laenge;

            $luecken[] = [max(0.0, $t - $halb), min(1.0, $t + $halb)];
        }

        if ($luecken === []) {
            return [[$a, $b]];
        }

        usort($luecken, fn ($x, $y) => $x[0] <=> $y[0]);

        $punkt = fn (float $t) => [
            $a[0] + $t * ($b[0] - $a[0]),
            $a[1] + $t * ($b[1] - $a[1]),
        ];

        $teile  = [];
        $cursor = 0.0;

        foreach ($luecken as [$von, $bis]) {
            if ($von > $cursor) {
                $teile[] = [$punkt($cursor), $punkt($von)];
            }

            $cursor = max($cursor, $bis);
        }

        if ($cursor < 1.0) {
            $teile[] = [$punkt($cursor), $punkt(1.0)];
        }

        return $teile;
    }

    /** Kurze Schreibweise – das d-Attribut geht über die Leitung. */
    protected static function zahl(float $wert): string
    {
        $s = rtrim(rtrim(number_format($wert, 5, '.', ''), '0'), '.');

        return $s === '' || $s === '-' ? '0' : $s;
    }
}
