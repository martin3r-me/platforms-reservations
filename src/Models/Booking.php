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

        // Automatischer Bon-Druck beim Wechsel auf "bestätigt".
        //
        // Bewusst am Statuswechsel statt an einer Stelle im Ablauf: Bestätigt
        // wird nach der Zahlung, von Hand in der Buchungsliste und über die
        // Freigabe im Posteingang. wasChanged() liefert nur bei einem echten
        // Wechsel true, ein erneutes Speichern druckt also nicht noch einmal.
        static::updated(function (self $model) {
            if ($model->status !== self::STATUS_CONFIRMED) {
                return;
            }

            if (! $model->wasChanged('status')) {
                return;
            }

            app(\Platform\Reservation\Services\AutoPrintService::class)->printBooking($model);
        });

        // Falls eine Buchung je direkt als bestätigt angelegt wird. Heute tut
        // das kein Weg – Gast-Checkout und Backoffice legen beide "pending" an –,
        // aber die Regel soll lauten: eine bestätigte Buchung wird einmal
        // gedruckt, nicht nur eine, die vorher pending war.
        static::created(function (self $model) {
            if ($model->status !== self::STATUS_CONFIRMED) {
                return;
            }

            app(\Platform\Reservation\Services\AutoPrintService::class)->printBooking($model);
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
