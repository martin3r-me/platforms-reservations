<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * tax_rate war eine String-Spalte ('7.00'), wodurch Werte inkonsistent
 * gespeichert werden konnten ('7' vs '7.00') und die MwSt-Aggregation
 * (Finance::taxBreakdown gruppiert per SQL über tax_rate) Gruppen fragmentiert
 * hätte. Umstellung auf decimal(5,2) normalisiert Speicherung und Vergleich –
 * konsistent mit price (decimal). Bestehende Werte werden von MySQL sauber
 * konvertiert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_menu_items', function (Blueprint $table) {
            $table->decimal('tax_rate', 5, 2)->default('7.00')->change();
        });

        Schema::table('reservation_booking_items', function (Blueprint $table) {
            $table->decimal('tax_rate', 5, 2)->default('7.00')->change();
        });
    }

    public function down(): void
    {
        Schema::table('reservation_menu_items', function (Blueprint $table) {
            $table->string('tax_rate')->default('7.00')->change();
        });

        Schema::table('reservation_booking_items', function (Blueprint $table) {
            $table->string('tax_rate')->default('7.00')->change();
        });
    }
};
