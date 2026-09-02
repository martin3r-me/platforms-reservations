<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Abholstationen – „Foyer links", „Rang 1 Bar".
 *
 * Eine Buchung zeigt künftig auf einen ORT: entweder auf einen Tisch oder auf
 * eine Station, genau eines von beidem. Siehe docs/PLAN-abholstationen.md.
 *
 * Eine eigene Tabelle statt eines Schalters „ist Abholstation" an
 * `reservation_tables`, und der dritte Grund wiegt am schwersten:
 *
 *   1. `reservation_tables.floor_plan_id` ist nicht nullable und cascadeOnDelete.
 *      „Foyer links" bräuchte einen erfundenen Tischplan, und das Löschen eines
 *      Raums risse eine Abholstelle mit, die gar nicht in ihm steht.
 *   2. `capacity` heißt am Tisch „Plätze", an der Station „Gäste je Pause".
 *      Gleiche Spalte, andere Bedeutung - das liest irgendwann jemand falsch.
 *   3. Die Platzrechnung läuft über `floorPlan->tables()`. Eine Station in
 *      dieser Menge zählte überall als Tisch mit Plätzen, solange nicht jede
 *      einzelne Schleife nachgebessert ist - und eine übersehene liefert keine
 *      Fehlermeldung, sondern eine falsche Zahl.
 *
 * Die Station gehört dem VENUE, nicht dem Raum. Sie kann in einem Tischplan
 * liegen (dann ist sie dort eine anklickbare Fläche), muss aber nicht - „Foyer
 * links" liegt gar nicht im Saal. Deshalb sind alle Lage-Spalten nullable, und
 * beim Löschen eines Plans verliert sie ihre Position, nicht ihre Existenz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_pickup_stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained('reservation_venues')->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();

            // Gaeste JE PAUSE. null = unbegrenzt. 150 in Pause 1 und 150 in
            // Pause 2 sind zusammen 300, nicht 150.
            $table->unsignedSmallInteger('capacity_per_slot')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            // Lage im Plan - optional, normalisiert (0…1) wie beim Tisch.
            $table->foreignId('floor_plan_id')->nullable()
                ->constrained('reservation_floor_plans')->nullOnDelete();
            $table->float('x_pct')->nullable();
            $table->float('y_pct')->nullable();
            $table->float('w_pct')->nullable();
            $table->float('h_pct')->nullable();
            $table->string('shape', 20)->nullable();
            $table->unsignedSmallInteger('rotation')->default(0);

            $table->timestamps();

            $table->index(['team_id', 'venue_id', 'sort_order']);
            $table->index('floor_plan_id');
        });

        // Zuordnung zum Termin - der Zwilling von reservation_event_rooms.
        Schema::create('reservation_event_stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('reservation_events')->cascadeOnDelete();
            $table->foreignId('pickup_station_id')->constrained('reservation_pickup_stations')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedSmallInteger('capacity_override')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'pickup_station_id']);
        });

        // Je Pause an oder aus. Zwei Pausen, Station nur in der ersten - genau
        // dieser Fall.
        //
        // EXPLIZITE Zeilen, kein „leer heisst alle": Sonst haette „keine Zeile"
        // zwei Bedeutungen - alle Pausen oder gar keine -, und diese Sorte
        // Zweideutigkeit faellt genau dann auf, wenn abends eine Station stumm
        // verschwindet.
        Schema::create('reservation_event_station_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_station_id')->constrained('reservation_event_stations')->cascadeOnDelete();
            $table->foreignId('event_slot_id')->constrained('reservation_event_slots')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['event_station_id', 'event_slot_id']);
        });

        // Getrennte Aufrufe: SQLite baut die Tabelle fuer einen Fremdschluessel
        // neu, und ein Index im selben Zug faellt dabei durch.
        Schema::table('reservation_bookings', function (Blueprint $table) {
            // Ohne ->after(): SQLite kann Spalten nicht einsortieren und baut
            // die Tabelle dafuer neu. Die Reihenfolge in der Datenbank ist
            // Kosmetik, ein vermeidbarer Tabellenumbau nicht.
            $table->foreignId('pickup_station_id')->nullable()
                ->constrained('reservation_pickup_stations')->nullOnDelete();
        });

        Schema::table('reservation_bookings', function (Blueprint $table) {
            // Fuer die Kapazitaetsabfrage: Gaeste je Station und Pause.
            $table->index(['event_slot_id', 'pickup_station_id', 'status'], 'reservation_bookings_station_idx');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_bookings', function (Blueprint $table) {
            $table->dropIndex('reservation_bookings_station_idx');
            $table->dropConstrainedForeignId('pickup_station_id');
        });

        Schema::dropIfExists('reservation_event_station_slots');
        Schema::dropIfExists('reservation_event_stations');
        Schema::dropIfExists('reservation_pickup_stations');
    }
};
