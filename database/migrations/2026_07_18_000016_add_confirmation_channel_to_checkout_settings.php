<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Absender (CRM-Comms-Channel) für Bestellbestätigungen. Bewusst OHNE Default:
 * ist kein Channel gewählt, wird keine Mail versendet (kein zufälliger Absender).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_checkout_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('confirmation_channel_id')->nullable()->after('guest_frontend_url');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_checkout_settings', function (Blueprint $table) {
            $table->dropColumn('confirmation_channel_id');
        });
    }
};
