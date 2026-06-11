<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_floor_plans', function (Blueprint $table) {
            // Raum-Default: dient nur als Vorbelegung beim Anlegen eines Termins
            $table->foreignId('default_sales_list_id')->nullable()->after('layout_json')
                ->constrained('reservation_sales_lists')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reservation_floor_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_sales_list_id');
        });
    }
};
