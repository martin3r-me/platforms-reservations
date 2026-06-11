<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_menu_categories', function (Blueprint $table) {
            // Kategoriebild (16:9) – ContextFile aus platform-core, bewusst ohne FK
            $table->unsignedBigInteger('image_context_file_id')->nullable()->after('is_active');
            $table->index('image_context_file_id');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_menu_categories', function (Blueprint $table) {
            $table->dropIndex(['image_context_file_id']);
            $table->dropColumn('image_context_file_id');
        });
    }
};
