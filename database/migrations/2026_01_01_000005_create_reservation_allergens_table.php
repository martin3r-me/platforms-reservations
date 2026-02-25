<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_allergens', function (Blueprint $table) {
            $table->id();
            $table->string('name');       // z.B. "Gluten", "Laktose"
            $table->string('code')->nullable(); // EU-Kennzeichnung (A–T)
            $table->string('icon')->nullable(); // Icon-Key oder Emoji
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_allergens');
    }
};
