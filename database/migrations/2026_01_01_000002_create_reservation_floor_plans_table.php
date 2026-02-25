<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_floor_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('reservation_venues')->cascadeOnDelete();
            $table->string('name');
            $table->json('layout_json')->nullable(); // Positionen, Größen, Hintergrund
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['venue_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_floor_plans');
    }
};
