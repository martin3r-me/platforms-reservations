<?php

namespace Platform\Reservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Raum-Zuordnung eines Termins (Event ↔ FloorPlan) inkl. Freigabe-Konfiguration
 * für sequentielle Raumöffnung.
 */
class EventRoom extends Model
{
    protected $table = 'reservation_event_rooms';

    protected $fillable = [
        'event_id',
        'floor_plan_id',
        'sort_order',
        'fill_threshold_percent',
        'capacity_override',
        'is_open_override',
    ];

    protected $casts = [
        'sort_order'             => 'integer',
        'fill_threshold_percent' => 'integer',
        'capacity_override'      => 'integer',
        'is_open_override'       => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function floorPlan(): BelongsTo
    {
        return $this->belongsTo(FloorPlan::class, 'floor_plan_id');
    }

    /** Gesamtkapazität in Plätzen (Override oder Summe der Tisch-Kapazitäten). */
    public function totalSeats(): int
    {
        if ($this->capacity_override !== null) {
            return $this->capacity_override;
        }

        return (int) $this->floorPlan->tables()->where('is_active', true)->sum('capacity');
    }
}
