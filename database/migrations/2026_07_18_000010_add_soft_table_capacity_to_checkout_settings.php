<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Weiche Tisch-Kapazität: Großgruppen dürfen einen LEEREN Tisch über die
 * Platzzahl hinaus belegen (z. B. Stehtische als Treffpunkt). Passt die Gruppe
 * nicht in die freien Plätze und ist der Tisch nicht leer, bleibt er gesperrt.
 * Default aus = bisheriges strenges Verhalten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_checkout_settings', function (Blueprint $table) {
            $table->boolean('soft_table_capacity')->default(false)->after('field_notes');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_checkout_settings', function (Blueprint $table) {
            $table->dropColumn('soft_table_capacity');
        });
    }
};
