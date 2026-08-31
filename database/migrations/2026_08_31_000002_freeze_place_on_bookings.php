<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Der Ort einer Buchung wird eingefroren.
 *
 * Anlass ist kein gedachter Fall, sondern ein eingetretener: In der
 * Gartenhalle wurden Tisch 10 und Tisch 11 gelöscht. Die drei Buchungen, die
 * daran hingen, haben ihren Tisch verloren – table_id ist nullOnDelete, die
 * Buchung bleibt, der Verweis wird geleert. Auf Laufzettel und Bon stand
 * danach kein Ort mehr, im Export fehlte auch das Venue (es wird über den
 * Tisch abgeleitet), und welcher Tisch es war, ließ sich nirgends mehr
 * herausfinden.
 *
 * Dieselbe Regel, nach der Preise und Steuersätze eingefroren werden: Ein
 * Beleg soll zeigen, was galt.
 *
 * Zwei Spalten statt einer fertigen Zeichenkette, weil die Wortwahl in
 * Booking::zielortLabel() entsteht und nicht in der Datenbank. Ändert sich
 * später die Schreibweise, ändern sich alte Buchungen mit, statt in ihrer
 * alten Fassung zu erstarren.
 *
 * place_kind ist auf 'table' beschränkt, solange es nur Tische gibt. Die
 * Abholstationen (siehe docs/PLAN-abholstationen.md) tragen später 'station'
 * ein – das Feld ist genau dafür da.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_bookings', function (Blueprint $table) {
            $table->string('place_kind', 20)->nullable()->after('table_id');
            $table->string('place_label', 120)->nullable()->after('place_kind');
        });

        $this->nachfuellen();
    }

    /**
     * Vorhandene Buchungen einmalig nachfüllen.
     *
     * Damit der Schutz rückwirkend greift statt erst ab der nächsten
     * Bestellung. Wessen Tisch schon gelöscht ist, dem hilft es nicht mehr –
     * dort steht nichts mehr, was sich einfrieren ließe.
     *
     * In Laravel geschrieben und nicht als ein UPDATE mit JOIN: Die Testbasis
     * läuft auf SQLite, der Betrieb auf MySQL, und die JOIN-Syntax im UPDATE
     * unterscheidet sich zwischen beiden.
     */
    protected function nachfuellen(): void
    {
        $labels = DB::table('reservation_tables')->pluck('label', 'id');

        DB::table('reservation_bookings')
            ->whereNotNull('table_id')
            ->orderBy('id')
            ->chunkById(500, function ($buchungen) use ($labels) {
                foreach ($buchungen as $buchung) {
                    $label = $labels[$buchung->table_id] ?? null;

                    if ($label === null) {
                        continue;
                    }

                    DB::table('reservation_bookings')
                        ->where('id', $buchung->id)
                        ->update(['place_kind' => 'table', 'place_label' => $label]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('reservation_bookings', function (Blueprint $table) {
            $table->dropColumn(['place_kind', 'place_label']);
        });
    }
};
