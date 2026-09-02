<?php

namespace Platform\Reservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Eine Abholstation, einem Termin zugeordnet – der Zwilling von EventRoom.
 *
 * Ohne eigenen Team-Scope: Wie EventRoom, EventSlot und Table erbt sie die
 * Trennung von ihrer Elternressource, dem Termin.
 *
 * `capacity_override` schlägt `capacity_per_slot` der Station – dieselbe
 * Beziehung wie zwischen Termin und Team-Vorgabe an anderen Stellen: Der
 * speziellere Wert gewinnt, und null heißt „dem allgemeineren folgen".
 */
class EventStation extends Model
{
    protected $table = 'reservation_event_stations';

    protected $fillable = [
        'event_id',
        'pickup_station_id',
        'sort_order',
        'capacity_override',
    ];

    protected $casts = [
        'sort_order'        => 'integer',
        'capacity_override' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(PickupStation::class, 'pickup_station_id');
    }

    /**
     * In welchen Pausen die Station offen ist.
     *
     * Immer ausdrücklich, nie „leer heißt alle": Sonst hätte eine fehlende
     * Zeile zwei Bedeutungen, und die Verwechslung fällt genau dann auf, wenn
     * abends eine Station stumm verschwindet.
     */
    public function slots(): BelongsToMany
    {
        return $this->belongsToMany(
            EventSlot::class,
            'reservation_event_station_slots',
            'event_station_id',
            'event_slot_id',
        )->withTimestamps();
    }

    /** Ist diese Station in DIESER Pause geöffnet? */
    public function offenIn(int $slotId): bool
    {
        return $this->slots->contains('id', $slotId);
    }

    /** Die geltende Obergrenze je Pause – oder null für unbegrenzt. */
    public function grenzeJePause(): ?int
    {
        return $this->capacity_override ?? $this->station?->capacity_per_slot;
    }
}
