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
 * NICHT nach Linien gesucht, sondern nach der FLÄCHE. Das ist die eigentliche
 * Entscheidung hinter dieser Klasse, und sie kommt von den echten Plänen: Deren
 * Böden sind mit Fischgrät-Parkett oder Marmor gefüllt. Eine Linienerkennung
 * würde in diesem Muster ertrinken – Hunderte kurzer Striche, alle so lang wie
 * eine Wand ist dick. Was dagegen in jedem geprüften Plan gilt: Draußen ist
 * weißes Papier, drinnen ist irgendetwas. Der Raum ist also der größte
 * zusammenhängende Fleck, der nicht Papier ist.
 *
 * Dadurch fällt Beiwerk von selbst weg: Beschriftungen wie "Eingang" liegen als
 * eigene kleine Flecken daneben, Möbel und Text INNERHALB des Raums stören
 * nicht, weil nur der äußere Rand des Flecks verfolgt wird.
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
     * Radius zum Schließen von Lücken, in Arbeitspixeln.
     *
     * Fenster und Türen sind im Plan weiße Kerben in der Wand. Ohne Schließen
     * würde der Umriss in jede davon hineinlaufen und ausgefranst aussehen.
     * Türöffnungen entstehen später ohnehin über die Beschriftungen.
     */
    public const SCHLIESSEN = 6;

    /**
     * Kleines Schließen VOR der Fleckwahl, in Arbeitspixeln.
     *
     * Nur so groß, dass eine Strichzeichnung mit Haarrissen zusammenhängt –
     * und klein genug, dass keine Brücke zu einer nahen Beschriftung entsteht.
     * Bei 2 klebten im Offenbachsaal die drei "Eingang"-Schriften über die
     * Türflügel-Bögen am Raum und zogen den Umriss in drei Zipfeln nach unten.
     */
    public const VORSCHLIESSEN = 1;

    /**
     * Radius zum Öffnen, in Arbeitspixeln.
     *
     * Entfernt dünne Anhängsel: Türflügel-Bögen, die außen an der Wand kleben,
     * und die Brücke, die das Schließen zu einer nahen Beschriftung baut. Ohne
     * diesen Schritt lief der Umriss in der Gartenhalle bis in das Wort
     * "Eingang" hinein.
     *
     * Muss kleiner sein als die halbe Wandstärke, sonst frisst er die Wand.
     */
    public const OEFFNEN = 3;

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
        //   1. Ein kleines Schließen überbrückt Haarrisse, damit eine reine
        //      Strichzeichnung überhaupt einen Fleck bildet – aber klein genug,
        //      um nicht bis zur nächsten Beschriftung zu reichen.
        //   2. Größten Fleck wählen. HIER fallen "Eingang", "Bar" und Legenden
        //      weg, weil sie eigene Flecken sind. Käme dieser Schritt später,
        //      hätte das große Schließen sie längst an den Raum geklebt – genau
        //      das lief im Offenbachsaal in drei Zipfeln nach unten.
        //   3. Löcher füllen. Nötig, weil eine reine Strichzeichnung nur aus dem
        //      Wandring besteht: Ohne Füllung ist der Raum ein dünner Rahmen,
        //      und Schritt 5 würde ihn restlos abtragen.
        //   4. Großes Schließen: Fenster- und Türkerben im Umriss zumachen.
        //   5. Öffnen: dünne Anhängsel wie Türflügel-Bögen abschneiden.
        //   6. Nochmal den größten Fleck – das Öffnen kann etwas abgetrennt haben.
        self::schliessen($maske, $bw, $bh, self::VORSCHLIESSEN);

        $fleck = self::groessterFleck($maske, $bw, $bh);

        if ($fleck === null) {
            return [];
        }

        $maske = $fleck[0];

        self::loecherFuellen($maske, $bw, $bh);
        self::schliessen($maske, $bw, $bh, self::SCHLIESSEN);
        self::oeffnen($maske, $bw, $bh, self::OEFFNEN);

        $fleck = self::groessterFleck($maske, $bw, $bh);

        if ($fleck === null) {
            return [];
        }

        [$flaeche, $start] = $fleck;

        if (substr_count($flaeche, "\1") < self::MIN_FLAECHE * $bw * $bh) {
            return [];
        }

        $rand = self::randVerfolgen($flaeche, $bw, $bh, $start);

        if (count($rand) < 8) {
            return [];
        }

        $punkte = self::vereinfachen($rand, self::GENAUIGKEIT);
        $punkte = self::achsenEinrasten($punkte);
        $punkte = self::kantenBuendeln($punkte, $bw, $bh);
        $punkte = self::aufraeumen($punkte);

        if (count($punkte) < 3) {
            return [];
        }

        // Ring schließen: derselbe Punkt am Ende wie am Anfang, wie beim
        // Zeichnen von Hand.
        $punkte[] = $punkte[0];

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
     * @param  array<int, array{0: float, 1: float}>  $punkte
     * @return array<int, array{0: float, 1: float}>
     */
    protected static function aufraeumen(array $punkte): array
    {
        // Zu kurze Kanten: den späteren Punkt fallen lassen.
        $out = [];

        foreach ($punkte as $pt) {
            if ($out === [] || hypot($pt[0] - end($out)[0], $pt[1] - end($out)[1]) >= self::MIN_KANTE) {
                $out[] = $pt;
            }
        }

        if (count($out) > 2 && hypot($out[0][0] - end($out)[0], $out[0][1] - end($out)[1]) < self::MIN_KANTE) {
            array_pop($out);
        }

        // Punkte, die auf der Verbindung ihrer Nachbarn liegen, sagen nichts.
        $n = count($out);

        if ($n < 4) {
            return $out;
        }

        $behalten = [];

        for ($i = 0; $i < $n; $i++) {
            $a = $out[($i - 1 + $n) % $n];
            $b = $out[$i];
            $c = $out[($i + 1) % $n];

            $laenge = hypot($c[0] - $a[0], $c[1] - $a[1]);

            $d = $laenge > 0
                ? abs(($c[0] - $a[0]) * ($a[1] - $b[1]) - ($a[0] - $b[0]) * ($c[1] - $a[1])) / $laenge
                : 0.0;

            if ($d >= 1.0) {
                $behalten[] = $b;
            }
        }

        return count($behalten) >= 3 ? $behalten : $out;
    }
}
