<?php

namespace Platform\Reservation\Services;

use Illuminate\Support\Collection;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Models\EventRoom;
use Platform\Reservation\Models\EventSlot;

/**
 * Raumfreigabe-Logik:
 * - parallel: alle Räume eines Termins sind offen.
 * - sequential: Raum n+1 öffnet, sobald Raum n zu >= fill_threshold_percent
 *   gefüllt ist (gezählt in Plätzen).
 * - is_open_override (manuelles Auf/Zu durch Admin) schlägt die Logik.
 */
class RoomReleaseService
{
    public function __construct(
        protected SeatAvailabilityService $seats,
    ) {
    }

    /** @return Collection<int, EventRoom> offene Räume in sort_order-Reihenfolge */
    public function openRooms(Event $event, EventSlot $slot): Collection
    {
        $rooms = $event->eventRooms()->with('floorPlan.tables')->get();

        if ($event->room_release_mode !== Event::RELEASE_SEQUENTIAL) {
            return $rooms->filter(fn (EventRoom $room) => $room->is_open_override ?? true)->values();
        }

        $open = collect();
        $previousFull = true; // erster Raum ist immer offen

        foreach ($rooms as $room) {
            $isOpen = $room->is_open_override ?? $previousFull;

            if ($isOpen) {
                $open->push($room);
            }

            // Füllung dieses Raums entscheidet über die Öffnung des nächsten
            $total = $room->totalSeats();
            $booked = $this->seats->bookedSeatsInRoom($room->floorPlan, $slot);
            $previousFull = $total > 0
                && ($booked / $total) * 100 >= $room->fill_threshold_percent;
        }

        return $open->values();
    }
}
