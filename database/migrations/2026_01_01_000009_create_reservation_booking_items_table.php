<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_booking_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')
                ->constrained('reservation_bookings')
                ->cascadeOnDelete();
            $table->foreignId('menu_item_id')
                ->constrained('reservation_menu_items')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('unit_price', 8, 2); // Preis zum Zeitpunkt der Buchung
            $table->string('tax_rate')->default('7.00');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_booking_items');
    }
};
