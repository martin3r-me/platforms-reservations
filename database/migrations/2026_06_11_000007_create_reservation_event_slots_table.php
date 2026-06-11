<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_event_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('reservation_events')->cascadeOnDelete();
            $table->string('name');                          // z.B. "Pause 1"
            $table->time('time_start');
            $table->time('time_end')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_event_slots');
    }
};
