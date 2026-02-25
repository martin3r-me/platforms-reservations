<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')
                ->constrained('reservation_bookings')
                ->cascadeOnDelete();
            $table->string('mollie_id')->nullable()->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('EUR');
            // open | pending | authorized | expired | failed | canceled | paid
            $table->string('status')->default('open');
            $table->string('method')->nullable(); // ideal, creditcard, paypal, …
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->decimal('refunded_amount', 10, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_payments');
    }
};
