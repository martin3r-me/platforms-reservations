<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #523 – Vorlaufzeit je Standzeit-Klasse (Laufrunden).
 * lead_time_minutes = Minuten vor Pausenbeginn, zu denen der Artikel platziert
 * werden soll. Daraus ergeben sich Ziel-Uhrzeit UND Reihenfolge der Laufrunde.
 * NULL = "absolut egal" (zeitunkritisch, vorab platzierbar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_holding_classes', function (Blueprint $table) {
            $table->unsignedSmallInteger('lead_time_minutes')->nullable()->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_holding_classes', function (Blueprint $table) {
            $table->dropColumn('lead_time_minutes');
        });
    }
};
