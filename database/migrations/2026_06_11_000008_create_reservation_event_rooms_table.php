<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_event_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('reservation_events')->cascadeOnDelete();
            $table->foreignId('floor_plan_id')->constrained('reservation_floor_plans')->cascadeOnDelete();

            $table->unsignedSmallInteger('sort_order')->default(0);          // Freigabe-Reihenfolge (sequential)
            $table->unsignedTinyInteger('fill_threshold_percent')->default(100); // ab wann Vorgänger-Raum als "voll" gilt
            $table->unsignedSmallInteger('capacity_override')->nullable();   // Plätze; null = Summe der Tisch-Kapazitäten
            $table->boolean('is_open_override')->nullable();                 // manuelles Auf/Zu schlägt Logik

            $table->timestamps();

            $table->unique(['event_id', 'floor_plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_event_rooms');
    }
};
