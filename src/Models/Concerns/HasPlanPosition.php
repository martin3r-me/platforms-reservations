<?php

namespace Platform\Reservation\Models\Concerns;

/**
 * Lage einer Fläche im Tischplan – geteilt von Tisch und Abholstation.
 *
 * Die Koordinaten sind normalisiert (0…1) und meinen den MITTELPUNKT; Breite
 * und Höhe sind Anteile der Planfläche. Daraus eine CSS-Fläche zu rechnen ist
 * drei Zeilen – und genau deshalb gehört es an eine Stelle: Zwei Fassungen
 * derselben Rechnung sind die Sorte Fehler, die erst auffällt, wenn im Shop
 * etwas zwei Pixel danebenliegt, und dann sucht man sie im falschen Modell.
 *
 * Eine Station MUSS keine Position haben („Foyer links" liegt nicht im Saal).
 * Ohne Position gibt es keine Fläche – und `hatPosition()` sagt es, statt eine
 * Fläche bei 0/0 mit Größe 0 zu liefern, die niemand sieht und die niemand
 * erklären kann.
 */
trait HasPlanPosition
{
    /** Ausrichtung auf 0…315 in 45°-Schritten bringen – auch für negative Werte. */
    public static function normalizeRotation(int $degrees): int
    {
        $step = (int) round($degrees / 45) * 45;

        return (($step % 360) + 360) % 360;
    }

    public function hatPosition(): bool
    {
        return $this->x_pct !== null
            && $this->y_pct !== null
            && $this->w_pct !== null
            && $this->h_pct !== null;
    }

    public function surfaceStyle(): string
    {
        if (! $this->hatPosition()) {
            return '';
        }

        $left = ($this->x_pct - $this->w_pct / 2) * 100;
        $top  = ($this->y_pct - $this->h_pct / 2) * 100;

        return sprintf(
            'left:%.4f%%; top:%.4f%%; width:%.4f%%; height:%.4f%%;',
            $left,
            $top,
            $this->w_pct * 100,
            $this->h_pct * 100,
        );
    }
}
