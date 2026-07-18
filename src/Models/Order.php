<?php

namespace Platform\Reservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Platform\Reservation\Models\Concerns\BelongsToTeam;
use Symfony\Component\Uid\UuidV7;

/**
 * Bestell-Klammer: bündelt eine oder mehrere (Slot-)Buchungen unter EINER
 * Zahlung. Jede Pause bleibt eine eigene Booking (Slot/Raum/Tisch + Positionen);
 * die Order trägt die gemeinsame Zahlung.
 */
class Order extends Model
{
    use BelongsToTeam;

    public const STATUS_PENDING   = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'reservation_orders';

    protected $fillable = [
        'uuid',
        'team_id',
        'event_id',
        'status',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) UuidV7::generate();
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'order_id');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class, 'order_id');
    }

    /** Gesamtbetrag = Summe der eingefrorenen Positionen aller Buchungen. */
    public function getTotalAmountAttribute(): float
    {
        return (float) $this->bookings->sum(fn (Booking $booking) => $booking->total_amount);
    }
}
