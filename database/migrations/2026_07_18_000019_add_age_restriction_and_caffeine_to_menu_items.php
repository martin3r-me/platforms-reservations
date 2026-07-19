<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Altersgrenze (16/18, nullable) und Koffein-Deklaration (Flag + Gehalt) am
 * Artikel. Altersgrenze steuert die Checkout-Altersbestätigung; Koffein ist eine
 * Kennzeichnung (LMIV-Warnhinweis bei erhöhtem Gehalt).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_menu_items', function (Blueprint $table) {
            $table->unsignedTinyInteger('min_age')->nullable()->after('is_alcoholic'); // 16 | 18 | null
            $table->boolean('is_caffeinated')->default(false)->after('min_age');
            $table->decimal('caffeine_mg', 6, 1)->nullable()->after('is_caffeinated'); // mg / 100 ml
        });
    }

    public function down(): void
    {
        Schema::table('reservation_menu_items', function (Blueprint $table) {
            $table->dropColumn(['min_age', 'is_caffeinated', 'caffeine_mg']);
        });
    }
};
