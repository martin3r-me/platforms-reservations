<?php

namespace Platform\Reservation\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\CheckoutSetting;
use Platform\Reservation\Support\DatevBuchungsstapel;
use Platform\Reservation\Tests\Concerns\ErzeugtTermine;
use Platform\Reservation\Tests\TestCase;

/**
 * Der Buchungsstapel wird in einer Kanzlei eingelesen, nicht bei uns.
 *
 * DATEV liest die Datei ueber die REIHENFOLGE der Felder. Eine Spalte zu
 * wenig, und die Kostenstelle landet in "Beleginfo - Inhalt 8" - die Datei
 * importiert sauber, die Buchungen stehen auf keiner Kostenstelle, und
 * auffallen wird es im Zweifel niemandem. Genau deshalb steht die Position
 * hier als Zahl und nicht nur als Kommentar.
 */
class DatevStapelTest extends TestCase
{
    use ErzeugtTermine;

    private function einstellungen(array $abweichend = []): CheckoutSetting
    {
        // updateOrCreate statt create: Pro Team gibt es genau einen Satz, und
        // ein Test ruft das hier auch mal zweimal.
        return CheckoutSetting::updateOrCreate(['team_id' => $this->teamId], array_merge([
            'datev_berater'          => '1234567',
            'datev_mandant'          => '12345',
            'datev_sachkontenlaenge' => 6,
            'datev_wj_beginn'        => '01-01',
            'datev_erloes_7'         => '830000',
            'datev_erloes_19'        => '840000',
            'datev_geldkonto'        => '120000',
            'datev_kostenstelle'     => '4711',
            'datev_modus'            => CheckoutSetting::DATEV_EINZEL,
        ], $abweichend));
    }

    /** Eine bezahlte Buchung mit einer Position. */
    private function eineBuchung(): Collection
    {
        $termin = $this->termin(1);
        $plan   = $this->raum('Saal', [4]);
        $this->haengeRaumAn($termin, $plan);

        $buchung = $this->buchung(
            $this->pause($termin, 1),
            $this->tisch($plan),
            2,
            null,
            Booking::STATUS_CONFIRMED,
        );

        $this->position($buchung, $this->artikel(10.0), 2);

        return Booking::with(['items', 'event'])->get();
    }

    public function test_die_kostenstelle_steht_auf_feld_37(): void
    {
        $saetze = DatevBuchungsstapel::saetze($this->eineBuchung(), $this->einstellungen());

        $this->assertCount(1, $saetze);
        $this->assertSame('4711', $saetze[0]['kost1']);

        $datei  = DatevBuchungsstapel::bauen(
            $this->eineBuchung(),
            $this->einstellungen(),
            CarbonImmutable::parse('2026-10-01'),
            CarbonImmutable::parse('2026-10-31'),
        );

        $zeilen = explode("\r\n", mb_convert_encoding($datei, 'UTF-8', 'Windows-1252'));

        // Zeile 1 Kopf, Zeile 2 Spaltennamen, ab Zeile 3 die Buchungssaetze.
        $spalten = explode(';', $zeilen[1]);
        $satz    = explode(';', $zeilen[2]);

        $this->assertSame('"KOST1 - Kostenstelle"', $spalten[DatevBuchungsstapel::FELD_KOST1 - 1]);
        $this->assertSame('"4711"', $satz[DatevBuchungsstapel::FELD_KOST1 - 1]);
    }

    public function test_satz_und_spalten_haben_gleich_viele_felder(): void
    {
        $datei = DatevBuchungsstapel::bauen(
            $this->eineBuchung(),
            $this->einstellungen(),
            CarbonImmutable::parse('2026-10-01'),
            CarbonImmutable::parse('2026-10-31'),
        );

        $zeilen = explode("\r\n", mb_convert_encoding($datei, 'UTF-8', 'Windows-1252'));

        $this->assertCount(
            count(explode(';', $zeilen[1])),
            explode(';', $zeilen[2]),
            'Ein Buchungssatz muss genauso viele Felder haben wie die Spaltenzeile.',
        );
    }

    public function test_ohne_kostenstelle_bleibt_das_feld_leer(): void
    {
        $datei = DatevBuchungsstapel::bauen(
            $this->eineBuchung(),
            $this->einstellungen(['datev_kostenstelle' => null]),
            CarbonImmutable::parse('2026-10-01'),
            CarbonImmutable::parse('2026-10-31'),
        );

        $zeilen = explode("\r\n", mb_convert_encoding($datei, 'UTF-8', 'Windows-1252'));
        $satz   = explode(';', $zeilen[2]);

        // Leer heisst leer - nicht ein Paar Anfuehrungszeichen.
        $this->assertSame('', $satz[DatevBuchungsstapel::FELD_KOST1 - 1]);
    }

    public function test_die_sachkontenlaenge_steht_im_kopf(): void
    {
        $datei = DatevBuchungsstapel::bauen(
            $this->eineBuchung(),
            $this->einstellungen(),
            CarbonImmutable::parse('2026-10-01'),
            CarbonImmutable::parse('2026-10-31'),
        );

        $kopf = explode(';', explode("\r\n", $datei)[0]);

        // Feld 14 laut Formatbeschreibung.
        $this->assertSame('6', $kopf[13]);
    }

    public function test_konten_die_nicht_zur_laenge_passen_werden_gemeldet(): void
    {
        // Laenge 6, aber die alten vierstelligen Konten stehen noch drin.
        $abweichung = CheckoutSetting::datevKontenlaengeAbweichung(6, [
            'Erlöskonto 7 %'               => '8300',
            'Erlöskonto 19 %'              => '840000',
            'Gegenkonto (Zahlungseingang)' => '',
        ]);

        // Das leere Feld meldet datevMissing(), nicht diese Pruefung.
        $this->assertSame(['Erlöskonto 7 %'], $abweichung);
    }
}
