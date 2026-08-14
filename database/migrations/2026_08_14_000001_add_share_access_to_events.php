<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lesezugriff auf Küche und Laufzettel per Link, ohne Konto.
 *
 * Hintergrund: Veranstaltungsleiter sind vor Ort, wenn niemand aus dem
 * Catering da ist. Ein Modul-Zugang scheidet aus – dort liegen Gästenamen
 * und E-Mail-Adressen, und er hinge an einem M365-Konto, das diese Personen
 * nicht haben.
 *
 * Der Link zeigt deshalb NUR Küche und Laufzettel. Die Buchungsliste mit
 * Kontaktdaten bleibt außen vor: Ein Link ist ein Schlüssel, und
 * weitergeleitete Mails verteilen ihn.
 *
 * Drei Schranken:
 *   - share_token   Zufallswert im Link. Neu würfeln = alle alten Links tot.
 *   - share_pin_hash Zweite Hürde, damit ein durchgereichter Link allein
 *                    nicht genügt. Nur der Hash wird gespeichert.
 *   - Ablauf        Wird nicht gespeichert, sondern aus dem Veranstaltungs-
 *                   datum abgeleitet (+ 1 Tag). Ein Feld könnte veralten,
 *                   wenn der Termin verschoben wird.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_events', function (Blueprint $table) {
            $table->string('share_token', 64)->nullable()->unique()->after('uuid');
            $table->string('share_pin_hash')->nullable()->after('share_token');
            $table->timestamp('share_created_at')->nullable()->after('share_pin_hash');
        });

        Schema::create('reservation_event_share_accesses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                ->constrained('reservation_events')
                ->cascadeOnDelete();

            // Gekürzt gespeichert (letztes Oktett entfernt): Für "wurde der
            // Link missbraucht?" reicht das, und eine vollständige IP wäre
            // personenbezogen ohne echten Mehrwert.
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            // false = PIN falsch. Gehäufte Fehlschläge sind das Signal.
            $table->boolean('successful')->default(true);

            $table->timestamp('created_at')->nullable();

            $table->index(['event_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_event_share_accesses');

        Schema::table('reservation_events', function (Blueprint $table) {
            $table->dropUnique(['share_token']);
            $table->dropColumn(['share_token', 'share_pin_hash', 'share_created_at']);
        });
    }
};
