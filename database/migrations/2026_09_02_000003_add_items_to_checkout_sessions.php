<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Was im Warenkorb liegt – je Pause, als { pause_id: { artikel_id: menge } }.
 *
 * Bisher stand hier nur, WIE VIEL im Korb liegt (items_count, cart_total).
 * Für die Zahl im Balken reicht das; für die Frage „was zieht der da gerade
 * rein?" nicht.
 *
 * Gespeichert werden IDs und Mengen, keine Namen. Die Artikel gehören dem
 * Office; ihre Namen dort nachzuschlagen ist eine Zeile Code und liefert
 * immer den aktuellen Stand. Vom Shop mitgeschickte Namen wären eine Kopie,
 * die veraltet, sobald jemand einen Artikel umbenennt – und sie wären in der
 * Sprache, in der der Gast einkauft.
 *
 * Nach Pausen gegliedert, weil der Warenkorb es ist: Sekt zur ersten Pause und
 * Kaffee zur zweiten ist der Normalfall. Eine flache Liste könnte die Frage
 * „was kommt wann?" nicht beantworten.
 *
 * Bleibt Teil einer Zeile, die nach 30 Minuten ohne Lebenszeichen verschwindet.
 * Das hier ist kein Protokoll dessen, was jemand angeschaut hat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_checkout_sessions', function (Blueprint $table) {
            $table->json('items')->nullable()->after('items_count');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_checkout_sessions', function (Blueprint $table) {
            $table->dropColumn('items');
        });
    }
};
