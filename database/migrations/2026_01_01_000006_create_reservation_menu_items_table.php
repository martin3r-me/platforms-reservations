<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('category_id')
                ->constrained('reservation_menu_categories')
                ->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 8, 2)->default(0);
            $table->string('tax_rate')->default('7.00'); // MwSt in %
            $table->boolean('available')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['team_id', 'category_id', 'available']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_menu_items');
    }
};
