<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Der wievielte Schritt von wie vielen.
 *
 * Der Name allein sagt nicht, wie weit jemand ist: „Sitzplatz" ist bei einem
 * Termin mit einer Pause der zweite von vier Schritten, bei zweien der dritte
 * von fünf.
 *
 * Beide Zahlen kommen vom SHOP und werden nicht im Office gerechnet. Das ist
 * der Kern: Welche Schritte es gibt, entscheidet der Bestellweg zur Laufzeit
 * (CheckoutWizard::schritte() – „Wann?" fehlt bei einer Pause, später kommt
 * „Wo?" für die Abholstationen dazu). Rechnete das Office die Zahl aus der
 * Pausenzahl selbst nach, wäre das eine zweite Fassung derselben Regel, und
 * die erste Änderung im Shop ließe sie auseinanderlaufen – lautlos, denn eine
 * falsche Schrittzahl sieht nicht nach einem Fehler aus.
 *
 * Gezählt wird, was der GAST sieht: seine Stepper-Kreise. Der Intro-Schritt
 * mit der Personenzahl hat dort keinen Kreis und bekommt deshalb keine Nummer
 * (step_no bleibt null) – sonst nennt das Office eine Zahl, die auf dem
 * Bildschirm des Gastes nirgends steht.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_checkout_sessions', function (Blueprint $table) {
            $table->unsignedTinyInteger('step_no')->nullable()->after('step');
            $table->unsignedTinyInteger('step_count')->nullable()->after('step_no');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_checkout_sessions', function (Blueprint $table) {
            $table->dropColumn(['step_no', 'step_count']);
        });
    }
};
