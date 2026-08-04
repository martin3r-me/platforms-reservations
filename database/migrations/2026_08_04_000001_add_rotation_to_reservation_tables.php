<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ausrichtung je Tisch: quer (90°) oder diagonal (45°) stellen.
 *
 * Gedreht wird nur die DARSTELLUNG der Fläche, nicht die Geometrie: x_pct/y_pct
 * bleiben der Mittelpunkt, w_pct/h_pct die unrotierten Maße. Damit bleiben
 * Positionierung, Kapazitätsrechnung und die Gast-Ansicht unberührt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_tables', function (Blueprint $table) {
            // 0…315 in 45°-Schritten; unsigned, weil negativ normalisiert wird.
            $table->unsignedSmallInteger('rotation')->default(0)->after('shape');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_tables', function (Blueprint $table) {
            $table->dropColumn('rotation');
        });
    }
};
