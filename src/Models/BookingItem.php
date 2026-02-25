<?php

namespace Platform\Reservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingItem extends Model
{
    protected $table = 'reservation_booking_items';

    protected $fillable = [
        'booking_id',
        'menu_item_id',
        'quantity',
        'unit_price',
        'tax_rate',
        'notes',
    ];

    protected $casts = [
        'quantity'   => 'integer',
        'unit_price' => 'decimal:2',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    public function getLineTotalAttribute(): float
    {
        return (float) $this->unit_price * $this->quantity;
    }
}
