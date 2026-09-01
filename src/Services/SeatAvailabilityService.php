<?php

namespace Platform\Reservation\Services;

use Illuminate\Support\Collection;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Models\EventSlot;
use Platform\Reservation\Models\FloorPlan;
use Platform\Reservation\Models\Table;

/**
 * Platzgenaue Verfügbarkeit: Tische sind teilbar – mehrere Buchungen pro
 * Tisch, bis die Kapazität (Plätze) erreicht ist.
 *
 * Über WELCHE Pausen dabei gezählt wird, entscheidet die Tischbindung des
 * Termins (Event::tableBinding()):
 *
 *   slot  – je Pause getrennt. Ein Tisch ist in Pause 2 wieder frei, egal wer
 *           in Pause 1 daran saß.
 *   event – über alle Pausen des Termins. Wer einen Tisch hält, hält ihn den
 *           ganzen Abend – auch in Pausen, in denen er nichts bestellt hat.
 *
 * Bei Terminen mit nur einer Pause – dem Normalfall – liefern beide dieselben
 * Zahlen; die Unterscheidung ist dort ohne Wirkung.
 *
 * Diese Klasse ist die EINE Stelle, an der das gerechnet wird. RoomReleaseService
 * (Freigabe des nächsten Raums), das VA-Dashboard und die Gast-API rechnen über
 * ihre Methoden – eine zweite Fassung liefe unbemerkt auseinander, bis die
 * Zahlen sich widersprechen.
 *
 * M1 ohne Locking – Concurrency-Härtung (zwei Gäste buchen gleichzeitig
 * die letzten Plätze) folgt in M2.
 */
class SeatAvailabilityService
{
    public const STATUS_FREE    = 'free';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_FULL    = 'full';

    /**
     * Gemerkte Pausen-Mengen je Slot-ID.
     *
     * Termin und Betriebsart ändern sich innerhalb eines Requests nicht, die
     * Buchungen sehr wohl – deshalb wird hier NUR die Pausen-Menge gemerkt und
     * nie eine Belegung. Ein Zwischenspeicher für Zahlen wäre nach dem
     * Schreiben einer Buchung still falsch.
     *
     * @var array<int, array<int, int>>
     */
    protected array $pausenCache = [];

    /**
     * Über welche Pausen zählt dieser Slot?
     *
     * Bei Bindung an die Pause: nur er selbst. Bei Bindung an den Termin: alle
     * Pausen des Termins – dann ist ein belegter Tisch überall belegt.
     *
     * Ohne Team-Scope geladen: Im Gast-Weg läuft die API unter einem
     * Service-User, dessen Team nicht das des Termins ist. Der Scope würde den
     * Termin verschwinden lassen und die Menge stillschweigend auf die eine
     * Pause zusammenfallen – also genau die Verwechslung, die diese Methode
     * verhindern soll.
     *
     * @return array<int, int>
     */
    protected function pausenMenge(EventSlot $slot): array
    {
        if (isset($this->pausenCache[$slot->id])) {
            return $this->pausenCache[$slot->id];
        }

        $event = Event::withoutGlobalScope('team')->find($slot->event_id);

        $ids = [(int) $slot->id];

        if ($event && $event->tischGiltGanzenTermin()) {
            $alle = $event->slots()->pluck('id')->map(fn ($id) => (int) $id)->all();
            $ids  = $alle ?: $ids;
        }

        return $this->pausenCache[$slot->id] = $ids;
    }

    /**
     * Belegte Plätze je Tisch – die Zählung, auf der alles andere aufsetzt.
     *
     * Über mehrere Pausen darf NICHT summiert werden. Wer in beiden Pausen
     * bestellt, hat zwei Buchungen am selben Tisch; summiert würden aus zwei
     * Personen vier belegte Plätze, und der Gast wäre nach seiner eigenen
     * Bestellung selbst schuld am vollen Tisch.
     *
     * Gezählt wird deshalb nach PARTEIEN, jede einmal:
     *
     *  - Partei ist die Bestellung. bookings.order_id ist nullable (Altbestand
     *    von vor der Order-Klammer), deshalb gilt eine Buchung ohne Bestellung
     *    als eigene Partei.
     *  - Innerhalb einer Pause werden die Buchungen einer Partei summiert – so
     *    wie bisher auch.
     *  - Über die Pausen hinweg zählt die GRÖSSTE dieser Summen: Eine Partei,
     *    die zu viert in Pause 1 und zu zweit in Pause 2 sitzt, braucht vier
     *    Plätze. Der Tisch muss die größte Sitzung tragen.
     *
     * Bei genau einer Pause fällt das auf „Summe je Tisch" zusammen – das
     * bisherige Verhalten, unverändert.
     *
     * @param  array<int, int>  $tableIds
     * @param  array<int, int>  $slotIds
     * @return Collection<int, int> table_id => belegte Plätze
     */
    protected function belegungJeTisch(array $tableIds, array $slotIds): Collection
    {
        if ($tableIds === [] || $slotIds === []) {
            return collect();
        }

        $zeilen = Booking::withoutGlobalScope('team')
            ->whereIn('event_slot_id', $slotIds)
            ->whereIn('table_id', $tableIds)
            ->active()
            ->get(['id', 'order_id', 'table_id', 'event_slot_id', 'guest_count']);

        // Tisch -> Partei -> Pause -> Personen
        $verteilt = [];

        foreach ($zeilen as $zeile) {
            $partei = $zeile->order_id !== null ? 'o' . $zeile->order_id : 'b' . $zeile->id;
            $tisch  = (int) $zeile->table_id;
            $pause  = (int) $zeile->event_slot_id;

            $verteilt[$tisch][$partei][$pause] =
                ($verteilt[$tisch][$partei][$pause] ?? 0) + (int) $zeile->guest_count;
        }

        $summe = [];

        foreach ($verteilt as $tisch => $parteien) {
            $summe[$tisch] = 0;

            foreach ($parteien as $jePause) {
                $summe[$tisch] += max($jePause);
            }
        }

        return collect($summe);
    }

    /** Belegte Plätze je Tisch-ID für einen Slot (ein Query pro Plan). */
    public function bookedSeatsByTable(FloorPlan $floorPlan, EventSlot $slot): Collection
    {
        $tableIds = $floorPlan->tables()
            ->withoutGlobalScope('team')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $this->belegungJeTisch($tableIds, $this->pausenMenge($slot));
    }

    /** Bereits belegte Plätze eines Tisches in diesem Slot. */
    public function bookedSeatsForTable(Table $table, EventSlot $slot): int
    {
        return (int) $this->belegungJeTisch([(int) $table->id], $this->pausenMenge($slot))
            ->get((int) $table->id, 0);
    }

    public function remainingSeats(Table $table, EventSlot $slot): int
    {
        return max(0, $table->capacity - $this->bookedSeatsForTable($table, $slot));
    }

    /**
     * Passt eine Gruppe an diesen Tisch (in diesem Slot)?
     *
     * Normal: die Gruppe muss in die freien Plätze passen (Tische teilbar).
     * Weiche Kapazität ($softCapacity): zusätzlich darf eine Gruppe einen
     * KOMPLETT LEEREN Tisch über die Platzzahl hinaus belegen (Großgruppe →
     * leerer Tisch) – höchstens jedoch bis $maxGroupEmptyTable Personen
     * (null = unbegrenzt). Ein bereits (teil-)belegter Tisch bleibt für zu
     * große Gruppen gesperrt.
     *
     * „Leer" heißt bei Bindung an den Termin: in KEINER Pause belegt. Ein Tisch,
     * an dem in einer anderen Pause schon jemand sitzt, ist an diesem Abend
     * vergeben und steht für eine Großgruppe nicht mehr bereit.
     */
    public function canSeat(Table $table, EventSlot $slot, int $partySize, bool $softCapacity = false, ?int $maxGroupEmptyTable = null): bool
    {
        $booked    = $this->bookedSeatsForTable($table, $slot);
        $remaining = max(0, $table->capacity - $booked);

        if ($partySize <= $remaining) {
            return true;
        }

        return $softCapacity
            && $booked === 0
            && ($maxGroupEmptyTable === null || $partySize <= $maxGroupEmptyTable);
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
