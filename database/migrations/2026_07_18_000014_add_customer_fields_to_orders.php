<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kundendaten auf Order-Ebene (einmal je Bestellung): Vor-/Nachname getrennt,
 * optional Firma und Rechnungsadresse. Die Booking behält einen zusammengesetzten
 * guest_name (Denormalisierung für Küche/Laufzettel/Mails).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_orders', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('status');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('company')->nullable()->after('last_name');
            $table->string('email')->nullable()->after('company');
            $table->string('phone', 40)->nullable()->after('email');
            $table->string('billing_street')->nullable()->after('phone');
            $table->string('billing_zip', 20)->nullable()->after('billing_street');
            $table->string('billing_city')->nullable()->after('billing_zip');
            $table->string('billing_country', 2)->nullable()->after('billing_city'); // ISO-3166-1 alpha-2
        });
    }

    public function down(): void
    {
        Schema::table('reservation_orders', function (Blueprint $table) {
            $table->dropColumn([
                'first_name', 'last_name', 'company', 'email', 'phone',
                'billing_street', 'billing_zip', 'billing_city', 'billing_country',
            ]);
        });
    }
};
