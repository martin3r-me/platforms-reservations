<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generische Übersetzungen (#522, Runde 1): polymorph an beliebige Modelle
 * (Speisen, Kategorien, Allergene/Zusatzstoffe, Checkout-Texte). Beliebige
 * Sprachen (locale = freier Code); DE bleibt in den Basis-Spalten (Default),
 * hier stehen nur die abweichenden Sprachen. Zusätzlich: pro Team die Liste
 * der angebotenen Sprachen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_translations', function (Blueprint $table) {
            $table->id();
            $table->string('translatable_type');
            $table->unsignedBigInteger('translatable_id');
            $table->string('locale', 10);           // z.B. "en", "fr", "en_US"
            $table->string('field', 64);            // z.B. "name", "description"
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['translatable_type', 'translatable_id', 'locale', 'field'], 'reservation_translations_unique');
            $table->index(['translatable_type', 'translatable_id']);
        });

        Schema::table('reservation_checkout_settings', function (Blueprint $table) {
            $table->json('languages')->nullable()->after('max_group_empty_table');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_translations');

        Schema::table('reservation_checkout_settings', function (Blueprint $table) {
            $table->dropColumn('languages');
        });
    }
};
