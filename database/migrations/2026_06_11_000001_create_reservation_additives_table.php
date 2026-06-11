<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_additives', function (Blueprint $table) {
            $table->id();
            $table->string('name');                 // z.B. "Farbstoff", "Konservierungsstoff"
            $table->string('code')->unique();       // Standardisierte Legende (Nummern "1"–"x")
            $table->string('icon')->nullable();     // Icon-Key oder Emoji
            $table->timestamps();
        });

        Schema::create('reservation_menu_item_additive', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained('reservation_menu_items')->cascadeOnDelete();
            $table->foreignId('additive_id')->constrained('reservation_additives')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['menu_item_id', 'additive_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_menu_item_additive');
        Schema::dropIfExists('reservation_additives');
    }
};
