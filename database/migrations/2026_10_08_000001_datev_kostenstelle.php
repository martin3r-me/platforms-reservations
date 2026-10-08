<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kostenstelle fuer den DATEV-Buchungsstapel (KOST1).
 *
 * Die Buchhaltung will die Umsaetze aus PausePlus auf einer eigenen
 * Kostenstelle sehen. Sie ist fuer das ganze Haus gleich und steht deshalb in
 * den Einstellungen, nicht am Termin - kommt je Raum oder Veranstaltung einmal
 * eine eigene dazu, ist der Weg von hier aus kurz.
 *
 * 36 Zeichen, weil die Formatbeschreibung fuer KOST1 so viele zulaesst. In der
 * Praxis sind es vier bis acht Ziffern.
 *
 * Nullable und ohne Vorgabe: Ohne Kostenstelle bleibt das Feld im Stapel leer,
 * und das ist fuer jeden Mandanten ohne Kostenrechnung genau richtig.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_checkout_settings', function (Blueprint $table) {
            $table->string('datev_kostenstelle', 36)->nullable()->after('datev_geldkonto');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_checkout_settings', function (Blueprint $table) {
            $table->dropColumn('datev_kostenstelle');
        });
    }
};
