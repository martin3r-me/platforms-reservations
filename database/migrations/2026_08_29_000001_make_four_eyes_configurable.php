<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vier-Augen-Prinzip bei der Artikelfreigabe pro Team einstellbar.
 *
 * Der heikle Teil ist nicht der Schalter, sondern das Abschalten: Wer eine
 * Kontrolle allein abschalten kann, hat sie nicht – dann wäre der Schalter der
 * bequemste Weg, den eigenen Artikel durchzuwinken. Deshalb ist das Abschalten
 * selbst ein Vier-Augen-Vorgang: einer beantragt, ein ZWEITER bestätigt.
 * Einschalten dagegen wirkt sofort – strenger werden darf jeder allein.
 *
 * Wer beantragt und wer bestätigt hat, bleibt stehen: In den Einstellungen soll
 * dauerhaft lesbar sein, wer die Pflicht wann gelockert hat.
 *
 * submitted_at am Artikel schließt die letzte Lücke: Artikel, die beim
 * Abschalten schon zur Prüfung standen, brauchen weiterhin zwei Personen. Sonst
 * bliebe das Abschalten ein Umweg um genau die Prüfung, die schon lief.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_checkout_settings', function (Blueprint $table) {
            $table->boolean('four_eyes_enabled')->default(true)->after('max_group_empty_table');

            // Offener Antrag auf Abschalten (wartet auf den zweiten Menschen).
            $table->unsignedBigInteger('four_eyes_off_requested_by')->nullable()->after('four_eyes_enabled');
            $table->timestamp('four_eyes_off_requested_at')->nullable()->after('four_eyes_off_requested_by');

            // Beleg des letzten vollzogenen Wechsels – beim Abschalten beide
            // Beteiligten, beim Einschalten nur der, der es getan hat.
            $table->unsignedBigInteger('four_eyes_changed_by')->nullable()->after('four_eyes_off_requested_at');
            $table->unsignedBigInteger('four_eyes_changed_with')->nullable()->after('four_eyes_changed_by');
            $table->timestamp('four_eyes_changed_at')->nullable()->after('four_eyes_changed_with');
        });

        Schema::table('reservation_menu_items', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('submitted_by');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_menu_items', function (Blueprint $table) {
            $table->dropColumn('submitted_at');
        });

        Schema::table('reservation_checkout_settings', function (Blueprint $table) {
            $table->dropColumn([
                'four_eyes_enabled',
                'four_eyes_off_requested_by',
                'four_eyes_off_requested_at',
                'four_eyes_changed_by',
                'four_eyes_changed_with',
                'four_eyes_changed_at',
            ]);
        });
    }
};
