<?php

namespace Platform\Reservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pausen-Slot eines Termins (z.B. "Pause 1", 20:15–20:35).
 * Buchungen erben Datum vom Event und Uhrzeit vom Slot.
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
}
