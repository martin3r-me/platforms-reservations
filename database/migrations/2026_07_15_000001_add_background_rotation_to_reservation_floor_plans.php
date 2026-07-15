<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_floor_plans', function (Blueprint $table) {
            // Rotation des Grundriss-Hintergrundbilds in Grad (0/90/180/270).
            $table->unsignedSmallInteger('background_rotation')->default(0)->after('background_context_file_id');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_floor_plans', function (Blueprint $table) {
            $table->dropColumn('background_rotation');
        });
    }
};
