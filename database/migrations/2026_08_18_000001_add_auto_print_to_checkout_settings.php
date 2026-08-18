<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Neue Aufträge automatisch drucken.
 *
 * Bisher musste jemand die Buchungsliste offen haben und je Zeile auf Drucken
 * klicken. Kommt eine Bestellung abends oder während des Betriebs herein,
 * bemerkt sie niemand rechtzeitig.
 *
 * Ausgelöst wird der Druck, sobald eine Buchung auf "bestätigt" wechselt –
 * also nach erfolgreicher Zahlung ebenso wie bei manueller Bestätigung im
 * Backoffice. Der Druckknopf je Zeile bleibt unverändert bestehen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_checkout_settings', function (Blueprint $table) {
            $table->boolean('auto_print_enabled')->default(false)->after('issuer');

            // Entweder ein einzelner Drucker ODER eine Gruppe – wie beim
            // manuellen Druck. Bewusst ohne Fremdschlüssel: Das Druck-Modul
            // ist optional, ein harter Verweis würde die Migration bei
            // Installationen ohne Drucker scheitern lassen.
            $table->unsignedBigInteger('auto_print_printer_id')->nullable()->after('auto_print_enabled');
            $table->unsignedBigInteger('auto_print_printer_group_id')->nullable()->after('auto_print_printer_id');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_checkout_settings', function (Blueprint $table) {
            $table->dropColumn(['auto_print_enabled', 'auto_print_printer_id', 'auto_print_printer_group_id']);
        });
    }
};
