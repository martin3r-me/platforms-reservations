<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_checkout_settings', function (Blueprint $table) {
            // Standard-Raumfreigabe für neue Termine (parallel | sequential).
            $table->string('default_room_release_mode')->default('parallel')->after('privacy_url');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_checkout_settings', function (Blueprint $table) {
            $table->dropColumn('default_room_release_mode');
        });
    }
};
