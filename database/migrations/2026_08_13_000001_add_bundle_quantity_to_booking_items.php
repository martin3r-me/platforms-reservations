<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wie viele Bundles wurden bestellt?
 *
 * bundle_ref gruppiert die Positionen einer Bundle-Zeile, zählt sie aber nicht:
 * Bei "3× Brezel + Bier" gibt es EINE Referenz, nicht drei. Die Zahl 3 stand
 * bisher nirgends.
 *
 * Ableiten liesse sie sich aus den Bestandteilen (Summe der Mengen geteilt durch
 * die Menge im Bundle), aber nur solange niemand das Bundle nachträglich ändert.
 * Wird aus "1× Bier" später "2× Bier", ergibt dieselbe alte Bestellung plötzlich
 * "1,5× Bundle" – ein bereits ausgestellter Beleg würde rückwirkend falsch.
 *
 * Deshalb wird die Menge beim Bestellen eingefroren, aus demselben Grund wie
 * Preis und Steuersatz: Was auf dem Beleg steht, darf sich nicht mehr ändern.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_booking_items', function (Blueprint $table) {
            // Nur bei Bundle-Positionen gesetzt; bei Einzelartikeln null.
            $table->unsignedInteger('bundle_quantity')->nullable()->after('bundle_menu_item_id');
        });

        $this->backfill();
    }

    /**
     * Bestehende Bundle-Bestellungen nachtragen.
     *
     * Hier ist das Ableiten vertretbar: Zum Migrationszeitpunkt entspricht die
     * Zusammensetzung noch der, mit der bestellt wurde – später gilt das nicht
     * mehr. Genau deshalb geschieht es einmalig hier und nicht bei jeder Anzeige.
     *
     * Wo sich nichts ermitteln lässt, bleibt die Spalte null; die Anzeige fällt
     * dann auf 1 zurück.
     */
    protected function backfill(): void
    {
        $refs = DB::table('reservation_booking_items')
            ->whereNotNull('bundle_ref')
            ->select('bundle_ref', 'bundle_menu_item_id')
            ->distinct()
            ->get();

        foreach ($refs as $ref) {
            if (! $ref->bundle_menu_item_id) {
                continue;
            }

            // Mengen je Bestandteil in dieser Bundle-Zeile. Ein Bestandteil kann
            // über mehrere Positionen verteilt sein (der Preis-Verteiler splittet,
            // damit Menge × Einzelpreis exakt aufgeht), daher die Summe.
            $summen = DB::table('reservation_booking_items')
                ->where('bundle_ref', $ref->bundle_ref)
                ->groupBy('menu_item_id')
                ->select('menu_item_id', DB::raw('SUM(quantity) as gesamt'))
                ->pluck('gesamt', 'menu_item_id');

            $anteile = DB::table('reservation_menu_item_components')
                ->where('bundle_id', $ref->bundle_menu_item_id)
                ->pluck('quantity', 'component_id');

            $menge = null;

            foreach ($summen as $itemId => $gesamt) {
                $proBundle = (int) ($anteile[$itemId] ?? 0);

                if ($proBundle < 1 || (int) $gesamt % $proBundle !== 0) {
                    $menge = null;   // geht nicht auf – lieber nichts eintragen
                    break;
                }

                $kandidat = intdiv((int) $gesamt, $proBundle);

                if ($menge !== null && $menge !== $kandidat) {
                    $menge = null;   // Bestandteile widersprechen sich
                    break;
                }

                $menge = $kandidat;
            }

            if ($menge !== null && $menge > 0) {
                DB::table('reservation_booking_items')
                    ->where('bundle_ref', $ref->bundle_ref)
                    ->update(['bundle_quantity' => $menge]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('reservation_booking_items', function (Blueprint $table) {
            $table->dropColumn('bundle_quantity');
        });
    }
};
