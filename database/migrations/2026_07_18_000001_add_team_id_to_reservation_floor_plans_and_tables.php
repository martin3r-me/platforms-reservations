<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalisiert team_id auf Tischpläne und Tische. Beide hängen bisher nur
 * indirekt am Team (floor_plan → venue → team_id bzw. table → floor_plan → …),
 * wodurch der globale Team-Scope (BelongsToTeam) nicht greifen konnte und
 * findOrFail() auf fremde IDs möglich war. Mit eigener team_id-Spalte gilt
 * dieselbe automatische Mandanten-Trennung wie bei den übrigen Models.
 *
 * Bestehende Zeilen werden aus der Eltern-Hierarchie befüllt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_floor_plans', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('venue_id')
                ->constrained('teams')->cascadeOnDelete();
            $table->index(['team_id', 'is_active']);
        });

        // Backfill aus dem zugehörigen Venue (portabel für MySQL & SQLite).
        DB::table('reservation_floor_plans')->update([
            'team_id' => DB::raw(
                '(SELECT team_id FROM reservation_venues'
                . ' WHERE reservation_venues.id = reservation_floor_plans.venue_id)'
            ),
        ]);

        Schema::table('reservation_tables', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('floor_plan_id')
                ->constrained('teams')->cascadeOnDelete();
            $table->index(['team_id', 'is_active']);
        });

        // Backfill aus dem zugehörigen Tischplan (nach dessen Backfill).
        DB::table('reservation_tables')->update([
            'team_id' => DB::raw(
                '(SELECT team_id FROM reservation_floor_plans'
                . ' WHERE reservation_floor_plans.id = reservation_tables.floor_plan_id)'
            ),
        ]);
    }

    public function down(): void
    {
        Schema::table('reservation_tables', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'is_active']);
            $table->dropConstrainedForeignId('team_id');
        });

        Schema::table('reservation_floor_plans', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'is_active']);
            $table->dropConstrainedForeignId('team_id');
        });
    }
};
