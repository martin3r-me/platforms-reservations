<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #523 – Standzeit-/Zeitkritikalitäts-Klassen: pro Team pflegbare Stammliste
 * (z. B. "Unbedenklich", "Sollte kalt sein", "Sollte heiß sein"), NICHT hart im
 * Code. Wird dem Artikel zugewiesen und steuert später die Laufrunden-Zuordnung
 * im Function Sheet. sort_order = Reihenfolge/Priorität der Platzierung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_holding_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color', 7)->nullable();  // Hex-Badge fürs Function Sheet
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['team_id', 'is_active', 'sort_order']);
        });

        Schema::table('reservation_menu_items', function (Blueprint $table) {
            $table->foreignId('holding_class_id')
                ->nullable()
                ->after('category_id')
                ->constrained('reservation_holding_classes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reservation_menu_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('holding_class_id');
        });

        Schema::dropIfExists('reservation_holding_classes');
    }
};
