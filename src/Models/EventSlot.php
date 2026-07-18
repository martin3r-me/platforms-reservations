<?php

namespace Platform\Reservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pausen-Slot eines Termins (z.B. "Pause 1", 20:15–20:35).
 * Buchungen erben Datum vom Event und Uhrzeit vom Slot.
 *
 * Die Zeiten (time_start/time_end) sind optional (#518): Ein Slot kann als
 * reine Pause ohne konkrete Uhrzeit geführt werden.
 */
class EventSlot extends Model
{
    protected $table = 'reservation_event_slots';

    protected $fillable = [
        'event_id',
        'name',
        'time_start',
        'time_end',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'event_slot_id');
    }

    /**
     * Formatierte Zeitspanne oder null, wenn keine Startzeit hinterlegt ist.
     * Beispiele: "20:15–20:35", "20:15", null.
     */
    public function getTimeRangeAttribute(): ?string
    {
        if (! $this->time_start) {
            return null;
        }

        $start = substr((string) $this->time_start, 0, 5);
        $end   = $this->time_end ? substr((string) $this->time_end, 0, 5) : null;

        return $end ? "{$start}–{$end}" : $start;
    }

    /**
     * Anzeige-Label: Name plus optionale Zeit ("Pause 1 · 20:15 Uhr" bzw. nur "Pause 1").
     */
    public function displayLabel(): string
    {
        return $this->time_range ? "{$this->name} · {$this->time_range} Uhr" : $this->name;
    }
}
