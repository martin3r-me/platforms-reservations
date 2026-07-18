<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #520/#521 – Anmeldefelder per Setting steuerbar (Pflicht/optional/aus).
 * Pro Team konfigurierbare Sichtbarkeit/Pflicht der Gast-Kontaktfelder.
 * Defaults: E-Mail Pflicht, Rufnummer optional, Notiz optional.
 * (Name & Personenzahl sind fest Pflicht und daher nicht konfigurierbar.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_checkout_settings', function (Blueprint $table) {
            $table->string('field_email', 16)->default('required')->after('privacy_url');
            $table->string('field_phone', 16)->default('optional')->after('field_email');
            $table->string('field_notes', 16)->default('optional')->after('field_phone');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_checkout_settings', function (Blueprint $table) {
            $table->dropColumn(['field_email', 'field_phone', 'field_notes']);
        });
    }
};
