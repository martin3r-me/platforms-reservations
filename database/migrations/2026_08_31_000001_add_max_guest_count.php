<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Größte buchbare Gruppe – als eigene Einstellung.
 *
 * Bisher gab es dafür keine Zahl. Die API prüfte gegen ein hart notiertes
 * max:20, das Backoffice rechnete sich seinen eigenen Wert, und der Shop las
 * max_group_empty_table – also die weiche Tisch-Kapazität, die etwas ganz
 * anderes bedeutet ("so viele dürfen einen LEEREN Tisch überbelegen").
 *
 * Drei Stellen, drei Antworten. Jetzt eine: Vorgabe am Team, je Termin
 * überschreibbar.
 *
 * Warum 20 als Vorgabe: Das war die bisher wirksame Obergrenze der API. Ein
 * anderer Wert würde bei bestehenden Teams über Nacht das Verhalten ändern,
 * ohne dass jemand etwas angefasst hat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_checkout_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_guest_count')->default(20)->after('max_group_empty_table');
        });

        Schema::table('reservation_events', function (Blueprint $table) {
            // null = Vorgabe des Teams. Bewusst nullable statt Kopie beim
            // Anlegen: Ändert das Team seine Vorgabe, sollen die Termine
            // folgen, die nie eine eigene Zahl bekommen haben.
            $table->unsignedSmallInteger('max_guest_count')->nullable()->after('room_release_mode');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_checkout_settings', function (Blueprint $table) {
            $table->dropColumn('max_guest_count');
        });

        Schema::table('reservation_events', function (Blueprint $table) {
            $table->dropColumn('max_guest_count');
        });
    }
};
