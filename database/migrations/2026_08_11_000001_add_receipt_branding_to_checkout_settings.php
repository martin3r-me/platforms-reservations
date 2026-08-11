<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Beleg-Branding je Mandant: Logo, Akzentfarbe, Fußzeile.
 *
 * Bewusst Bausteine statt einer fertigen Layout-Datei – die Vorlage bleibt
 * HTML/CSS und damit Vektor. Ein Briefbogen als PDF (FPDI) wäre eine spätere
 * Ausbaustufe und bräuchte zusätzlich konfigurierbare Inhaltsränder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_checkout_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('receipt_logo_context_file_id')->nullable()->after('issuer');
            $table->string('receipt_accent_color', 7)->nullable()->after('receipt_logo_context_file_id');
            $table->text('receipt_footer_text')->nullable()->after('receipt_accent_color');

            $table->index('receipt_logo_context_file_id');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_checkout_settings', function (Blueprint $table) {
            $table->dropIndex(['receipt_logo_context_file_id']);
            $table->dropColumn([
                'receipt_logo_context_file_id',
                'receipt_accent_color',
                'receipt_footer_text',
            ]);
        });
    }
};
