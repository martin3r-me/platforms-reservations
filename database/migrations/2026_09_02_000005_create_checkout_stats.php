<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Was aus einem Bestellweg geworden ist – eine Zeile je beendetem Vorgang.
 *
 * Die Live-Tabelle ist nach 30 Minuten leer; sie beantwortet „was passiert
 * gerade", nicht „was passiert üblicherweise". Für die zweite Frage entsteht
 * hier eine Zeile, und zwar genau dann, wenn dort eine verschwindet:
 *
 *   LiveCheckoutService::beenden()    → der Gast hat bestellt   (ordered)
 *   LiveCheckoutService::aufraeumen() → 30 Minuten Stille       (abandoned)
 *
 * Es kommt also KEINE zweite Meldekette vom Shop hinzu, und die Zahlen können
 * nicht von der Live-Sicht abweichen: Es ist dieselbe Zeile, einen Moment
 * später.
 *
 * Was NICHT mitkommt: die Kennung des Bestellwegs, der Warenkorb-Inhalt, der
 * angeklickte Tisch. Das hier ist eine Statistik, keine Vorgangsakte. Ohne
 * Kennung lässt sich die Zeile auch nicht mehr einem Besuch zuordnen – deshalb
 * darf sie bleiben, während die Live-Zeile nach einer halben Stunde geht.
 *
 * event_id wird beim Löschen des Termins auf NULL gesetzt, nicht mitgelöscht:
 * Der Vorgang hat stattgefunden. Ein Cascade würde die Vergangenheit
 * umschreiben, sobald jemand einen alten Termin aufräumt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_checkout_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()
                ->constrained('reservation_events')->nullOnDelete();

            // Das Datum der VERANSTALTUNG, mitgeschrieben statt nachgeschlagen -
            // es soll den Termin ueberleben.
            $table->date('event_date')->nullable();

            // Der letzte Schritt, den der Gast gesehen hat. Als Name, wie in der
            // Live-Tabelle und aus demselben Grund.
            $table->string('last_step', 20);
            $table->unsignedTinyInteger('step_no')->nullable();
            $table->unsignedTinyInteger('step_count')->nullable();

            $table->string('outcome', 10);   // ordered | abandoned

            $table->unsignedSmallInteger('party_size')->nullable();
            $table->unsignedSmallInteger('items_count')->default(0);
            $table->decimal('cart_total', 10, 2)->default(0);
            $table->unsignedInteger('duration_seconds')->default(0);

            $table->timestamp('ended_at');

            // Kein created_at/updated_at: Diese Zeilen werden angelegt und nie
            // wieder angefasst. Zwei Zeitstempel, die dasselbe meinen wie
            // ended_at, wuerden frueher oder spaeter auseinanderlaufen.

            $table->index(['team_id', 'ended_at']);
            $table->index(['team_id', 'last_step']);
            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_checkout_stats');
    }
};
