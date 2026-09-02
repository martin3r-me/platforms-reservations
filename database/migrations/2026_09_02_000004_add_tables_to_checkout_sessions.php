<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Welchen Tisch der Gast angeklickt hat – je Pause, als { pause_id: tisch_id }.
 *
 * Diese Spalte hat es sich verdient, dass hier steht, warum es sie zuerst
 * NICHT gab: Ein Tisch im Bestellweg ist nicht reserviert. Er hält nichts
 * frei, zwei Gäste können denselben im Blick haben, und vergeben wird er erst
 * beim Bestellen. Steht er unkommentiert in einer Liste, disponiert jemand
 * danach.
 *
 * Das Haus will ihn trotzdem sehen, und das ist berechtigt: An einem vollen
 * Abend ist „auf welchen Tisch geht der gerade?" die naheliegendste Frage.
 *
 * Aufgeloest wird die Spannung nicht durch Weglassen, sondern durch den Ort:
 * Der Tisch steht NUR im Warenkorb-Fenster, nicht in der Liste – also dort, wo
 * der Satz „nichts davon ist bestellt" daneben steht und mitgelesen wird.
 *
 * Je Pause, weil die Tischbindung des Termins es so vorsieht: Wird jede Pause
 * einzeln vergeben, sitzt derselbe Gast in Pause 2 an einem anderen Tisch und
 * womoeglich in einem anderen Raum.
 *
 * Gespeichert wird die ID. Ob sie zu diesem Termin gehoert, entscheidet die
 * Ansicht beim Nachschlagen (ueber die Tischplaene des Termins) – ein fremder
 * Tisch loest sich dort einfach nicht auf. Das haelt den Schreibweg billig; er
 * laeuft bei jeder Meldung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_checkout_sessions', function (Blueprint $table) {
            $table->json('tables')->nullable()->after('items');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_checkout_sessions', function (Blueprint $table) {
            $table->dropColumn('tables');
        });
    }
};
