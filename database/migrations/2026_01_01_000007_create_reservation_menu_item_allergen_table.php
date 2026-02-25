<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_menu_item_allergen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')
                ->constrained('reservation_menu_items')
                ->cascadeOnDelete();
            $table->foreignId('allergen_id')
                ->constrained('reservation_allergens')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['menu_item_id', 'allergen_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_menu_item_allergen');
    }
};
