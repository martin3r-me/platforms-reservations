<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();                  // öffentliche Gast-Referenz
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();

            $table->string('name');                          // z.B. "Bodo Wartke"
            $table->text('description')->nullable();
            $table->date('date');
            $table->dateTime('order_deadline_at')->nullable(); // Bestellschluss

            // draft | published | closed – nur published erscheint auf der Übersichtsseite
            $table->string('status')->default('draft');

            $table->foreignId('venue_id')->nullable()
                ->constrained('reservation_venues')->nullOnDelete();
            $table->foreignId('sales_list_id')->nullable()
                ->constrained('reservation_sales_lists')->nullOnDelete();

            // parallel | sequential (Raum 2 öffnet nach Füllung von Raum 1)
            $table->string('room_release_mode')->default('parallel');

            // Hero-Bild (16:9) – ContextFile aus platform-core, bewusst ohne FK
            $table->unsignedBigInteger('image_context_file_id')->nullable();

            // Optionale lose Verknüpfung zum platforms-events-Modul (ohne FK,
            // Zugriff nur hinter class_exists-Guard)
            $table->unsignedBigInteger('events_event_id')->nullable()->index();
            $table->uuid('events_event_uuid')->nullable();

            $table->timestamps();

            $table->index(['team_id', 'date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_events');
    }
};
