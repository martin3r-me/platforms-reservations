<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Uid\UuidV7;

/**
 * Order-Klammer: bündelt mehrere (Slot-)Buchungen unter EINER Zahlung.
 *
 * Bisher galt hart 1 Buchung = 1 Zahlung (payments.booking_id). Für „mehrere
 * Pausen, eine Zahlung" wird die Order eingezogen: Booking bekommt order_id,
 * die Zahlung hängt an der Order (payments.order_id statt booking_id).
 *
 * Backfill: jede bestehende Buchung mit Zahlung wird in eine eigene
 * 1-Buchungs-Order gehüllt; danach entfällt payments.booking_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();          // öffentliche Referenz (Payment-Return)
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()
                ->constrained('reservation_events')->nullOnDelete();
            $table->string('status')->default('pending'); // pending|confirmed|cancelled
            $table->timestamps();

            $table->index(['team_id', 'status']);
        });

        Schema::table('reservation_bookings', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('team_id')
                ->constrained('reservation_orders')->nullOnDelete();
        });

        Schema::table('reservation_payments', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('id')
                ->constrained('reservation_orders')->restrictOnDelete();
        });

        // Backfill: bestehende Zahlungen an eine je Buchung erzeugte Order hängen.
        foreach (DB::table('reservation_payments')->get() as $payment) {
            $booking = DB::table('reservation_bookings')->where('id', $payment->booking_id)->first();
            if (!$booking) {
                continue;
            }

            $orderId = DB::table('reservation_orders')->insertGetId([
                'uuid'       => (string) UuidV7::generate(),
                'team_id'    => $booking->team_id,
                'event_id'   => $booking->event_id,
                'status'     => $booking->status,
                'created_at' => $booking->created_at,
                'updated_at' => $booking->updated_at,
            ]);

            DB::table('reservation_bookings')->where('id', $booking->id)->update(['order_id' => $orderId]);
            DB::table('reservation_payments')->where('id', $payment->id)->update(['order_id' => $orderId]);
        }

        // payments.booking_id entfällt – die Zahlung ist jetzt order-bezogen.
        // FK zuerst lösen (MySQL braucht den Index noch für den FK), dann Index/Spalte.
        Schema::table('reservation_payments', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->dropIndex(['booking_id', 'status']);
            $table->dropColumn('booking_id');
        });

        Schema::table('reservation_payments', function (Blueprint $table) {
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        // booking_id wieder anlegen (nullable, damit der Backfill greifen kann).
        Schema::table('reservation_payments', function (Blueprint $table) {
            $table->foreignId('booking_id')->nullable()->after('id')
                ->constrained('reservation_bookings')->cascadeOnDelete();
        });

        // Zahlung wieder an die (in 2a einzige) Buchung der Order hängen.
        foreach (DB::table('reservation_payments')->get() as $payment) {
            $booking = DB::table('reservation_bookings')->where('order_id', $payment->order_id)->first();
            if ($booking) {
                DB::table('reservation_payments')->where('id', $payment->id)->update(['booking_id' => $booking->id]);
            }
        }

        // order_id entfernen (FK vor Index vor Spalte), booking_id-Index wieder herstellen.
        Schema::table('reservation_payments', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropIndex(['order_id', 'status']);
            $table->dropColumn('order_id');
            $table->index(['booking_id', 'status']);
        });

        Schema::table('reservation_bookings', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropColumn('order_id');
        });

        Schema::dropIfExists('reservation_orders');
    }
};
