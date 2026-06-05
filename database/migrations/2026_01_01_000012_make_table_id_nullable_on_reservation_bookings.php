<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite does not support ALTER COLUMN directly.
        // Recreate the table with table_id nullable.
        Schema::table('reservation_bookings', function (Blueprint $table) {
            $table->foreignId('table_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('reservation_bookings', function (Blueprint $table) {
            $table->foreignId('table_id')->nullable(false)->change();
        });
    }
};
