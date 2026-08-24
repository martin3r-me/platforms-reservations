<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Angaben für den DATEV-Export (Buchungsstapel).
 *
 * Sie stehen bewusst in den Einstellungen und nicht im Code: Kontonummern und
 * Berater-/Mandantennummern sind je Mandant verschieden, und der Beginn des
 * Wirtschaftsjahres steht im Kopf jeder Datei. Im Code wäre das ein Fall für
 * den nächsten Januar.
 *
 * Der Beginn des Wirtschaftsjahres wird als TAG UND MONAT abgelegt ("01-01"),
 * nicht als volles Datum. Das Jahr ergibt sich beim Export aus dem gewählten
 * Zeitraum – so muss zum Jahreswechsel niemand daran denken.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_checkout_settings', function (Blueprint $table) {
            $table->string('datev_berater', 7)->nullable()->after('auto_print_printer_group_id');
            $table->string('datev_mandant', 5)->nullable()->after('datev_berater');

            // 4 oder 5 – steht so im Kopf der Datei und muss zur Kanzlei passen.
            $table->unsignedTinyInteger('datev_sachkontenlaenge')->default(4)->after('datev_mandant');

            // Tag und Monat, z.B. "01-01". Siehe Kommentar oben.
            $table->string('datev_wj_beginn', 5)->default('01-01')->after('datev_sachkontenlaenge');

            $table->string('datev_erloes_7', 9)->nullable()->after('datev_wj_beginn');
            $table->string('datev_erloes_19', 9)->nullable()->after('datev_erloes_7');

            // Geld- oder Verrechnungskonto, auf dem die Zahlungen eingehen.
            $table->string('datev_geldkonto', 9)->nullable()->after('datev_erloes_19');

            // einzel = ein Satz je Buchung und Steuersatz; tagessumme = ein Satz
            // je Tag und Steuersatz. Kanzleien wollen oft das Zweite.
            $table->string('datev_modus', 12)->default('einzel')->after('datev_geldkonto');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_checkout_settings', function (Blueprint $table) {
            $table->dropColumn([
                'datev_berater', 'datev_mandant', 'datev_sachkontenlaenge', 'datev_wj_beginn',
                'datev_erloes_7', 'datev_erloes_19', 'datev_geldkonto', 'datev_modus',
            ]);
        });
    }
};
