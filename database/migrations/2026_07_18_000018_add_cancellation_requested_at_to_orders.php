<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zeitpunkt einer angefragten Stornierung (Freigabe-Modus) – für die
 * Admin-Liste offener Storno-Anfragen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_orders', function (Blueprint $table) {
            $table->timestamp('cancellation_requested_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_orders', function (Blueprint $table) {
            $table->dropColumn('cancellation_requested_at');
        });
    }
};
