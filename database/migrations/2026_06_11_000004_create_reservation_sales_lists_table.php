<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_sales_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('name');                          // z.B. "Konzert", "Kantine"
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);   // Team-Default
            $table->timestamps();

            $table->index(['team_id', 'is_default']);
        });

        Schema::create('reservation_sales_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_list_id')->constrained('reservation_sales_lists')->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained('reservation_menu_items')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['sales_list_id', 'menu_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_sales_list_items');
        Schema::dropIfExists('reservation_sales_lists');
    }
};
