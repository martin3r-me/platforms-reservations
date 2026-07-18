<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Obergrenze für die weiche Tisch-Kapazität: maximale Gruppengröße, die einen
 * LEEREN Tisch über die Platzzahl hinaus belegen darf. NULL = unbegrenzt.
 * Greift nur zusammen mit soft_table_capacity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_checkout_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_group_empty_table')->nullable()->after('soft_table_capacity');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_checkout_settings', function (Blueprint $table) {
            $table->dropColumn('max_group_empty_table');
        });
    }
};
