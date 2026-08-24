<?php

namespace Platform\Reservation\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\CheckoutSetting;

/**
 * Buchungsstapel im DATEV-Format (EXTF) aus Buchungen erzeugen.
 *
 * Die Datei besteht aus drei Teilen: einer Kopfzeile mit Berater, Mandant,
 * Wirtschaftsjahr und Zeitraum, einer Zeile mit den Spaltenüberschriften und
 * den Buchungssätzen.
 *
 * WAS HIER NICHT ENTSCHIEDEN WIRD: Kontonummern. Sie kommen aus den
 * Einstellungen, weil sie je Mandant verschieden sind und vom Kontenrahmen
 * abhängen (SKR03 bucht Erlöse auf 8400/8300, SKR04 auf 4400/4300). Im Code
 * wären sie beim ersten weiteren Kunden falsch.
 *
 * Gebucht wird "Geldkonto an Erlöskonto": Der Betrag steht im Soll auf dem
 * Konto, auf dem das Geld eingeht, und im Haben auf dem Erlöskonto. Der
 * Steuersatz steckt bei den üblichen Automatikkonten im Erlöskonto selbst,
 * deshalb bleibt der BU-Schlüssel leer.
 *
 * Brutto, nicht netto: unit_price ist der Bruttopreis, und DATEV erwartet im
 * Buchungsstapel ebenfalls den Bruttobetrag – die Steuer zieht es selbst aus
 * dem Konto. Genau deshalb darf der Steuersatz nicht verlorengehen, und deshalb
 * wird je Steuersatz eine eigene Zeile geschrieben.
 *
 * Nur BEZAHLTE Umsätze: bestätigte und abgeschlossene Buchungen, dieselbe
 * Abgrenzung wie in den Finanzen. Ausstehende oder stornierte Bestellungen
 * gehören nicht in die Buchhaltung.
 */
class DatevBuchungsstapel
{
    /** Kennung und Versionen laut DATEV-Formatbeschreibung. */
    public const KENNZEICHEN     = 'EXTF';
    public const VERSION         = 700;
    public const KATEGORIE       = 21;              // Buchungsstapel
    public const FORMATNAME      = 'Buchungsstapel';
    public const FORMATVERSION   = 13;

    /** Herkunft: zwei Zeichen, beim Import von DATEV überschrieben. */
    public const HERKUNFT = 'PP';

    /** Status, die Umsatz sind – wie in den Finanzen. */
    public const UMSATZ_STATUS = [Booking::STATUS_CONFIRMED, Booking::STATUS_COMPLETED];

    /**
     * Die komplette Datei als Zeichenkette.
     *
     * @param  Collection<int, Booking>  $bookings
     */
    public static function bauen(
        Collection $bookings,
        CheckoutSetting $einst,
        CarbonImmutable $von,
        CarbonImmutable $bis,
    ): string {
        $zeilen = [
            self::kopfzeile($einst, $von, $bis),
            self::ueberschriften(),
        ];

        foreach (self::saetze($bookings, $einst) as $satz) {
            $zeilen[] = self::zeile($satz);
        }

        // CRLF und Windows-1252: beides erwartet DATEV so. Umlaute, die dort
        // fehlen, würden den Import sonst zerlegen.
        $text = implode("\r\n", $zeilen) . "\r\n";

        return mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
    }

    /**
     * Buchungssätze aus den Buchungen.
     *
     * Je Steuersatz eine Zeile: Ein Bundle zerfällt beim Bestellen in
     * Bestandteile mit eigenen Sätzen, und 7 % und 19 % gehören auf
     * verschiedene Erlöskonten.
     *
     * @param  Collection<int, Booking>  $bookings
     * @return array<int, array<string, string>>
     */
    public static function saetze(Collection $bookings, CheckoutSetting $einst): array
    {
        $relevant = $bookings->filter(fn (Booking $b) => in_array($b->status, self::UMSATZ_STATUS, true));

        $tagessummen = $einst->datev_modus === CheckoutSetting::DATEV_TAGESSUMME;

        $roh = [];

        foreach ($relevant as $booking) {
            foreach ($booking->items as $item) {
                $satz    = (float) $item->tax_rate;
                $betrag  = (float) $item->unit_price * (int) $item->quantity;
                $datum   = $booking->date;

                $schluessel = $tagessummen
                    ? $datum->format('Y-m-d') . '|' . $satz
                    : $booking->id . '|' . $satz;

                if (! isset($roh[$schluessel])) {
                    $roh[$schluessel] = [
                        'datum'   => $datum,
                        'satz'    => $satz,
                        'betrag'  => 0.0,
                        'beleg'   => $tagessummen ? $datum->format('dmY') : (string) $booking->id,
                        'text'    => $tagessummen
                            ? trim('PausePlus ' . ($booking->event?->name ?? ''))
                            : trim(($booking->event?->name ? $booking->event->name . ' ' : '') . $booking->guest_name),
                    ];
                }

                $roh[$schluessel]['betrag'] += $betrag;
            }
        }

        // Nach Datum und Steuersatz sortieren – so liest es sich in der Kanzlei.
        uasort($roh, fn ($a, $b) => [$a['datum']->timestamp, $a['satz']] <=> [$b['datum']->timestamp, $b['satz']]);

        $saetze = [];

        foreach ($roh as $eintrag) {
            $betrag = round($eintrag['betrag'], 2);

            // Null-Zeilen haben in einem Stapel nichts verloren.
            if (abs($betrag) < 0.005) {
                continue;
            }

            $saetze[] = [
                'umsatz'      => number_format(abs($betrag), 2, ',', ''),
                'soll_haben'  => $betrag >= 0 ? 'S' : 'H',
                'konto'       => (string) $einst->datev_geldkonto,
                'gegenkonto'  => self::erloeskonto($einst, $eintrag['satz']),
                'belegdatum'  => $eintrag['datum']->format('dm'),
                'belegfeld1'  => mb_substr($eintrag['beleg'], 0, 36),
                'buchungstext'=> mb_substr(self::sauber($eintrag['text']), 0, 60),
            ];
        }

        return $saetze;
    }

    /**
     * Erlöskonto zum Steuersatz.
     *
     * Alles unter 10 % gilt als ermäßigt. Absichtlich nicht auf exakt 7,00
     * geprüft: Der ermäßigte Satz war schon einmal ein anderer, und ein Export,
     * der bei einer Gesetzesänderung stumm das falsche Konto nimmt, ist
     * schlimmer als einer, der grob richtig liegt.
     */
    protected static function erloeskonto(CheckoutSetting $einst, float $satz): string
    {
        return (string) ($satz < 10 ? $einst->datev_erloes_7 : $einst->datev_erloes_19);
    }

    /**
     * Kopfzeile (31 Felder).
     */
    protected static function kopfzeile(CheckoutSetting $einst, CarbonImmutable $von, CarbonImmutable $bis): string
    {
        $wj = $einst->datevWirtschaftsjahrBeginn($von);

        $felder = [
            self::t(self::KENNZEICHEN),
            self::VERSION,
            self::KATEGORIE,
            self::t(self::FORMATNAME),
            self::FORMATVERSION,
            CarbonImmutable::now()->format('YmdHis') . '000',
            '',                                   // importiert (leer)
            self::t(self::HERKUNFT),
            self::t('PausePlus'),                 // exportiert von
            '',                                   // importiert von
            (int) $einst->datev_berater,
            (int) $einst->datev_mandant,
            $wj->format('Ymd'),
            (int) ($einst->datev_sachkontenlaenge ?: 4),
            $von->format('Ymd'),
            $bis->format('Ymd'),
            self::t('PausePlus ' . $von->format('m/Y')),
            '',                                   // Diktatkürzel
            1,                                    // Buchungstyp: Finanzbuchführung
            0,                                    // Rechnungslegungszweck
            0,                                    // Festschreibung: nein, die Kanzlei entscheidet
            self::t('EUR'),
            '', '', '', '', '', '', '', '', '',   // reserviert
        ];

        return implode(';', $felder);
    }

    /**
     * Spaltenüberschriften laut Formatbeschreibung.
     *
     * Nur so viele, wie hier tatsächlich gefüllt werden – DATEV liest die Datei
     * über die Reihenfolge der Felder, nicht über die Namen, und weitere leere
     * Spalten bringen nichts als Länge.
     */
    protected static function ueberschriften(): string
    {
        return implode(';', array_map(fn ($n) => self::t($n), [
            'Umsatz (ohne Soll/Haben-Kz)',
            'Soll/Haben-Kennzeichen',
            'WKZ Umsatz',
            'Kurs',
            'Basis-Umsatz',
            'WKZ Basis-Umsatz',
            'Konto',
            'Gegenkonto (ohne BU-Schlüssel)',
            'BU-Schlüssel',
            'Belegdatum',
            'Belegfeld 1',
            'Belegfeld 2',
            'Skonto',
            'Buchungstext',
        ]));
    }

    /** Ein Buchungssatz als Zeile. */
    protected static function zeile(array $satz): string
    {
        return implode(';', [
            self::t($satz['umsatz']),
            self::t($satz['soll_haben']),
            self::t('EUR'),
            '',                       // Kurs
            '',                       // Basis-Umsatz
            '',                       // WKZ Basis-Umsatz
            $satz['konto'],
            $satz['gegenkonto'],
            '',                       // BU-Schlüssel: steckt im Automatikkonto
            $satz['belegdatum'],
            self::t($satz['belegfeld1']),
            '',                       // Belegfeld 2
            '',                       // Skonto
            self::t($satz['buchungstext']),
        ]);
    }

    /** Textfeld in Anführungszeichen, innere Zeichen entschärft. */
    protected static function t(string $wert): string
    {
        return '"' . str_replace('"', "'", $wert) . '"';
    }

    /** Semikolon und Zeilenumbruch haben in einem Feld nichts zu suchen. */
    protected static function sauber(string $wert): string
    {
        return trim(preg_replace('/[;\r\n]+/', ' ', $wert) ?? '');
    }
}
