<?php

namespace Platform\Reservation\Services;

use Illuminate\Support\Collection;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\EventSlot;
use Platform\Reservation\Models\FloorPlan;
use Platform\Reservation\Models\Table;

/**
 * Platzgenaue Verfügbarkeit: Tische sind teilbar – mehrere Buchungen pro
 * Tisch, bis die Kapazität (Plätze) erreicht ist. Gezählt wird pro
 * Pausen-Slot. (Das tischweise Table::isAvailableOn() bleibt für den
 * Admin-Alt-Flow ohne Event bestehen.)
 *
 * M1 ohne Locking – Concurrency-Härtung (zwei Gäste buchen gleichzeitig
 * die letzten Plätze) folgt in M2.
 */
class SeatAvailabilityService
{
    public const STATUS_FREE    = 'free';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_FULL    = 'full';

    /** Belegte Plätze je Tisch-ID für einen Slot (ein Query pro Plan). */
    public function bookedSeatsByTable(FloorPlan $floorPlan, EventSlot $slot): Collection
    {
        return Booking::query()
            ->where('event_slot_id', $slot->id)
            ->whereIn('table_id', $floorPlan->tables()->pluck('id'))
            ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW])
            ->groupBy('table_id')
            ->selectRaw('table_id, SUM(guest_count) as seats')
            ->pluck('seats', 'table_id')
            ->map(fn ($seats) => (int) $seats);
    }

    public function remainingSeats(Table $table, EventSlot $slot): int
    {
        $booked = (int) Booking::query()
            ->where('event_slot_id', $slot->id)
            ->where('table_id', $table->id)
            ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW])
            ->sum('guest_count');

        return max(0, $table->capacity - $booked);
    }

    /** free | partial | full – für die Färbung im Tischplan. */
    public function tableStatus(Table $table, int $bookedSeats): string
    {
        if ($bookedSeats <= 0) {
            return self::STATUS_FREE;
        }

        return $bookedSeats >= $table->capacity ? self::STATUS_FULL : self::STATUS_PARTIAL;
    }

    /** Gebuchte Plätze im gesamten Raum für einen Slot. */
    public function bookedSeatsInRoom(FloorPlan $floorPlan, EventSlot $slot): int
    {
        return (int) $this->bookedSeatsByTable($floorPlan, $slot)->sum();
    }
}
