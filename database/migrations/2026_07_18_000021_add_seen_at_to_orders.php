<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Team-geteilter "gesehen"-Status für den Posteingang: seen_at = null → ungesehen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_orders', function (Blueprint $table) {
            $table->timestamp('seen_at')->nullable()->after('cancellation_requested_at');
            $table->index(['team_id', 'seen_at']);
        });
    }

    public function down(): void
    {
        Schema::table('reservation_orders', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'seen_at']);
            $table->dropColumn('seen_at');
        });
    }
};
