<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Die Vier-Augen-Pflicht gilt für NEUE Teams nicht mehr per Vorgabe.
 *
 * Sie war standardmäßig an, und das ist eine Falle: Einschalten darf jeder
 * allein, Abschalten braucht zwei verschiedene Menschen. Ein Team, das allein
 * pflegt, kommt also nie wieder heraus – und kann die Pflicht zugleich nicht
 * erfüllen, weil niemand die eigene Einreichung freigeben darf. Ein Schloss
 * ohne Schlüssel, und niemand hatte es sich ausgesucht.
 *
 * Wer das Prinzip will, schaltet es ein; das geht sofort und allein. Wer es
 * nicht will, stolpert nicht mehr hinein.
 *
 * BESTEHENDE Teams bleiben unangetastet. Eine Kontrolle abzuschalten, die ein
 * Team vielleicht bewusst gewählt hat, ist nicht die Aufgabe einer Migration –
 * und stillschweigend schon gar nicht.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_checkout_settings', function (Blueprint $table) {
            $table->boolean('four_eyes_enabled')->default(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('reservation_checkout_settings', function (Blueprint $table) {
            $table->boolean('four_eyes_enabled')->default(true)->change();
        });
    }
};
