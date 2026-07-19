<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Basis-URL des externen Shop-Frontends (z. B. https://culinaria.pauseplus.de).
 * Dient als Allowlist-Origin: nur redirect_url mit diesem Origin darf als
 * Mollie-Rücksprung genutzt werden (Open-Redirect-Schutz).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_checkout_settings', function (Blueprint $table) {
            $table->string('guest_frontend_url')->nullable()->after('languages');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_checkout_settings', function (Blueprint $table) {
            $table->dropColumn('guest_frontend_url');
        });
    }
};
