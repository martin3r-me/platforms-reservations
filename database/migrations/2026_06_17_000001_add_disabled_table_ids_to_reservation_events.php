<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_events', function (Blueprint $table) {
            // Pro Termin gesperrte Tische (z.B. reserviert/defekt) – IDs aus reservation_tables.
            $table->json('disabled_table_ids')->nullable()->after('room_release_mode');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_events', function (Blueprint $table) {
            $table->dropColumn('disabled_table_ids');
        });
    }
};
