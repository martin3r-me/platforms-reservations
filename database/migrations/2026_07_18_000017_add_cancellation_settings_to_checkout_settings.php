<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Selbst-Storno durch den Kunden (#Storno): aktivierbar, mit Frist (Stunden vor
 * dem Veranstaltungsdatum) und optionaler Freigabe (Default: ohne Freigabe →
 * Klick = sofort Storno + Rückerstattung).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_checkout_settings', function (Blueprint $table) {
            $table->boolean('cancellation_enabled')->default(false)->after('confirmation_channel_id');
            $table->unsignedSmallInteger('cancellation_deadline_hours')->nullable()->after('cancellation_enabled');
            $table->boolean('cancellation_requires_approval')->default(false)->after('cancellation_deadline_hours');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_checkout_settings', function (Blueprint $table) {
            $table->dropColumn(['cancellation_enabled', 'cancellation_deadline_hours', 'cancellation_requires_approval']);
        });
    }
};
