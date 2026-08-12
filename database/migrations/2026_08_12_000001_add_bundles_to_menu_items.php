<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bundles ("Brezel + Bier zusammen günstiger").
 *
 * Ein Bundle ist ein MenuItem mit is_bundle = true und Bestandteilen. Es ist
 * ein VERKAUFSOBJEKT, aber keine Bestellposition: beim Bestellen zerfällt es in
 * seine Bestandteile, damit MwSt je Satz, Allergene, Alterscheck und die
 * Standzeit-Klassen der Laufrunde unverändert weiterfunktionieren.
 *
 * bundle_ref gruppiert die Positionen einer Bundle-Instanz, damit der Beleg sie
 * unter einer Überschrift zeigen und ein Storno nur ganze Bundles erfassen kann.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_menu_items', function (Blueprint $table) {
            $table->boolean('is_bundle')->default(false)->after('category_id');
            $table->index(['team_id', 'is_bundle']);
        });

        Schema::create('reservation_menu_item_components', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bundle_id')
                ->constrained('reservation_menu_items')
                ->cascadeOnDelete();

            // Wird ein Bestandteil gelöscht, darf das Bundle nicht stillschweigend
            // seinen Inhalt verlieren – Löschen wird stattdessen blockiert.
            $table->foreignId('component_id')
                ->constrained('reservation_menu_items')
                ->restrictOnDelete();

            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['bundle_id', 'component_id']);
            $table->index('component_id');
        });

        Schema::table('reservation_booking_items', function (Blueprint $table) {
            // Gruppiert die Positionen EINER Bundle-Instanz.
            $table->uuid('bundle_ref')->nullable()->after('menu_item_id');

            // Aus welchem Bundle die Position stammt (für Beleg-Überschrift und
            // Auswertung "wie oft verkauft").
            $table->unsignedBigInteger('bundle_menu_item_id')->nullable()->after('bundle_ref');

            $table->index('bundle_ref');
            $table->index('bundle_menu_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_booking_items', function (Blueprint $table) {
            $table->dropIndex(['bundle_ref']);
            $table->dropIndex(['bundle_menu_item_id']);
            $table->dropColumn(['bundle_ref', 'bundle_menu_item_id']);
        });

        Schema::dropIfExists('reservation_menu_item_components');

        Schema::table('reservation_menu_items', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'is_bundle']);
            $table->dropColumn('is_bundle');
        });
    }
};
