<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_floor_plans', function (Blueprint $table) {
            // Grundriss-/Hintergrundbild – ContextFile aus platform-core (ohne FK).
            $table->unsignedBigInteger('background_context_file_id')->nullable()->after('layout_json');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_floor_plans', function (Blueprint $table) {
            $table->dropColumn('background_context_file_id');
        });
    }
};
