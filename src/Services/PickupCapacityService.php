<?php

namespace Platform\Reservation\Services;

use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\EventSlot;
use Platform\Reservation\Models\EventStation;

/**
 * Wie voll eine Abholstation in einer Pause ist.
 *
 * Der Zwilling von SeatAvailabilityService, aber deutlich einfacher – und das
 * ist Absicht, nicht Faulheit: An einer Station gibt es keine Tische, keine
 * weiche Kapazität, keine Großgruppen-Regel und nichts zu sperren. Gezählt
 * werden Gäste je Station UND Pause gegen eine optionale Obergrenze.
 *
 * **150 in Pause 1 und 150 in Pause 2 sind 300, nicht 150.** Anders als beim
 * Tisch gibt es hier keine Bindung über den Abend: Wer in der ersten Pause
 * abholt, belegt in der zweiten nichts.
 *
 * **Kein `lockForUpdate` – Entscheidung vom 28.08.2026.** Zwei gleichzeitige
 * Bestellungen können dieselbe letzte Kapazität nehmen; dann steht am Ende eine
 * Portion mehr auf der Liste, als die Obergrenze sagt. Beim Tisch wäre das
 * inzwischen anders gelöst, und der Unterschied hat einen Grund: Ein Tisch hat
 * physisch vier Stühle, eine Station hat eine Vorgabe. Der Bestellschluss liegt
 * Stunden vor der Pause, produziert wird danach und zwar das, was bestellt ist.
 * Die Obergrenze ist eine Bremse gegen Überlast, keine Zusage über vorhandene
 * Ware. Bei einer Ausgabe ohne Vorlauf wäre die Antwort eine andere.
 */
class PickupCapacityService
{
    /**
     * Bereits vergebene Gäste an dieser Station in dieser Pause.
     *
     * Gezählt wird nach derselben Regel wie am Tisch: ohne Stornos, ohne
     * No-Shows. Eine zweite Regel liefe früher oder später auseinander, und
     * dann stünde im Backoffice eine andere Zahl als beim Gast.
     */
    public function belegt(EventStation $station, EventSlot $slot): int
    {
        return (int) Booking::withoutGlobalScope('team')
            ->where('pickup_station_id', $station->pickup_station_id)
            ->where('event_slot_id', $slot->id)
            ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW])
            ->sum('guest_count');
    }

    /**
     * Noch frei – oder null, wenn es keine Obergrenze gibt.
     *
     * Null heißt ausdrücklich „unbegrenzt", nicht „null Plätze". Deshalb der
     * Rückgabetyp und nicht eine große Zahl: Wer hier eine Zahl bekommt, darf
     * sie anzeigen; wer null bekommt, soll gar nichts anzeigen.
     */
    public function frei(EventStation $station, EventSlot $slot): ?int
    {
        $grenze = $station->grenzeJePause();

        return $grenze === null ? null : max(0, $grenze - $this->belegt($station, $slot));
    }

    /**
     * Passt diese Gruppe noch an die Station?
     *
     * Ohne Obergrenze immer. Die Frage ist bewusst nicht „ist noch ein Platz
     * frei" – es gibt keine Plätze, nur eine Menge Gäste, die das Haus in einer
     * Pause bedienen kann.
     */
    public function passt(EventStation $station, EventSlot $slot, int $partySize): bool
    {
        $frei = $this->frei($station, $slot);

        return $frei === null || $partySize <= $frei;
    }

    /**
     * Ist die Station in dieser Pause überhaupt offen und aufnahmefähig?
     *
     * Zwei Fragen in einer, weil der Bestellweg sie zusammen stellt: Eine
     * Station, die in dieser Pause gar nicht geöffnet ist, ist kein Sonderfall
     * von „voll" – sie steht nicht zur Wahl.
     */
    public function buchbar(EventStation $station, EventSlot $slot, int $partySize): bool
    {
        if (! $station->station?->is_active) {
            return false;
        }

        if (! $station->offenIn($slot->id)) {
            return false;
        }

        return $this->passt($station, $slot, $partySize);
    }
}
