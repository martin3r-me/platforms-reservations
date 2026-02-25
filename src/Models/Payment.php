<?php

namespace Platform\Reservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $table = 'reservation_payments';

    protected $fillable = [
        'booking_id',
        'mollie_id',
        'amount',
        'currency',
        'status',
        'method',
        'paid_at',
        'refunded_at',
        'refunded_amount',
        'metadata',
    ];

    protected $casts = [
        'amount'          => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'paid_at'         => 'datetime',
        'refunded_at'     => 'datetime',
        'metadata'        => 'array',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isRefunded(): bool
    {
        return $this->refunded_at !== null;
    }
}
