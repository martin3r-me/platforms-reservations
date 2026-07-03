<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_checkout_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->unique()->constrained('teams')->cascadeOnDelete();
            $table->text('age_check_text')->nullable();   // 18+-Hinweis (nur bei Alkohol)
            $table->text('legal_text')->nullable();        // Pflicht-Bestätigung im Checkout
            $table->string('privacy_url')->nullable();     // Link zur Datenschutzerklärung
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_checkout_settings');
    }
};
