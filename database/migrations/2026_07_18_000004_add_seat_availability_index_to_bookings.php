<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stützt den Hot-Path der Platzverfügbarkeit (SeatAvailabilityService:
 * bookedSeatsByTable + remainingSeats), der bei jedem Gast-Checkout-Render
 * pro Slot/Tischplan läuft:
 *   WHERE event_slot_id = ? AND table_id (IN|=) ? AND status NOT IN (...)
 *   GROUP BY table_id
 * Kein bestehender Index führt mit event_slot_id ([event_id, event_slot_id]
 * führt mit event_id). Dieser Composite-Index bedient beide Methoden direkt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_bookings', function (Blueprint $table) {
            $table->index(
                ['event_slot_id', 'table_id', 'status'],
                'reservation_bookings_slot_table_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('reservation_bookings', function (Blueprint $table) {
            $table->dropIndex('reservation_bookings_slot_table_status_index');
        });
    }
};
