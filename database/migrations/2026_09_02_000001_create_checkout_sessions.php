<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laufende Bestellwege – wer gerade im Shop steht, ohne schon bestellt zu haben.
 *
 * Eine Zeile lebt Minuten, nicht Tage: Der Shop meldet den Stand, die Zeile wird
 * überschrieben, und nach 30 Minuten ohne Lebenszeichen fliegt sie raus (siehe
 * CheckoutSession::AUFRAEUMEN_NACH_MINUTEN). Sie ist bewusst kein Protokoll –
 * was bestellt wurde, steht in den Buchungen, und was jemand angeschaut hat,
 * geht uns nichts an.
 *
 * Deshalb steht hier auch KEIN Personenbezug: kein Name, keine E-Mail, kein
 * Telefon, keine IP. Gezählt werden Vorgänge, nicht Menschen.
 *
 * checkout_ref ist eine Zufalls-UUID, die der Shop je Bestellweg erzeugt –
 * ausdrücklich NICHT die Laravel-Session-ID. Die ist bei
 * SESSION_DRIVER=database ein aktives Login-Token; eine Kopie davon in einer
 * zweiten Tabelle wäre ein zweiter Ort, an dem ein Diebstahl reicht.
 *
 * Ebenfalls nicht gespeichert: der gewählte Tisch. Er wäre die interessanteste
 * Zahl und zugleich die gefährlichste – ein Tisch im Bestellweg ist nicht
 * reserviert, und wer ihn hier sähe, würde ihn für belegt halten.
 *
 * Der Schritt steht als NAME, nicht als Nummer. Der Bestellweg baut seine
 * Schritte seit dem 01.09.2026 zur Laufzeit als Liste, in der Schritte fehlen
 * dürfen; eine Nummer bekäme beim nächsten Umbau rückwirkend eine andere
 * Bedeutung. Siehe docs-intern/PLAN-live-checkouts.md, Abschnitt B.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_checkout_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('reservation_events')->cascadeOnDelete();

            // An welcher Pause der Gast gerade arbeitet. Fehlt, solange er keine
            // gewählt hat - und bei Terminen mit nur einer Pause meist auch dann,
            // weil es dort keinen Schritt "Wann?" gibt.
            $table->foreignId('event_slot_id')->nullable()
                ->constrained('reservation_event_slots')->nullOnDelete();

            $table->string('checkout_ref', 36);
            $table->string('step', 20);
            $table->unsignedSmallInteger('party_size')->nullable();
            $table->unsignedSmallInteger('items_count')->default(0);
            $table->decimal('cart_total', 10, 2)->default(0);
            $table->timestamp('last_seen_at');
            $table->timestamps();

            // Ein Bestellweg, eine Zeile. Der Shop meldet immer dieselbe Kennung,
            // geschrieben wird per updateOrCreate - ohne diesen Schlüssel entstünde
            // je Meldung eine neue Zeile.
            $table->unique(['team_id', 'checkout_ref']);

            // Die Abfrage der Ansicht: ein Termin, alles Lebendige.
            $table->index(['event_id', 'last_seen_at']);

            // Das Aufräumen läuft team-übergreifend nur über die Zeit.
            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_checkout_sessions');
    }
};
