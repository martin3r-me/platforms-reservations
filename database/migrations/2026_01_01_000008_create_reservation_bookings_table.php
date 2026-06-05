<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_bookings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('table_id')
                ->nullable()
                ->constrained('reservation_tables')
                ->nullOnDelete();

            // Gastdaten
            $table->string('guest_name');
            $table->string('guest_email')->nullable();
            $table->string('guest_phone')->nullable();
            $table->unsignedSmallInteger('guest_count')->default(1);
            $table->text('notes')->nullable();

            // Zeitraum
            $table->date('date');
            $table->time('time_start');
            $table->time('time_end')->nullable();

            // Status: pending | confirmed | cancelled | no_show | completed
            $table->string('status')->default('pending');

            // Zahlung (Mollie)
            $table->string('mollie_payment_id')->nullable();

            $table->timestamps();

            $table->index(['team_id', 'date', 'status']);
            $table->index(['table_id', 'date']);
            $table->index('guest_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_bookings');
    }
};
