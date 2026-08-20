<?php

namespace Platform\Reservation\Support;

use Platform\Reservation\Models\FloorPlan;

/**
 * Raumumriss eines Grundrisses – Linienzüge über der Zeichenfläche.
 *
 * VERSUCHSSTAND. Bewusst so gebaut, dass sich die Funktion rückstandsfrei
 * entfernen lässt:
 *
 *   - Keine Migration. Die Daten liegen in FloorPlan.layout_json, das seit
 *     jeher existiert und bisher nie beschrieben wurde. Fällt die Funktion
 *     weg, bleibt die Spalte ungenutzt zurück wie zuvor.
 *   - Kein Eingriff in die Tische. Der Umriss ist eine eigene Ebene.
 *   - Nicht in der Gast-API. Der Shop sieht davon vorerst nichts.
 *
 * Koordinaten sind normalisiert (0…1) auf die Zeichenfläche – dieselbe
 * Konvention wie bei den Tischen. Weil das Seitenverhältnis der Fläche aus
 * dem Hintergrundbild stammt, liegt ein über dem Bild gezeichneter Zug
 * automatisch deckungsgleich zu den Tischen.
 *
 * Format in layout_json:
 *   { "room": { "paths": [ [[x,y],[x,y], …], … ] } }
 *
 * Andere Schlüssel in layout_json bleiben unangetastet.
 */
class RoomLayout
{
    /** Höchstzahl Züge und Punkte – gegen versehentlich riesige Nutzlasten. */
    public const MAX_PATHS  = 20;
    public const MAX_POINTS = 200;

    /**
     * Züge eines Grundrisses lesen.
     *
     * @return array<int, array<int, array{0: float, 1: float}>>
     */
    public static function paths(?FloorPlan $plan): array
    {
        if (! $plan) {
            return [];
        }

        return self::sanitize(data_get($plan->layout_json, 'room.paths', []));
    }

    /**
     * Züge speichern. Ersetzt die bisherigen; ein leeres Array löscht sie.
     *
     * @param  array<mixed>  $paths
     */
    public static function save(FloorPlan $plan, array $paths): void
    {
        $layout = $plan->layout_json ?? [];
        $sauber = self::sanitize($paths);

        if ($sauber === []) {
            // Nichts Leeres zurücklassen: Ohne Züge verschwindet der Schlüssel
            // wieder, damit layout_json so aussieht wie vor der Funktion.
            unset($layout['room']);
        } else {
            $layout['room'] = ['paths' => $sauber];
        }

        $plan->update(['layout_json' => $layout ?: null]);
    }

    /**
     * Eingaben vom Client säubern: nur Zahlenpaare in [0,1], Züge mit
     * mindestens zwei Punkten, begrenzte Anzahl.
     *
     * @param  mixed  $paths
     * @return array<int, array<int, array{0: float, 1: float}>>
     */
    public static function sanitize($paths): array
    {
        if (! is_array($paths)) {
            return [];
        }

        $out = [];

        foreach (array_slice($paths, 0, self::MAX_PATHS) as $path) {
            if (! is_array($path)) {
                continue;
            }

            $punkte = [];

            foreach (array_slice($path, 0, self::MAX_POINTS) as $punkt) {
                if (! is_array($punkt) || ! isset($punkt[0], $punkt[1])) {
                    continue;
                }

                if (! is_numeric($punkt[0]) || ! is_numeric($punkt[1])) {
                    continue;
                }

                $punkte[] = [
                    round(min(1, max(0, (float) $punkt[0])), 4),
                    round(min(1, max(0, (float) $punkt[1])), 4),
                ];
            }

            // Ein einzelner Punkt ist kein Linienzug.
            if (count($punkte) >= 2) {
                $out[] = $punkte;
            }
        }

        return $out;
    }
}
