<?php

namespace Platform\Reservation\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Platform\Core\Models\ContextFile;

/**
 * Bilder für die PDF-Erzeugung als Data-URI einbetten.
 *
 * Zwei Gründe, warum der direkte Weg über die Bild-URL nicht funktioniert:
 *
 * 1. dompdf lädt standardmäßig keine externen Ressourcen (enable_remote = false).
 *    Ein <img src="https://…"> bliebe schlicht leer.
 * 2. ContextFileService speichert Bilder IMMER als WebP – auch mit
 *    keep_original, das ist nur ein DB-Flag und bewahrt keine Datei. Auf
 *    dompdfs WebP-Unterstützung wollen wir uns nicht verlassen, deshalb wird
 *    hier nach PNG gewandelt.
 */
class PdfImage
{
    /** Wie lange die fertige Data-URI zwischengehalten wird. */
    protected const CACHE_MINUTES = 60 * 24;

    /**
     * Data-URI eines ContextFiles, PNG-konvertiert. null, wenn die Datei fehlt
     * oder nicht gewandelt werden kann – der Aufrufer lässt das Bild dann weg,
     * statt ein kaputtes PDF zu erzeugen.
     */
    public static function dataUri(?ContextFile $file): ?string
    {
        if (! $file) {
            return null;
        }

        // updated_at im Schlüssel: ein ausgetauschtes Logo verdrängt den Eintrag.
        $key = 'reservation.pdf-image.' . $file->id . '.' . ($file->updated_at?->timestamp ?? 0);

        return Cache::remember($key, now()->addMinutes(self::CACHE_MINUTES), function () use ($file) {
            try {
                if (! Storage::disk($file->disk)->exists($file->path)) {
                    return null;
                }

                $bytes = Storage::disk($file->disk)->get($file->path);
            } catch (\Throwable $e) {
                report($e);

                return null;
            }

            $png = self::toPng($bytes);

            return $png ? 'data:image/png;base64,' . base64_encode($png) : null;
        });
    }

    /**
     * Nach PNG wandeln. Über die GD-Funktionen von PHP statt über Intervention,
     * damit das Modul keine zusätzliche Abhängigkeit bekommt – GD ist ohnehin
     * vorausgesetzt, weil der ContextFileService damit arbeitet.
     */
    protected static function toPng(string $bytes): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagepng')) {
            return null;
        }

        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            return null;
        }

        // Transparenz erhalten – Logos liegen meist freigestellt vor.
        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        $ok = imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $ok && $png !== '' ? $png : null;
    }
}
