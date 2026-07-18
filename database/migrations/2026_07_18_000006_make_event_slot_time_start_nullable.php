<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #518 – Optionale Pausenzeiten: Die Startzeit eines Pausen-Slots wird optional.
 * Ein Termin ist damit auch ohne konkrete Pausenzeit speicherbar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_event_slots', function (Blueprint $table) {
            $table->time('time_start')->nullable()->change();
        });

        // Buchungen erben die (nun optionale) Uhrzeit vom Slot.
        Schema::table('reservation_bookings', function (Blueprint $table) {
            $table->time('time_start')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('reservation_event_slots', function (Blueprint $table) {
            $table->time('time_start')->nullable(false)->change();
        });

        Schema::table('reservation_bookings', function (Blueprint $table) {
            $table->time('time_start')->nullable(false)->change();
        });
    }
};
