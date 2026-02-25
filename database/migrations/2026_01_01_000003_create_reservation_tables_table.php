<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('floor_plan_id')->constrained('reservation_floor_plans')->cascadeOnDelete();
            $table->string('label'); // z.B. "Tisch 1", "VIP-Box A"
            $table->unsignedSmallInteger('capacity')->default(2);
            // Position & Größe im Tischplan (in Prozent oder px)
            $table->float('x')->default(0);
            $table->float('y')->default(0);
            $table->float('width')->default(80);
            $table->float('height')->default(80);
            $table->enum('shape', ['round', 'square', 'rectangle'])->default('square');
            $table->string('color')->nullable(); // Custom Farbe für Admin-Ansicht
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['floor_plan_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_tables');
    }
};
