<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schützt die Bestell- und Zahlungshistorie vor stillem Datenverlust.
 *
 * Bisher waren booking_items.menu_item_id und payments.booking_id als
 * cascadeOnDelete definiert: das Löschen eines Artikels hätte bezahlte
 * Bestellpositionen (mit eingefrorenem Preis/MwSt) mitgelöscht, das Löschen
 * einer Buchung den Zahlungs-/Refund-Nachweis. Beide werden auf
 * restrictOnDelete umgestellt – historische Belege bleiben erhalten, ein
 * bereits bestellter Artikel wird stattdessen deaktiviert statt gelöscht.
 *
 * booking_items.booking_id bleibt bewusst cascadeOnDelete (Positionen gehören
 * zur Buchung).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_booking_items', function (Blueprint $table) {
            $table->dropForeign(['menu_item_id']);
            $table->foreign('menu_item_id')
                ->references('id')->on('reservation_menu_items')
                ->restrictOnDelete();
        });

        Schema::table('reservation_payments', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->foreign('booking_id')
                ->references('id')->on('reservation_bookings')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reservation_booking_items', function (Blueprint $table) {
            $table->dropForeign(['menu_item_id']);
            $table->foreign('menu_item_id')
                ->references('id')->on('reservation_menu_items')
                ->cascadeOnDelete();
        });

        Schema::table('reservation_payments', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->foreign('booking_id')
                ->references('id')->on('reservation_bookings')
                ->cascadeOnDelete();
        });
    }
};
