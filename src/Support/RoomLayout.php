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
 *   { "room": {
 *       "paths":   [ [[x,y],[x,y], …], … ],
 *       "markers": [ { "x": …, "y": …, "label": "Eingang", "gap": true,
 *                      "w": …, "h": … }, … ]
 *   } }
 *
 * w/h sind die Ausdehnung einer Beschriftung, ebenfalls normalisiert, und
 * stehen nur dort, wo eine eingestellt ist. Eine Theke ist vier Meter lang
 * oder einen – als reiner Punkt wäre sie im Plan nicht wiederzuerkennen.
 * Fehlen sie, ist die Beschriftung ein Punkt wie bisher.
 *
 * Andere Schlüssel in layout_json bleiben unangetastet.
 */
class RoomLayout
{
    /** Höchstzahlen – gegen versehentlich riesige Nutzlasten. */
    public const MAX_PATHS   = 20;
    public const MAX_POINTS  = 200;
    public const MAX_MARKERS = 30;
    public const MAX_LABEL   = 40;

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
     * Beschriftungen lesen ("Eingang", "Bühne").
     *
     * @return array<int, array{x: float, y: float, label: string, gap: bool, w?: float, h?: float}>
     */
    public static function markers(?FloorPlan $plan): array
    {
        if (! $plan) {
            return [];
        }

        return self::sanitizeMarkers(data_get($plan->layout_json, 'room.markers', []));
    }

    /**
     * Züge speichern. Ersetzt die bisherigen; ein leeres Array löscht sie.
     *
     * @param  array<mixed>  $paths
     */
    public static function savePaths(FloorPlan $plan, array $paths): void
    {
        self::write($plan, self::sanitize($paths), self::markers($plan));
    }

    /**
     * Beschriftungen speichern.
     *
     * @param  array<mixed>  $markers
     */
    public static function saveMarkers(FloorPlan $plan, array $markers): void
    {
        self::write($plan, self::paths($plan), self::sanitizeMarkers($markers));
    }

    /**
     * Beides zusammen ablegen.
     *
     * Sind weder Züge noch Beschriftungen da, verschwindet der Schlüssel wieder –
     * layout_json sieht dann aus wie vor dieser Funktion. Fremde Schlüssel
     * bleiben unangetastet.
     */
    protected static function write(FloorPlan $plan, array $paths, array $markers): void
    {
        $layout = $plan->layout_json ?? [];

        if ($paths === [] && $markers === []) {
            unset($layout['room']);
        } else {
            $layout['room'] = array_filter([
                'paths'   => $paths,
                'markers' => $markers,
            ]);
        }

        $plan->update(['layout_json' => $layout ?: null]);
    }

    /**
     * Beschriftungen säubern: Lage in [0,1], Text gekürzt, Anzahl begrenzt,
     * Leeres verworfen.
     *
     * @param  mixed  $markers
     * @return array<int, array{x: float, y: float, label: string, gap: bool, w?: float, h?: float}>
     */
    public static function sanitizeMarkers($markers): array
    {
        if (! is_array($markers)) {
            return [];
        }

        $out = [];

        foreach (array_slice($markers, 0, self::MAX_MARKERS) as $m) {
            if (! is_array($m) || ! isset($m['x'], $m['y'])) {
                continue;
            }

            if (! is_numeric($m['x']) || ! is_numeric($m['y'])) {
                continue;
            }

            $label = trim((string) ($m['label'] ?? ''));

            // Ohne Text hat der Punkt keinen Zweck – er soll ja etwas benennen.
            if ($label === '') {
                continue;
            }

            $eintrag = [
                'x'     => round(min(1, max(0, (float) $m['x'])), 4),
                'y'     => round(min(1, max(0, (float) $m['y'])), 4),
                'label' => mb_substr($label, 0, self::MAX_LABEL),
                // Öffnet die Wand an dieser Stelle (Tür). Reine Darstellung –
                // die Punkte des Zugs bleiben unverändert.
                'gap'   => (bool) ($m['gap'] ?? false),
            ];

            // Ausdehnung nur ablegen, wenn es eine gibt: Ein Punkt bleibt dann
            // Byte für Byte das, was er vor dieser Erweiterung war.
            foreach (['w', 'h'] as $achse) {
                $wert = self::mass($m[$achse] ?? null);

                if ($wert > 0) {
                    $eintrag[$achse] = $wert;
                }
            }

            $out[] = $eintrag;
        }

        return $out;
    }

    /** Eine Ausdehnung auf [0,1] begrenzen; alles Unbrauchbare wird zu 0. */
    protected static function mass($wert): float
    {
        if (! is_numeric($wert)) {
            return 0.0;
        }

        return round(min(1, max(0, (float) $wert)), 4);
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
