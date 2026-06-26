<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_payment_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->unique()->constrained('teams')->cascadeOnDelete();
            $table->string('provider')->default('mollie');
            $table->boolean('enabled')->default(false);
            $table->string('mode')->default('test'); // test | live

            // API-Keys werden im Model verschlüsselt gespeichert (Cast 'encrypted').
            $table->text('test_api_key')->nullable();
            $table->text('live_api_key')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_payment_settings');
    }
};
