<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aussteller-/Rechnungsangaben des Teams (für Belege/Bewirtungsbeleg):
 * Firmenname, Anschrift, USt-IdNr, Steuernummer, Kontakt. Als JSON, damit
 * weitere Felder ohne Migration ergänzt werden können.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_checkout_settings', function (Blueprint $table) {
            $table->json('issuer')->nullable()->after('confirmation_channel_id');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_checkout_settings', function (Blueprint $table) {
            $table->dropColumn('issuer');
        });
    }
};
