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
 *
 * Der Schwellwert eines Raums entscheidet über den NÄCHSTEN: fill_threshold_percent
 * am ersten Raum sagt, ab wann der zweite öffnet. Am letzten Raum bewirkt er nichts,
 * weil keiner mehr folgt.
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

            // Ein von Hand GESCHLOSSENER Raum reicht durch, statt die Kette
            // anzuhalten.
            //
            // Sonst passierte Folgendes: Er bekommt keine Buchungen, erreicht
            // also nie seinen Schwellwert - und der Raum DAHINTER öffnet nie.
            // Bei drei Räumen und dem mittleren auf "geschlossen" bliebe der
            // dritte für immer zu, ohne dass irgendwo stünde warum. Wer einen
            // Raum zumacht, will ihn überspringen, nicht die Reihenfolge
            // stilllegen.
            if ($room->is_open_override === false) {
                continue;
            }

            // Füllung dieses Raums entscheidet über die Öffnung des nächsten
            $total = $room->totalSeats();
            $booked = $this->seats->bookedSeatsInRoom($room->floorPlan, $slot);
            $previousFull = $total > 0
                && ($booked / $total) * 100 >= $room->fill_threshold_percent;
        }

        return $open->values();
    }

    /**
     * Warum ein Raum zu ist – oder warum er entgegen der Reihenfolge offen ist.
     *
     * Ohne diesen Satz steht ein geschlossener Raum bei 0 Prozent da, und
     * niemand weiß, ob dort nichts gebucht wurde oder ob dort nichts gebucht
     * werden KONNTE. Das sind zwei sehr verschiedene Abende.
     *
     * Hier und nicht in der Ansicht, weil ihn zwei brauchen: das VA-Dashboard
     * und die Gast-API. Zwei Fassungen desselben Satzes liefen auseinander,
     * und dann liest der Gast etwas anderes als das Haus.
     *
     * Gibt null zurück, wenn es nichts zu sagen gibt – der Normalfall bei
     * parallelen Räumen ohne Handeingriff.
     */
    public function hinweis(?EventRoom $raum, ?EventRoom $vorgaenger, bool $offen): ?string
    {
        if (! $raum) {
            return null;
        }

        if (! $offen) {
            if ($raum->is_open_override === false) {
                return 'von Hand geschlossen';
            }

            return $vorgaenger
                ? 'öffnet, sobald ' . ($vorgaenger->floorPlan?->name ?? 'der Raum davor')
                    . ' zu ' . (int) $vorgaenger->fill_threshold_percent . ' % gefüllt ist'
                : 'geschlossen';
        }

        return $raum->is_open_override === true ? 'von Hand geöffnet' : null;
    }
}
