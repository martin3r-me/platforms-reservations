<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zählt ein No-Show zum Umsatz?
 *
 * Die Frage ist kaufmännisch und nicht technisch, deshalb eine Einstellung:
 * Wer im Shop über Mollie bezahlt und dann nicht kommt, bekommt das Geld in
 * aller Regel nicht zurück – dann ist es Erlös und gehört in die Buchhaltung.
 * Wer dagegen vor Ort kassiert oder aus Kulanz immer erstattet, hat bei einem
 * No-Show gar keine Einnahme.
 *
 * Default false: Bis hierher zählten No-Shows nirgends mit. Ein Default true
 * würde bei jedem Bestandskunden über Nacht den Jahresumsatz ändern, ohne dass
 * jemand etwas angefasst hätte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_checkout_settings', function (Blueprint $table) {
            $table->boolean('revenue_includes_no_show')->default(false)->after('datev_modus');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_checkout_settings', function (Blueprint $table) {
            $table->dropColumn('revenue_includes_no_show');
        });
    }
};
