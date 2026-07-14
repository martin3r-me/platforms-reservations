<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_menu_items', function (Blueprint $table) {
            // Freitext-Portionsgröße, z.B. "0,2 l" (Getränke) oder "250 g" (Speisen).
            $table->string('portion_size')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_menu_items', function (Blueprint $table) {
            $table->dropColumn('portion_size');
        });
    }
};
