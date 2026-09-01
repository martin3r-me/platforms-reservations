<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sollen Gäste einen noch gesperrten Raum sehen?
 *
 * Bei sequentieller Freigabe ist Raum 2 zu, bis Raum 1 seinen Schwellwert
 * erreicht. Zwei Haltungen dazu sind vertretbar:
 *
 *   ANZEIGEN – der Gast sieht, dass es weitergeht („ROSSINI · öffnet, sobald
 *   Terasse zu 100 % gefüllt ist"). Das nimmt der vollen Terasse den Beigeschmack
 *   von ausverkauft.
 *
 *   VERBERGEN – das Haus möchte nicht zeigen, welche Räume es noch gäbe, solange
 *   sie nicht dran sind. Der Gast sieht schlicht die Räume, die offen sind.
 *
 * Vorgabe ist ANZEIGEN, weil das der Zustand ist, den wir gerade gebaut haben,
 * und weil Verbergen der Anfang des alten Problems war: ein Abend, der voll
 * aussieht, obwohl nebenan alles frei ist.
 *
 * Gefahrlos in beide Richtungen: Ein gesperrter Raum ist per Definition einer,
 * in dem gerade niemand buchen kann – vor ihm liegt immer ein offener.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_checkout_settings', function (Blueprint $table) {
            $table->boolean('show_closed_rooms')->default(true)->after('max_guest_count');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_checkout_settings', function (Blueprint $table) {
            $table->dropColumn('show_closed_rooms');
        });
    }
};
