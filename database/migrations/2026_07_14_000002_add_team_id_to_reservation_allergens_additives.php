<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allergene und Zusatzstoffe werden team-bezogen pflegbar. Bestehende
 * globale Einträge (team_id NULL) bleiben erhalten; die pro-Team-Stammliste
 * wird beim ersten Öffnen der Einstellungen befüllt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_allergens', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('id')
                ->constrained('teams')->cascadeOnDelete();
            $table->index(['team_id', 'code']);
        });

        Schema::table('reservation_additives', function (Blueprint $table) {
            // Globale Unique-Constraint auf code entfernen (Codes wiederholen sich je Team).
            $table->dropUnique('reservation_additives_code_unique');
        });

        Schema::table('reservation_additives', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('id')
                ->constrained('teams')->cascadeOnDelete();
            $table->unique(['team_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('reservation_allergens', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'code']);
            $table->dropConstrainedForeignId('team_id');
        });

        Schema::table('reservation_additives', function (Blueprint $table) {
            $table->dropUnique(['team_id', 'code']);
            $table->dropConstrainedForeignId('team_id');
            $table->unique('code');
        });
    }
};
