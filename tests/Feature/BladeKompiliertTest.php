<?php

namespace Platform\Reservation\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use Platform\Reservation\Tests\TestCase;

/**
 * Jede Blade-Datei des Moduls muss zu gültigem PHP kompilieren.
 *
 * Anlass sind zwei tatsächliche Ausfälle, beide mit derselben Ursache: Blade
 * schneidet den Rohblock von der öffnenden Direktive bis zum nächsten @endphp
 * heraus. Steht irgendwo im Baum ein einzeiliges @php(...) und weiter unten ein
 * mehrzeiliger Block, verschluckt die Datei alles dazwischen. Kompiliert wird
 * dann bis zum Dateiende, und der Fehler meldet sich als "unexpected endif" in
 * einer Zeile, die nichts damit zu tun hat.
 *
 * Das ist die Art Fehler, die man nur im Browser sieht – und im Zweifel erst,
 * wenn ein Gast auf der Seite steht. Ein Test dafür kostet zwei Sekunden.
 *
 * Geprüft wird das Kompilat mit php -l, nicht ausgeführt: Ausführen bräuchte
 * die Komponenten der Plattform, und um die geht es hier nicht.
 *
 * Aus demselben Grund bleiben die <x-…>-Tags uncompiliert (withoutComponentTags):
 * Sie kämen aus martin3r/platform-ui-tailwind, das hier nicht installiert ist –
 * ein Fehlschlag daran wäre eine Aussage über die Testumgebung, nicht über den
 * Code. Die Struktur aus Direktiven und Rohblöcken, um die es geht, prüft der
 * Lauf vollständig.
 */
class BladeKompiliertTest extends TestCase
{
    #[Test]
    public function jede_blade_datei_ergibt_gueltiges_php(): void
    {
        $dateien = $this->bladeDateien();

        $this->assertGreaterThan(20, count($dateien), 'Es wurden kaum Ansichten gefunden – stimmt der Pfad noch?');

        // Gibt void zurueck, ist also keine Kette - einmal vorher schalten.
        Blade::withoutComponentTags();

        $kaputt = [];

        foreach ($dateien as $datei) {
            $ziel = tempnam(sys_get_temp_dir(), 'blade') . '.php';
            file_put_contents($ziel, Blade::compileString(file_get_contents($datei)));

            $ausgabe = [];
            exec('php -l ' . escapeshellarg($ziel) . ' 2>&1', $ausgabe, $code);
            unlink($ziel);

            if ($code !== 0) {
                $kaputt[] = basename($datei) . ': ' . implode(' ', $ausgabe);
            }
        }

        $this->assertSame([], $kaputt, "Diese Ansichten kompilieren nicht:\n" . implode("\n", $kaputt));
    }

    /** @return array<int, string> */
    protected function bladeDateien(): array
    {
        $wurzel   = __DIR__ . '/../../resources/views';
        $gefunden = [];

        $lauf = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($wurzel));

        foreach ($lauf as $datei) {
            if ($datei->isFile() && str_ends_with($datei->getFilename(), '.blade.php')) {
                $gefunden[] = $datei->getPathname();
            }
        }

        sort($gefunden);

        return $gefunden;
    }
}
