<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_bookings', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->after('table_id')
                ->constrained('reservation_events')->nullOnDelete();
            $table->foreignId('event_slot_id')->nullable()->after('event_id')
                ->constrained('reservation_event_slots')->nullOnDelete();

            // Timestamps statt Booleans = Nachweis (wann bestätigt)
            $table->timestamp('age_check_confirmed_at')->nullable()->after('status');
            $table->timestamp('legal_accepted_at')->nullable()->after('age_check_confirmed_at');

            // Mock-Zahlart in M1 (card | paypal | applepay); echte Zahlung via Mollie in M2
            $table->string('payment_method')->nullable()->after('legal_accepted_at');

            $table->index(['event_id', 'event_slot_id']);
        });
    }

    public function down(): void
    {
        Schema::table('reservation_bookings', function (Blueprint $table) {
            $table->dropIndex(['event_id', 'event_slot_id']);
            $table->dropConstrainedForeignId('event_slot_id');
            $table->dropConstrainedForeignId('event_id');
            $table->dropColumn(['age_check_confirmed_at', 'legal_accepted_at', 'payment_method']);
        });
    }
};
