<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Normalisierte Tisch-Koordinaten (0…1) relativ zur Grundriss-Fläche.
 *
 * Vorher lagen x/y/width/height als absolute Pixel im jeweiligen Canvas –
 * Editor (dynamische Breite) und Gast-Viewer (fix 800×600) interpretierten
 * diese unterschiedlich, wodurch die Tische verrutschten. Prozentwerte sind
 * geräte- und größenunabhängig und rendern überall identisch.
 *
 * Konvention:
 *   x_pct / y_pct = MITTELPUNKT des Tisches (Anteil der Flächenbreite/-höhe)
 *   w_pct         = Breite als Anteil der Flächenbreite
 *   h_pct         = Höhe  als Anteil der Flächenhöhe
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_tables', function (Blueprint $table) {
            $table->float('x_pct')->default(0.5)->after('y');
            $table->float('y_pct')->default(0.5)->after('x_pct');
            $table->float('w_pct')->default(0.1)->after('height');
            $table->float('h_pct')->default(0.12)->after('w_pct');
        });

        // Best-Effort-Backfill aus den alten Pixeln (alter Gast-Raum 800×600).
        // Mittelpunkt + Größe normalisieren, auf [0,1] begrenzen.
        foreach (DB::table('reservation_tables')->get() as $row) {
            $w = max(1.0, (float) ($row->width ?? 80));
            $h = max(1.0, (float) ($row->height ?? 80));
            $cx = ((float) ($row->x ?? 0) + $w / 2) / 800;
            $cy = ((float) ($row->y ?? 0) + $h / 2) / 600;

            DB::table('reservation_tables')->where('id', $row->id)->update([
                'x_pct' => min(1, max(0, $cx)),
                'y_pct' => min(1, max(0, $cy)),
                'w_pct' => min(1, max(0.02, $w / 800)),
                'h_pct' => min(1, max(0.02, $h / 600)),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('reservation_tables', function (Blueprint $table) {
            $table->dropColumn(['x_pct', 'y_pct', 'w_pct', 'h_pct']);
        });
    }
};
