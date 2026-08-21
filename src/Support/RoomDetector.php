<?php

namespace Platform\Reservation\Support;

use GdImage;

/**
 * Wände aus einem Grundriss-Bild vorschlagen.
 *
 * VERSUCHSSTAND, gehört zu RoomLayout und fällt mit ihm weg. Das Ergebnis ist
 * ein VORSCHLAG für den Editor, kein gespeicherter Umriss – angenommen wird er
 * von Hand.
 *
 * NICHT nach Linien gesucht. Das kommt von den echten Plänen: Deren Böden sind
 * mit Fischgrät-Parkett oder Marmor gefüllt, und eine Linienerkennung würde in
 * diesem Muster ertrinken – Hunderte kurzer Striche, alle so lang wie eine Wand
 * dick ist.
 *
 * Gefragt wird stattdessen: WOHIN KOMMT DAS PAPIER VON DRAUSSEN? Vom Bildrand
 * aus wird durchs Weiß geflutet, und dieses Draußen wird dann geöffnet – erst
 * geschrumpft, dann wieder geweitet. Das Schrumpfen kappt schmale Ausläufer,
 * das Weiten stellt die Grenze wieder her. Der Raum ist, was das Draußen so
 * nicht erreicht.
 *
 * Der Weg über das Papier statt über die Tinte trägt drei Fälle auf einmal:
 *
 *   - Gefüllter Boden (Parkett, Marmor): Der Innenraum ist gar nicht Papier,
 *     also von außen ohnehin unerreichbar.
 *   - Weißer Innenraum mit Wandblöcken, zwischen denen Fenster sitzen: Das
 *     Papier sickert nur durch schmale Lücken hinein, und genau die kappt das
 *     Schrumpfen. Ein Weg über die Tinte scheiterte hier – jeder Wandblock war
 *     ein eigener Fleck, gefunden wurde nur der eine durchgehende Streifen.
 *   - Beschriftungen und Türbögen: Kleine Tinteninseln im Papier werden beim
 *     Weiten überdeckt und gehören danach zum Draußen. Sie verschwinden, ohne
 *     dass jemand nach ihrer Größe fragen muss – ein Versuch, sie über die
 *     Größe zu erkennen, warf prompt die Wandblöcke mit weg, denn die sind
 *     kleiner als eine Legende.
 *
 * Der Umriss folgt der Außenkante der Wände. Die Innenkante wäre um die
 * Mauerstärke genauer – bei den geprüften Plänen ein bis zwei Prozent der
 * Bildbreite. Das ist weniger, als die Vereinfachung ohnehin glättet, und die
 * Außenkante ist die robustere Spur.
 */
final class RoomDetector
{
    /** Auflösung, in der gerechnet wird. Feiner bringt nichts, kostet nur Zeit. */
    public const ARBEITSBREITE = 300;

    /** Ab diesem Wert (kleinster Farbkanal) gilt ein Pixel als weißes Papier. */
    public const PAPIER = 250;

    /**
     * Radius, mit dem das Draußen geöffnet wird, in Arbeitspixeln.
     *
     * Er bestimmt, wie breit eine Öffnung sein darf, durch die das Papier noch
     * hereinkommt: Alles bis zur doppelten Radiusbreite wird gekappt. Fenster
     * und Türen im Plan liegen darunter, ein offener Saalzugang darüber – dort
     * soll der Umriss auch offen bleiben.
     *
     * Zugleich die Größe, bis zu der Tinteninseln im Papier (Beschriftungen,
     * Türbögen) beim Weiten überdeckt werden.
     */
    public const ABSCHLUSS = 12;


    /** Feinschliff am Raum: schneidet zurückgebliebene Fäden ab. */
    public const OEFFNEN = 2;

    /**
     * Ab dieser Tintendichte in der Bildmitte gilt der Boden als gefüllt.
     *
     * Die Weiche zwischen den zwei Wegen. Gemessen wird in der mittleren Hälfte
     * des Tinten-Rechtecks: Parkett und Marmor füllen sie fast vollständig, eine
     * Strichzeichnung hat dort nur ein paar Möbel.
     */
    public const DICHTE_GEFUELLT = 0.4;

    /** Schließen im Tinten-Weg: Fenster- und Türkerben im Umriss zumachen. */
    public const SCHLIESSEN = 6;

    /** Haarrisse überbrücken, bevor der Fleck gewählt wird (Tinten-Weg). */
    public const VORSCHLIESSEN = 1;

    /**
     * Tinteninseln unter diesem Anteil fliegen im Papier-Weg raus.
     *
     * Nur dort nötig und nur dort gefahrlos: In einer Strichzeichnung ist eine
     * Beschriftung um ein Mehrfaches kleiner als ein Wandblock. Im Tinten-Weg
     * wäre dieselbe Grenze schädlich – da fällt Beiwerk ohnehin über die
     * Fleckwahl weg.
     */
    public const INSEL = 0.002;

    /** Douglas-Peucker: erlaubte Abweichung in Arbeitspixeln. */
    public const GENAUIGKEIT = 2.5;

    /** Bis zu diesem Anteil der Kantenlänge gilt eine Kante als waagerecht/senkrecht. */
    public const ACHSE_ANTEIL = 0.12;

    /**
     * Wie weit zwei Wände auseinanderliegen dürfen, um als dieselbe zu gelten –
     * als Anteil der Bildkante.
     *
     * Fortsetzung des Achsen-Einrastens auf der nächsten Ebene: Eine Wand ist
     * nicht nur gerade, sie liegt auch mit den anderen Stücken derselben Wand
     * auf einer Linie. Fensterkerben und Türflügel weichen um die Mauerstärke
     * ab – die fallen damit auf die Hauptlinie zurück. Eine echte Nische ist
     * tiefer und bleibt.
     */
    public const BUENDEL_ANTEIL = 0.04;

    /** Kürzere Kanten als diese (Arbeitspixel) verschwinden beim Aufräumen. */
    public const MIN_KANTE = 3;

    /** Kleinere Flecken als dieser Anteil des Bildes sind kein Raum. */
    public const MIN_FLAECHE = 0.02;

    /**
     * Deckt der gefundene Raum weniger als diesen Anteil des Wand-Rechtecks ab,
     * gilt er als nicht gefunden – dann wird das Rechteck selbst vorgeschlagen.
     *
     * Der Rückfall für Säle, die NICHT umschlossen sind. Ein großer Zugang ist
     * baulich eine offene Seite, und dann trägt keine der beiden Suchen: Das
     * Papier läuft ungehindert herein, und übrig bleibt irgendein Zwickel neben
     * der Öffnung. Der Rahmen um die Wände ist dort das ehrlichere Angebot – ein
     * Rechteck, das der Mensch nachzieht, statt eines Zwickels, den er löscht.
     *
     * Die Grenze liegt tief genug, dass ein L-förmiger Saal (rund zwei Drittel
     * seines Rechtecks) seine Form behält.
     */
    public const GRENZE_RAHMEN = 0.35;

    /** Wie tief ein erkannter Eingang in die Wand reicht, in Arbeitspixeln. */
    public const TIEFE_EINGANG = 6;

    /** Schmalere Lücken als diese (Arbeitspixel) sind Ecken-Ungenauigkeit, keine Tür. */
    public const MIN_OEFFNUNG = 6;

    /** Wie weit senkrecht in die Wand geschaut wird, um sie zu finden. */
    public const BAND_INNEN = 12;

    /**
     * Eingänge zu einem vorhandenen Umriss vorschlagen.
     *
     * Zweiter, eigener Schritt – und er braucht den ersten: Gesucht wird dort,
     * wo der Umriss durch WEISSES PAPIER läuft. Eine Wand ist Tinte, eine
     * Öffnung ist keine.
     *
     * Geschaut wird senkrecht nach innen in ein Band, nicht auf den Punkt der
     * Linie selbst. Zwei Gründe: Der Umriss liegt nach dem Vereinfachen nicht
     * pixelgenau auf der Wand, und – wichtiger – Fenster liegen in der Wand,
     * nicht durch sie hindurch. Wer nur die Außenkante prüft, hält jedes Fenster
     * für eine Tür; wer in die Wand hineinschaut, findet dahinter noch Mauer.
     *
     * @param  array<int, array<int, array{0: float, 1: float}>>  $paths
     * @return array<int, array<string, mixed>>
     */
    public static function eingaenge(GdImage $bild, array $paths): array
    {
        [$maske, $bw, $bh] = self::maske($bild);

        if ($bw < 8 || $bh < 8) {
            return [];
        }

        $marker = [];

        foreach ($paths as $pfad) {
            $pfad = array_values($pfad);

            if (count($pfad) < 2) {
                continue;
            }

            [$mx, $my] = self::mitte($pfad, $bw, $bh);

            for ($i = 0; $i < count($pfad) - 1; $i++) {
                $ax = $pfad[$i][0] * $bw;
                $ay = $pfad[$i][1] * $bh;
                $bx = $pfad[$i + 1][0] * $bw;
                $by = $pfad[$i + 1][1] * $bh;

                $laenge = hypot($bx - $ax, $by - $ay);

                if ($laenge < self::MIN_OEFFNUNG * 2) {
                    continue;
                }

                // Senkrechte, zur Mitte des Zugs hin gedreht: dort steht die Wand.
                $ex = ($bx - $ax) / $laenge;
                $ey = ($by - $ay) / $laenge;
                $nx = $ey;
                $ny = -$ex;

                if ((($mx - ($ax + $bx) / 2) * $nx + ($my - ($ay + $by) / 2) * $ny) < 0) {
                    $nx = -$nx;
                    $ny = -$ny;
                }

                foreach (self::freieStuecke($maske, $bw, $bh, $ax, $ay, $bx, $by, $nx, $ny) as [$von, $bis]) {
                    $t = ($von + $bis) / 2;

                    $px = $ax + $ex * $t;
                    $py = $ay + $ey * $t;

                    $offen = $bis - $von;
                    $waagerecht = abs($ex) >= abs($ey);

                    $marker[] = [
                        'x'     => round(min(1, max(0, $px / $bw)), 4),
                        'y'     => round(min(1, max(0, $py / $bh)), 4),
                        'w'     => round(($waagerecht ? $offen : self::TIEFE_EINGANG) / $bw, 4),
                        'h'     => round(($waagerecht ? self::TIEFE_EINGANG : $offen) / $bh, 4),
                        'label' => 'Eingang',
                        'gap'   => true,
                    ];
                }
            }
        }

        return array_slice($marker, 0, RoomLayout::MAX_MARKERS);
    }

    /**
     * Mitte des umschließenden Rechtecks eines Zugs, in Arbeitspixeln.
     *
     * Nicht der Schwerpunkt der Punkte: Der doppelte Schlusspunkt und ungleich
     * verteilte Ecken zögen ihn aus der Mitte.
     *
     * @param  array<int, array{0: float, 1: float}>  $pfad
     * @return array{0: float, 1: float}
     */
    protected static function mitte(array $pfad, int $bw, int $bh): array
    {
        $xs = array_column($pfad, 0);
        $ys = array_column($pfad, 1);

        return [
            (min($xs) + max($xs)) / 2 * $bw,
            (min($ys) + max($ys)) / 2 * $bh,
        ];
    }

    /**
     * Stücke einer Kante, hinter denen keine Wand steht.
     *
     * @return array<int, array{0: float, 1: float}>
     */
    protected static function freieStuecke(
        string $maske, int $bw, int $bh,
        float $ax, float $ay, float $bx, float $by, float $nx, float $ny
    ): array {
        $laenge = hypot($bx - $ax, $by - $ay);
        $ex = ($bx - $ax) / $laenge;
        $ey = ($by - $ay) / $laenge;

        $wand = function (float $x, float $y) use ($maske, $bw, $bh, $nx, $ny): bool {
            // Von zwei Pixeln vor der Linie bis tief in die Wand hinein.
            for ($d = -2; $d <= self::BAND_INNEN; $d++) {
                $ix = (int) round($x + $nx * $d);
                $iy = (int) round($y + $ny * $d);

                if ($ix < 0 || $iy < 0 || $ix >= $bw || $iy >= $bh) {
                    continue;
                }

                if ($maske[$iy * $bw + $ix] === "\1") {
                    return true;
                }
            }

            return false;
        };

        $stuecke = [];
        $start   = null;

        for ($t = 0.0; $t <= $laenge; $t += 1.0) {
            $frei = ! $wand($ax + $ex * $t, $ay + $ey * $t);

            if ($frei && $start === null) {
                $start = $t;
            }

            if (! $frei && $start !== null) {
                $stuecke[] = [$start, $t];
                $start = null;
            }
        }

        if ($start !== null) {
            $stuecke[] = [$start, $laenge];
        }

        // Zu schmale Stücke sind Ungenauigkeit an den Ecken, keine Tür.
        return array_values(array_filter($stuecke, fn ($s) => $s[1] - $s[0] >= self::MIN_OEFFNUNG));
    }

    /**
     * Umriss aus Bilddaten. Leeres Ergebnis heißt: nichts Brauchbares gefunden.
     *
     * @return array<int, array<int, array{0: float, 1: float}>>
     */
    public static function ausBinaerdaten(string $binaer): array
    {
        $bild = @imagecreatefromstring($binaer);

        if (! $bild) {
            return [];
        }

        try {
            return self::ausBild($bild);
        } finally {
            imagedestroy($bild);
        }
    }

    /**
     * @return array<int, array<int, array{0: float, 1: float}>>
     */
    public static function ausBild(GdImage $bild): array
    {
        [$maske, $bw, $bh] = self::maske($bild);

        if ($bw < 8 || $bh < 8) {
            return [];
        }

        // Die Reihenfolge ist der Kern dieser Methode, und sie war zweimal falsch:
        //
        // Rechteck um die tragende Tinte – Maßstab für die Plausibilitätsprüfung
        // unten und Rückfall, wenn der Saal keine geschlossene Wand hat.
        $tragend = $maske;
        self::inselnEntfernen($tragend, $bw, $bh);
        $rahmen = self::rahmen($tragend, $bw, $bh);

        // Zwei Wege, und die Vorlage entscheidet. Ein Kompromiss aus beiden war
        // schlechter als jeder einzeln: Der Radius, der Fensterlücken in einer
        // Strichzeichnung zumacht, frisst in einem gefüllten Plan die schmalen
        // Papierstreifen zwischen Raum und Beschriftung.
        $raum = self::bodenGefuellt($maske, $bw, $bh)
            ? self::raumAusTinte($maske, $bw, $bh)
            : self::raumAusPapier($maske, $bw, $bh);

        self::loecherFuellen($raum, $bw, $bh);
        self::oeffnen($raum, $bw, $bh, self::OEFFNEN);

        $fleck = self::groessterFleck($raum, $bw, $bh);

        if ($fleck === null) {
            return [];
        }

        [$flaeche, $start] = $fleck;

        if (substr_count($flaeche, "\1") < self::MIN_FLAECHE * $bw * $bh) {
            return [];
        }

        // Deckt der Raum das Wand-Rechteck nicht halbwegs ab, war die Wand offen
        // und gefunden wurde ein Zwickel. Dann lieber das Rechteck anbieten.
        if ($rahmen !== null) {
            [$rx0, $ry0, $rx1, $ry1] = $rahmen;
            $flaecheRahmen = ($rx1 - $rx0 + 1) * ($ry1 - $ry0 + 1);

            if ($flaecheRahmen > 0
                && substr_count($flaeche, "\1") < self::GRENZE_RAHMEN * $flaecheRahmen) {
                return self::alsZug([
                    [$rx0, $ry0], [$rx1, $ry0], [$rx1, $ry1], [$rx0, $ry1], [$rx0, $ry0],
                ], $bw, $bh);
            }
        }

        $rand = self::randVerfolgen($flaeche, $bw, $bh, $start);

        if (count($rand) < 8) {
            return [];
        }

        $punkte = self::vereinfachen($rand, self::GENAUIGKEIT);
        $punkte = self::achsenEinrasten($punkte);
        $punkte = self::kantenBuendeln($punkte, $bw, $bh);
        $punkte = self::aufraeumen($punkte);

        // Letzter Schliff: Das Bündeln zieht die Kanten auf gemeinsame Linien,
        // lässt dabei aber den ersten Punkt an seiner alten Stelle – der
        // Schlusskante fehlte danach ein Pixel zur Geraden.
        $punkte = self::achsenEinrasten($punkte);
        $punkte = self::aufraeumen($punkte);

        if (count($punkte) < 3) {
            return [];
        }

        // Ring schließen: derselbe Punkt am Ende wie am Anfang, wie beim
        // Zeichnen von Hand.
        $punkte[] = $punkte[0];

        return self::alsZug($punkte, $bw, $bh);
    }

    /**
     * Bild auf Arbeitsauflösung bringen und in "Papier / nicht Papier" trennen.
     *
     * Ein Arbeitspixel ist Tinte, sobald IRGENDEIN Quellpixel darin Tinte ist.
     * Eine gemittelte Verkleinerung würde dünne Wandlinien wegwaschen.
     *
     * Die Maske ist eine ZEICHENKETTE mit einem Byte je Pixel, kein Array. Ein
     * PHP-Array kostet pro Eintrag rund vierzig Byte; bei 300x225 Pixeln und
     * einem Dutzend Zwischenständen sprengte das die Speichergrenze eines
     * gewöhnlichen Laravel-Prozesses.
     *
     * @return array{0: string, 1: int, 2: int}
     */
    protected static function maske(GdImage $bild): array
    {
        $qw = imagesx($bild);
        $qh = imagesy($bild);

        if ($qw < 1 || $qh < 1) {
            return ['', 0, 0];
        }

        // Sehr große Vorlagen erst grob verkleinern – sonst wird jedes Pixel
        // einzeln gelesen und das dauert. Bilinear verwischt eine dünne Linie
        // zu Grau, und Grau ist immer noch Tinte.
        $arbeit = $bild;
        $eigen  = false;

        if ($qw > 4 * self::ARBEITSBREITE) {
            $klein = imagescale($bild, 4 * self::ARBEITSBREITE, -1, IMG_BILINEAR_FIXED);

            if ($klein) {
                $arbeit = $klein;
                $eigen  = true;
                $qw     = imagesx($arbeit);
                $qh     = imagesy($arbeit);
            }
        }

        $bw = min(self::ARBEITSBREITE, $qw);
        $bh = max(1, (int) round($qh * $bw / $qw));

        $maske = str_repeat("\0", $bw * $bh);

        for ($y = 0; $y < $bh; $y++) {
            $y0 = (int) floor($y * $qh / $bh);
            $y1 = max($y0 + 1, (int) floor(($y + 1) * $qh / $bh));

            for ($x = 0; $x < $bw; $x++) {
                $x0 = (int) floor($x * $qw / $bw);
                $x1 = max($x0 + 1, (int) floor(($x + 1) * $qw / $bw));

                $tinte = 0;

                for ($sy = $y0; $sy < $y1 && ! $tinte; $sy++) {
                    for ($sx = $x0; $sx < $x1; $sx++) {
                        $c = imagecolorat($arbeit, $sx, $sy);

                        // Durchsichtiges zählt als Papier – ein freigestellter
                        // Plan hat außen keine Farbe, nicht Weiß.
                        if ((($c >> 24) & 0x7F) > 100) {
                            continue;
                        }

                        if (min(($c >> 16) & 255, ($c >> 8) & 255, $c & 255) < self::PAPIER) {
                            $tinte = 1;

                            break;
                        }
                    }
                }

                if ($tinte) {
                    $maske[$y * $bw + $x] = "\1";
                }
            }
        }

        if ($eigen) {
            imagedestroy($arbeit);
        }

        return [$maske, $bw, $bh];
    }

    /**
     * Schließen: erst weiten, dann wieder schrumpfen.
     *
     * Füllt Kerben, die schmaler als der Radius sind (Fenster, Türen), ohne die
     * Fläche insgesamt zu vergrößern. Getrennt in Zeilen und Spalten gerechnet –
     * zwei Durchgänge über eine Linie statt einer über eine Scheibe.
     *
     */
    protected static function schliessen(string &$maske, int $bw, int $bh, int $r): void
    {
        if ($r < 1) {
            return;
        }

        self::linie($maske, $bw, $bh, $r, true);
        self::linie($maske, $bw, $bh, $r, false);
    }

    /**
     * Öffnen: erst schrumpfen, dann wieder weiten.
     *
     * Was schmaler ist als der Radius, kommt nicht zurück. Genau das soll mit
     * Türbögen und Text-Brücken passieren.
     *
     */
    protected static function oeffnen(string &$maske, int $bw, int $bh, int $r): void
    {
        if ($r < 1) {
            return;
        }

        self::linie($maske, $bw, $bh, $r, false);
        self::linie($maske, $bw, $bh, $r, true);
    }

    /**
     * Ein Durchgang weiten (oder schrumpfen) über Zeilen und Spalten.
     *
     * @param  bool  $weiten  true = weiten, false = schrumpfen
     */
    protected static function linie(string &$maske, int $bw, int $bh, int $r, bool $weiten): void
    {
        foreach ([true, false] as $waagerecht) {
            $neu = $maske;
            $ein = $weiten ? "\1" : "\0";

            $aussen = $waagerecht ? $bh : $bw;
            $innen  = $waagerecht ? $bw : $bh;

            for ($a = 0; $a < $aussen; $a++) {
                // Laufende Summe der Tinte im Fenster [i-r, i+r].
                $summe = 0;

                for ($i = 0; $i <= min($r, $innen - 1); $i++) {
                    $summe += $maske[$waagerecht ? $a * $bw + $i : $i * $bw + $a] === "\1" ? 1 : 0;
                }

                for ($i = 0; $i < $innen; $i++) {
                    $breite = min($innen - 1, $i + $r) - max(0, $i - $r) + 1;
                    $treffer = $weiten ? $summe > 0 : $summe === $breite;

                    $neu[$waagerecht ? $a * $bw + $i : $i * $bw + $a] = $treffer ? "\1" : "\0";

                    // Fenster einen Schritt weiterschieben.
                    $raus = $i - $r;
                    $rein = $i + $r + 1;

                    if ($raus >= 0) {
                        $summe -= $maske[$waagerecht ? $a * $bw + $raus : $raus * $bw + $a] === "\1" ? 1 : 0;
                    }

                    if ($rein < $innen) {
                        $summe += $maske[$waagerecht ? $a * $bw + $rein : $rein * $bw + $a] === "\1" ? 1 : 0;
                    }
                }
            }

            $maske = $neu;
        }
    }

    /**
     * Umschließendes Rechteck aller Tinte in Arbeitspixeln.
     *
     * @return array{0: int, 1: int, 2: int, 3: int}|null
     */
    protected static function rahmen(string $maske, int $bw, int $bh): ?array
    {
        $x0 = $bw; $y0 = $bh; $x1 = -1; $y1 = -1;

        for ($y = 0; $y < $bh; $y++) {
            for ($x = 0; $x < $bw; $x++) {
                if ($maske[$y * $bw + $x] === "\1") {
                    $x0 = min($x0, $x); $x1 = max($x1, $x);
                    $y0 = min($y0, $y); $y1 = max($y1, $y);
                }
            }
        }

        return $x1 > $x0 && $y1 > $y0 ? [$x0, $y0, $x1, $y1] : null;
    }

    /**
     * Punkte in Arbeitspixeln als normalisierten Zug ausgeben.
     *
     * @param  array<int, array{0: float, 1: float}>  $punkte
     * @return array<int, array<int, array{0: float, 1: float}>>
     */
    protected static function alsZug(array $punkte, int $bw, int $bh): array
    {
        $zug = [];

        foreach (array_slice($punkte, 0, RoomLayout::MAX_POINTS) as [$x, $y]) {
            $zug[] = [
                round(min(1, max(0, $x / $bw)), 4),
                round(min(1, max(0, $y / $bh)), 4),
            ];
        }

        return [$zug];
    }

    /**
     * Ist der Boden gefüllt (Parkett, Marmor) oder weiß?
     *
     * Gemessen in der mittleren Hälfte des Tinten-Rechtecks. Dort liegt bei
     * einem gefüllten Plan Boden, bei einer Strichzeichnung Luft.
     */
    protected static function bodenGefuellt(string $maske, int $bw, int $bh): bool
    {
        $x0 = $bw; $y0 = $bh; $x1 = -1; $y1 = -1;

        for ($y = 0; $y < $bh; $y++) {
            for ($x = 0; $x < $bw; $x++) {
                if ($maske[$y * $bw + $x] === "\1") {
                    $x0 = min($x0, $x); $x1 = max($x1, $x);
                    $y0 = min($y0, $y); $y1 = max($y1, $y);
                }
            }
        }

        if ($x1 <= $x0 || $y1 <= $y0) {
            return false;
        }

        // Mittlere Hälfte des Rechtecks.
        $mx0 = (int) round($x0 + ($x1 - $x0) * 0.25);
        $mx1 = (int) round($x0 + ($x1 - $x0) * 0.75);
        $my0 = (int) round($y0 + ($y1 - $y0) * 0.25);
        $my1 = (int) round($y0 + ($y1 - $y0) * 0.75);

        $tinte = 0;
        $alle  = 0;

        for ($y = $my0; $y <= $my1; $y++) {
            for ($x = $mx0; $x <= $mx1; $x++) {
                $alle++;

                if ($maske[$y * $bw + $x] === "\1") {
                    $tinte++;
                }
            }
        }

        return $alle > 0 && $tinte / $alle >= self::DICHTE_GEFUELLT;
    }

    /**
     * Gefüllter Boden: Der Raum IST die Tinte.
     *
     * Erst Haarrisse überbrücken, dann den größten Fleck – hier fallen
     * Beschriftungen und Legenden als eigene Flecken weg. Danach Löcher füllen,
     * Kerben schließen, Fäden abschneiden.
     */
    protected static function raumAusTinte(string $maske, int $bw, int $bh): string
    {
        self::schliessen($maske, $bw, $bh, self::VORSCHLIESSEN);

        $fleck = self::groessterFleck($maske, $bw, $bh);

        if ($fleck === null) {
            return $maske;
        }

        $raum = $fleck[0];

        self::loecherFuellen($raum, $bw, $bh);
        self::schliessen($raum, $bw, $bh, self::SCHLIESSEN);

        return $raum;
    }

    /**
     * Kleine Tinteninseln streichen – Beschriftungen, Türbögen, Krümel.
     *
     * Nur im Papier-Weg: Dort gibt es keine Fleckwahl, die sie von selbst
     * aussortiert, und ein Wandblock ist um ein Mehrfaches größer als Schrift.
     */
    protected static function inselnEntfernen(string &$maske, int $bw, int $bh): void
    {
        $grenze  = max(1, (int) round(self::INSEL * $bw * $bh));
        $gesehen = str_repeat("\0", $bw * $bh);

        for ($start = 0, $n = $bw * $bh; $start < $n; $start++) {
            if ($maske[$start] === "\0" || $gesehen[$start] === "\1") {
                continue;
            }

            $stapel = [$start];
            $gesehen[$start] = "\1";
            $insel = [];

            while ($stapel) {
                $i = array_pop($stapel);
                $insel[] = $i;

                $x = $i % $bw;
                $y = intdiv($i, $bw);

                for ($dy = -1; $dy <= 1; $dy++) {
                    for ($dx = -1; $dx <= 1; $dx++) {
                        $nx = $x + $dx;
                        $ny = $y + $dy;

                        if ($nx < 0 || $ny < 0 || $nx >= $bw || $ny >= $bh) {
                            continue;
                        }

                        $j = $ny * $bw + $nx;

                        if ($maske[$j] === "\1" && $gesehen[$j] === "\0") {
                            $gesehen[$j] = "\1";
                            $stapel[] = $j;
                        }
                    }
                }
            }

            if (count($insel) < $grenze) {
                foreach ($insel as $i) {
                    $maske[$i] = "\0";
                }
            }
        }
    }

    /**
     * Der Raum als das, was das Papier von draußen nicht erreicht.
     *
     * Drei Schritte, und der mittlere ist der eigentliche Trick:
     *
     *   1. Vom Bildrand durchs Weiß fluten – das ist "draußen".
     *   2. Dieses Draußen ÖFFNEN: schrumpfen, nur den Teil behalten, der den
     *      Bildrand noch berührt, wieder weiten. Beim Schrumpfen reißen schmale
     *      Ausläufer ab – also genau die Fenster- und Türlücken, durch die das
     *      Papier in den Saal sickert. Was dahinter lag, hängt danach nicht mehr
     *      am Rand und fällt weg. Das Weiten stellt die Grenze wieder her, und
     *      es überdeckt dabei kleine Tinteninseln: Beschriftungen und Türbögen
     *      gehören danach zum Draußen.
     *   3. Raum = alles Übrige.
     */
    protected static function raumAusPapier(string $maske, int $bw, int $bh): string
    {
        self::inselnEntfernen($maske, $bw, $bh);

        $draussen = self::vomRandGeflutet($maske, $bw, $bh, "\0");

        self::linie($draussen, $bw, $bh, self::ABSCHLUSS, false);
        $draussen = self::vomRandGeflutet($draussen, $bw, $bh, "\1");
        self::linie($draussen, $bw, $bh, self::ABSCHLUSS, true);

        $raum = str_repeat("\0", $bw * $bh);

        for ($i = 0, $n = $bw * $bh; $i < $n; $i++) {
            if ($draussen[$i] === "\0") {
                $raum[$i] = "\1";
            }
        }

        return $raum;
    }

    /**
     * Alles, was vom Bildrand aus über Pixel mit dem Wert $suche erreichbar ist.
     *
     * Vierer-Nachbarschaft: Diagonal darf das Papier nicht durch eine Ecke
     * schlüpfen, sonst käme es an jeder über Eck gesetzten Wand vorbei.
     */
    protected static function vomRandGeflutet(string $maske, int $bw, int $bh, string $suche): string
    {
        $treffer = str_repeat("\0", $bw * $bh);
        $stapel  = [];

        $setzen = function (int $i) use (&$treffer, &$stapel, $maske, $suche): void {
            if ($maske[$i] === $suche && $treffer[$i] === "\0") {
                $treffer[$i] = "\1";
                $stapel[] = $i;
            }
        };

        for ($x = 0; $x < $bw; $x++) {
            $setzen($x);
            $setzen(($bh - 1) * $bw + $x);
        }

        for ($y = 0; $y < $bh; $y++) {
            $setzen($y * $bw);
            $setzen($y * $bw + $bw - 1);
        }

        while ($stapel) {
            $i = array_pop($stapel);
            $x = $i % $bw;
            $y = intdiv($i, $bw);

            foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                $nx = $x + $dx;
                $ny = $y + $dy;

                if ($nx < 0 || $ny < 0 || $nx >= $bw || $ny >= $bh) {
                    continue;
                }

                $setzen($ny * $bw + $nx);
            }
        }

        return $treffer;
    }

    /**
     * Eingeschlossenes Papier zu Tinte machen.
     *
     * Vom Bildrand aus wird durchs Papier geflutet; was dabei nicht erreicht
     * wird, liegt innen und wird gefüllt. Damit ist der Raum eine Scheibe und
     * kein Ring – erst dadurch übersteht er das Öffnen.
     *
     * Nebenwirkung, die gewollt ist: Möbel, Text und Löcher IM Raum
     * verschwinden aus der Maske. Sie interessieren für den Umriss nicht.
     *
     */
    protected static function loecherFuellen(string &$maske, int $bw, int $bh): void
    {
        $draussen = str_repeat("\0", $bw * $bh);
        $stapel   = [];

        for ($x = 0; $x < $bw; $x++) {
            foreach ([0, $bh - 1] as $y) {
                $i = $y * $bw + $x;

                if ($maske[$i] === "\0" && $draussen[$i] === "\0") {
                    $draussen[$i] = "\1";
                    $stapel[] = $i;
                }
            }
        }

        for ($y = 0; $y < $bh; $y++) {
            foreach ([0, $bw - 1] as $x) {
                $i = $y * $bw + $x;

                if ($maske[$i] === "\0" && $draussen[$i] === "\0") {
                    $draussen[$i] = "\1";
                    $stapel[] = $i;
                }
            }
        }

        while ($stapel) {
            $i = array_pop($stapel);
            $x = $i % $bw;
            $y = intdiv($i, $bw);

            foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                $nx = $x + $dx;
                $ny = $y + $dy;

                if ($nx < 0 || $ny < 0 || $nx >= $bw || $ny >= $bh) {
                    continue;
                }

                $j = $ny * $bw + $nx;

                if ($maske[$j] === "\0" && $draussen[$j] === "\0") {
                    $draussen[$j] = "\1";
                    $stapel[] = $j;
                }
            }
        }

        for ($i = 0; $i < $bw * $bh; $i++) {
            if ($maske[$i] === "\0" && $draussen[$i] === "\0") {
                $maske[$i] = "\1";
            }
        }
    }

    /**
     * Größter zusammenhängender Fleck (8er-Nachbarschaft).
     *
     * @return array{0: string, 1: array{0: int, 1: int}}|null
     */
    protected static function groessterFleck(string $maske, int $bw, int $bh): ?array
    {
        $leer    = str_repeat("\0", $bw * $bh);
        $gesehen = $leer;
        $beste   = null;
        $bestN   = 0;
        $bestAb  = 0;

        for ($start = 0; $start < $bw * $bh; $start++) {
            if ($maske[$start] === "\0" || $gesehen[$start] === "\1") {
                continue;
            }

            // Nur EINE Fleckmaske gleichzeitig aufheben, nicht die Punktlisten
            // aller Flecken – bei 300x225 sind das je Fleck 67 Kilobyte statt
            // mehrerer Megabyte.
            $treffer = $leer;
            $anzahl  = 0;
            $stapel  = [$start];
            $gesehen[$start] = "\1";

            while ($stapel) {
                $i = array_pop($stapel);
                $treffer[$i] = "\1";
                $anzahl++;

                $x = $i % $bw;
                $y = intdiv($i, $bw);

                for ($dy = -1; $dy <= 1; $dy++) {
                    for ($dx = -1; $dx <= 1; $dx++) {
                        $nx = $x + $dx;
                        $ny = $y + $dy;

                        if ($nx < 0 || $ny < 0 || $nx >= $bw || $ny >= $bh) {
                            continue;
                        }

                        $j = $ny * $bw + $nx;

                        if ($maske[$j] === "\1" && $gesehen[$j] === "\0") {
                            $gesehen[$j] = "\1";
                            $stapel[] = $j;
                        }
                    }
                }
            }

            if ($anzahl > $bestN) {
                $bestN  = $anzahl;
                $beste  = $treffer;
                $bestAb = $start;   // zeilenweise gesucht: oberster, linkester Punkt
            }
        }

        if ($beste === null) {
            return null;
        }

        return [$beste, [$bestAb % $bw, intdiv($bestAb, $bw)]];
    }

    /**
     * Außenrand des Flecks verfolgen (Moore-Nachbarschaft, im Uhrzeigersinn).
     *
     * @param  array{0: int, 1: int}  $start
     * @return array<int, array{0: int, 1: int}>
     */
    protected static function randVerfolgen(string $flaeche, int $bw, int $bh, array $start): array
    {
        // Reihenfolge im Uhrzeigersinn, beginnend links oben.
        $ring = [[-1, -1], [0, -1], [1, -1], [1, 0], [1, 1], [0, 1], [-1, 1], [-1, 0]];

        $tinte = function (int $x, int $y) use ($flaeche, $bw, $bh): bool {
            return $x >= 0 && $y >= 0 && $x < $bw && $y < $bh && $flaeche[$y * $bw + $x] === "\1";
        };

        $rand = [$start];
        $x = $start[0];
        $y = $start[1];

        // Betreten von links: gesucht wurde zeilenweise, links liegt Papier.
        // Der Wert ist so gewählt, dass die Suche unten bei "links oben" beginnt.
        $richtung = 3;

        // Abbruch über gesehene (Ort + Richtung)-Zustände statt über "wieder am
        // Anfang". Der Startpunkt wird auf dünnen Fortsätzen schon nach drei
        // Schritten wieder berührt; der Umriss war dann ein Zipfel statt eines
        // Raums. Ein Zustand kann sich nur wiederholen, wenn der Rundgang
        // geschlossen ist – das ist genau die Bedingung, die gemeint war.
        $gesehen = str_repeat("\0", $bw * $bh * 8);

        while (true) {
            $zustand = (($y * $bw + $x) * 8) + $richtung;

            if ($gesehen[$zustand] === "\1") {
                break;
            }

            $gesehen[$zustand] = "\1";

            $gefunden = false;

            // Weiter beim Nachbarn NACH dem, aus dem wir kamen – im
            // Uhrzeigersinn. Der Vorgänger selbst liegt bei richtung+4 und ist
            // Tinte; würde die Suche dort anfangen, liefe sie sofort zurück und
            // pendelte zwischen zwei Punkten. Zwei Schritte weiter (+6) wäre
            // umgekehrt einer zu viel: Dann bleibt ein gültiger Nachbar
            // unbesehen, der Rundgang schneidet die Ecke und schließt sich nach
            // vier Punkten um einen Zipfel. Genau das war der Fehler.
            for ($k = 0; $k < 8; $k++) {
                $d  = ($richtung + 5 + $k) % 8;
                $nx = $x + $ring[$d][0];
                $ny = $y + $ring[$d][1];

                if ($tinte($nx, $ny)) {
                    $x = $nx;
                    $y = $ny;
                    $richtung = $d;
                    $gefunden = true;

                    break;
                }
            }

            if (! $gefunden) {
                break;   // einzelner Punkt
            }

            if ($rand[count($rand) - 1] !== [$x, $y]) {
                $rand[] = [$x, $y];
            }
        }

        // Der zurückgelaufene Startpunkt gehört nicht zweimal in die Liste.
        while (count($rand) > 1 && $rand[count($rand) - 1] === $start) {
            array_pop($rand);
        }

        return $rand;
    }

    /**
     * Douglas-Peucker: Punkte weglassen, solange die Linie nah genug bleibt.
     *
     * @param  array<int, array{0: int, 1: int}>  $punkte
     * @return array<int, array{0: float, 1: float}>
     */
    protected static function vereinfachen(array $punkte, float $eps): array
    {
        $n = count($punkte);

        if ($n < 3) {
            return $punkte;
        }

        $behalten = array_fill(0, $n, false);
        $behalten[0] = true;
        $behalten[$n - 1] = true;

        $stapel = [[0, $n - 1]];

        while ($stapel) {
            [$a, $b] = array_pop($stapel);

            if ($b <= $a + 1) {
                continue;
            }

            $ax = $punkte[$a][0];
            $ay = $punkte[$a][1];
            $bx = $punkte[$b][0];
            $by = $punkte[$b][1];

            $laenge = hypot($bx - $ax, $by - $ay);
            $weit   = -1.0;
            $wo     = $a;

            for ($i = $a + 1; $i < $b; $i++) {
                $px = $punkte[$i][0];
                $py = $punkte[$i][1];

                $d = $laenge > 0
                    ? abs(($bx - $ax) * ($ay - $py) - ($ax - $px) * ($by - $ay)) / $laenge
                    : hypot($px - $ax, $py - $ay);

                if ($d > $weit) {
                    $weit = $d;
                    $wo   = $i;
                }
            }

            if ($weit > $eps) {
                $behalten[$wo] = true;
                $stapel[] = [$a, $wo];
                $stapel[] = [$wo, $b];
            }
        }

        $out = [];

        foreach ($punkte as $i => $pt) {
            if ($behalten[$i]) {
                $out[] = [(float) $pt[0], (float) $pt[1]];
            }
        }

        return $out;
    }

    /**
     * Fast waagerechte und fast senkrechte Kanten gerade ziehen.
     *
     * Räume sind gebaut, nicht gemalt: Was im Bild um ein Pixel kippt, ist in
     * Wirklichkeit gerade. Ohne diesen Schritt sähe der Vorschlag schief aus,
     * und schief lässt sich schlechter weiterbearbeiten als gerade.
     *
     * @param  array<int, array{0: float, 1: float}>  $punkte
     * @return array<int, array{0: float, 1: float}>
     */
    protected static function achsenEinrasten(array $punkte): array
    {
        $n = count($punkte);

        for ($i = 0; $i < $n; $i++) {
            $j = ($i + 1) % $n;

            $dx = $punkte[$j][0] - $punkte[$i][0];
            $dy = $punkte[$j][1] - $punkte[$i][1];

            $laenge = hypot($dx, $dy);

            if ($laenge < 1) {
                continue;
            }

            $spiel = max(1.0, $laenge * self::ACHSE_ANTEIL);

            if (abs($dy) <= $spiel && abs($dy) < abs($dx)) {
                $mitte = ($punkte[$i][1] + $punkte[$j][1]) / 2;
                $punkte[$i][1] = $mitte;
                $punkte[$j][1] = $mitte;
            } elseif (abs($dx) <= $spiel && abs($dx) < abs($dy)) {
                $mitte = ($punkte[$i][0] + $punkte[$j][0]) / 2;
                $punkte[$i][0] = $mitte;
                $punkte[$j][0] = $mitte;
            }
        }

        return $punkte;
    }

    /**
     * Kanten, die fast auf einer Linie liegen, auf dieselbe Linie legen.
     *
     * Die Höhen aller waagerechten Kanten werden zu Gruppen zusammengefasst,
     * die Breiten aller senkrechten ebenso. Innerhalb einer Gruppe gewinnt die
     * LÄNGE: Die durchgehende Wand zieht die kurze Fensterkerbe zu sich, nicht
     * umgekehrt.
     *
     * @param  array<int, array{0: float, 1: float}>  $punkte
     * @return array<int, array{0: float, 1: float}>
     */
    protected static function kantenBuendeln(array $punkte, int $bw, int $bh): array
    {
        $n = count($punkte);

        if ($n < 4) {
            return $punkte;
        }

        // Kante i führt von Punkt i zu Punkt i+1 (im Ring).
        $kanten = [];

        for ($i = 0; $i < $n; $i++) {
            $a = $punkte[$i];
            $b = $punkte[($i + 1) % $n];

            $dx = $b[0] - $a[0];
            $dy = $b[1] - $a[1];

            $waagerecht = abs($dx) >= abs($dy);

            $kanten[$i] = [
                'waagerecht' => $waagerecht,
                'hoehe'      => $waagerecht ? ($a[1] + $b[1]) / 2 : ($a[0] + $b[0]) / 2,
                'laenge'     => hypot($dx, $dy),
            ];
        }

        foreach ([true, false] as $waagerecht) {
            $spiel = self::BUENDEL_ANTEIL * ($waagerecht ? $bh : $bw);

            $auswahl = array_keys(array_filter($kanten, fn ($k) => $k['waagerecht'] === $waagerecht));

            if (count($auswahl) < 2) {
                continue;
            }

            usort($auswahl, fn ($a, $b) => $kanten[$a]['hoehe'] <=> $kanten[$b]['hoehe']);

            $gruppe  = [$auswahl[0]];
            $gruppen = [];

            for ($i = 1; $i < count($auswahl); $i++) {
                $vorher = $kanten[$gruppe[count($gruppe) - 1]]['hoehe'];

                if ($kanten[$auswahl[$i]]['hoehe'] - $vorher <= $spiel) {
                    $gruppe[] = $auswahl[$i];
                } else {
                    $gruppen[] = $gruppe;
                    $gruppe = [$auswahl[$i]];
                }
            }

            $gruppen[] = $gruppe;

            foreach ($gruppen as $g) {
                $summe = 0.0;
                $wert  = 0.0;

                foreach ($g as $i) {
                    $summe += $kanten[$i]['laenge'];
                    $wert  += $kanten[$i]['laenge'] * $kanten[$i]['hoehe'];
                }

                if ($summe <= 0) {
                    continue;
                }

                $linie = $wert / $summe;

                foreach ($g as $i) {
                    $kanten[$i]['hoehe'] = $linie;
                }
            }
        }

        // Punkte neu setzen: jeder liegt im Schnitt seiner beiden Kanten.
        $neu = [];

        for ($i = 0; $i < $n; $i++) {
            $rein = $kanten[($i - 1 + $n) % $n];
            $raus = $kanten[$i];

            $x = $punkte[$i][0];
            $y = $punkte[$i][1];

            if ($rein['waagerecht'] !== $raus['waagerecht']) {
                $x = $rein['waagerecht'] ? $raus['hoehe'] : $rein['hoehe'];
                $y = $rein['waagerecht'] ? $rein['hoehe'] : $raus['hoehe'];
            } elseif ($rein['waagerecht']) {
                $y = ($rein['hoehe'] + $raus['hoehe']) / 2;
            } else {
                $x = ($rein['hoehe'] + $raus['hoehe']) / 2;
            }

            $neu[] = [$x, $y];
        }

        return $neu;
    }

    /**
     * Zu kurze Kanten und Punkte auf einer Geraden entfernen.
     *
     * In Runden, bis sich nichts mehr ändert: Fällt ein Punkt als "liegt auf der
     * Geraden" weg, können die beiden Nachbarn plötzlich aufeinanderliegen – so
     * blieb eine Kante der Länge Null zurück, weil nach dem Geraden-Durchgang
     * niemand mehr nach zu kurzen Kanten sah.
     *
     * @param  array<int, array{0: float, 1: float}>  $punkte
     * @return array<int, array{0: float, 1: float}>
     */
    protected static function aufraeumen(array $punkte): array
    {
        for ($runde = 0; $runde < 4; $runde++) {
            $vorher = count($punkte);

            $punkte = self::kurzeKantenWeg($punkte);
            $punkte = self::geradeWeg($punkte);

            if (count($punkte) === $vorher) {
                break;
            }
        }

        return $punkte;
    }

    /**
     * Punkte verwerfen, die zu nah am vorigen liegen.
     *
     * @param  array<int, array{0: float, 1: float}>  $punkte
     * @return array<int, array{0: float, 1: float}>
     */
    protected static function kurzeKantenWeg(array $punkte): array
    {
        $out = [];

        foreach ($punkte as $pt) {
            if ($out === [] || hypot($pt[0] - end($out)[0], $pt[1] - end($out)[1]) >= self::MIN_KANTE) {
                $out[] = $pt;
            }
        }

        // Auch der Ringschluss ist eine Kante.
        if (count($out) > 2 && hypot($out[0][0] - end($out)[0], $out[0][1] - end($out)[1]) < self::MIN_KANTE) {
            array_pop($out);
        }

        return $out;
    }

    /**
     * Punkte verwerfen, die auf der Verbindung ihrer Nachbarn liegen.
     *
     * @param  array<int, array{0: float, 1: float}>  $punkte
     * @return array<int, array{0: float, 1: float}>
     */
    protected static function geradeWeg(array $punkte): array
    {
        $n = count($punkte);

        if ($n < 4) {
            return $punkte;
        }

        $behalten = [];

        for ($i = 0; $i < $n; $i++) {
            $a = $punkte[($i - 1 + $n) % $n];
            $b = $punkte[$i];
            $c = $punkte[($i + 1) % $n];

            $laenge = hypot($c[0] - $a[0], $c[1] - $a[1]);

            $d = $laenge > 0
                ? abs(($c[0] - $a[0]) * ($a[1] - $b[1]) - ($a[0] - $b[0]) * ($c[1] - $a[1])) / $laenge
                : 0.0;

            if ($d >= 1.0) {
                $behalten[] = $b;
            }
        }

        return count($behalten) >= 3 ? $behalten : $punkte;
    }
}
