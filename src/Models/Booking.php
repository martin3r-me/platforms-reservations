<?php

namespace Platform\Reservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Platform\Reservation\Models\Concerns\BelongsToTeam;
use Symfony\Component\Uid\UuidV7;

class Booking extends Model
{
    use BelongsToTeam;

    protected $table = 'reservation_bookings';

    // Mögliche Status-Werte
    const STATUS_PENDING   = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_NO_SHOW   = 'no_show';
    const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'uuid',
        'team_id',
        'order_id',
        'table_id',
        'event_id',
        'event_slot_id',
        'guest_name',
        'guest_email',
        'guest_phone',
        'guest_count',
        'notes',
        'date',
        'time_start',
        'time_end',
        'status',
        'age_check_confirmed_at',
        'legal_accepted_at',
        'payment_method',
        'mollie_payment_id',
    ];

    protected $casts = [
        'date'                   => 'date',
        'guest_count'            => 'integer',
        'age_check_confirmed_at' => 'datetime',
        'legal_accepted_at'      => 'datetime',
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

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class, 'table_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(EventSlot::class, 'event_slot_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class, 'booking_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Zahlung der Buchung – hängt seit der Order-Klammer an der Order.
     * Accessor, damit bestehende Lesestellen ($booking->payment) weiter
     * funktionieren; für Eager-Loading 'order.payment' verwenden.
     */
    public function getPaymentAttribute(): ?Payment
    {
        return $this->order?->payment;
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeForDate($query, string $date)
    {
        return $query->whereDate('date', $date);
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [self::STATUS_CANCELLED, self::STATUS_NO_SHOW]);
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function getTotalAmountAttribute(): float
    {
        return $this->items->sum(fn ($item) => $item->unit_price * $item->quantity);
    }

    /** Enthält die Buchung alkoholische Artikel (→ 18+-Check erforderlich)? */
    public function getRequiresAgeCheckAttribute(): bool
    {
        return $this->items()
            ->whereHas('menuItem', fn ($q) => $q->where('is_alcoholic', true))
            ->exists();
    }
}
