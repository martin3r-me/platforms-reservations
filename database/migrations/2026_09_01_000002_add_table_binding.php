<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wem gehört ein Tisch, wenn ein Termin mehrere Pausen hat?
 *
 * Zwei Betriebsarten, beide vertretbar:
 *
 *   event – Der Tisch gehört dem Gast den ganzen Abend. Ein Raum, ein Tisch,
 *   alle Pausen. Ein Tisch, der in irgendeiner Pause belegt ist, ist in allen
 *   belegt – auch wenn der Gast nur in der ersten etwas bestellt hat.
 *
 *   slot – Jede Pause wird einzeln vergeben. Der Saal wird zwischen den Pausen
 *   geräumt und neu verkauft; der Gast kann in Pause 2 an einem anderen Tisch
 *   und sogar in einem anderen Raum sitzen.
 *
 * Vorgabe ist EVENT: die strengere Lesart, sie verkauft nie einen Platz zu viel.
 * Und die realistische – in einer 20-Minuten-Pause setzt niemand einen Saal um.
 *
 * Bemerkenswert ist, dass es diese Entscheidung bisher nicht gab: Der
 * SeatAvailabilityService zählte ausnahmslos je Pause. Das war nie beschlossen,
 * es ist das, was herauskommt, wenn es nur eine Pause gibt.
 *
 * Deshalb ist diese Migration im Betrieb WIRKUNGSLOS: Alle Termine bei Culinaria
 * haben genau eine Pause, und bei einer Pause liefern beide Betriebsarten
 * dieselben Zahlen. Sie legt nur fest, wonach der erste Termin mit zwei Pausen
 * gerechnet wird.
 *
 * Am Termin nullable = „der Vorgabe des Teams folgen". Bewusst nicht beim
 * Anlegen kopiert: Ändert das Haus seine Vorgabe, sollen die Termine folgen, die
 * nie eine eigene bekommen haben. Gleiches Muster wie max_guest_count.
 *
 * Keine Spalte an den Buchungen – siehe docs-intern/PLAN-mehrere-pausen.md, Abschnitt F:
 * Anders als Preise oder der Zielort darf die Betriebsart nicht je Buchung
 * eingefroren werden. Die Kapazität eines Tisches muss für alle Buchungen daran
 * nach derselben Regel gerechnet werden, sonst ergibt sie keine Zahl.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_checkout_settings', function (Blueprint $table) {
            $table->string('table_binding', 10)->default('event')->after('show_closed_rooms');
        });

        Schema::table('reservation_events', function (Blueprint $table) {
            $table->string('table_binding', 10)->nullable()->after('room_release_mode');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_checkout_settings', function (Blueprint $table) {
            $table->dropColumn('table_binding');
        });

        Schema::table('reservation_events', function (Blueprint $table) {
            $table->dropColumn('table_binding');
        });
    }
};
