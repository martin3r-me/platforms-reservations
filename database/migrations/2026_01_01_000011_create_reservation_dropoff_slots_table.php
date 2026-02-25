<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_dropoff_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->date('date');
            $table->time('time_from');
            $table->time('time_to');
            $table->unsignedSmallInteger('capacity')->default(10);
            $table->unsignedSmallInteger('booked_count')->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['team_id', 'date', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_dropoff_slots');
    }
};
