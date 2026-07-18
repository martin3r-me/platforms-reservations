<?php

namespace Platform\Reservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Platform\Reservation\Models\Concerns\BelongsToTeam;
use Platform\Reservation\Models\Concerns\HasContextImage;
use Symfony\Component\Uid\UuidV7;

/**
 * Termin/Veranstaltung in PausePlus (z.B. "Bodo Wartke, 29.08.").
 *
 * Optional lose mit dem platforms-events-Modul verknüpfbar
 * (events_event_id/-uuid ohne FK) – standalone voll nutzbar.
 */
class Event extends Model
{
    use BelongsToTeam;
    use HasContextImage;

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_CLOSED    = 'closed';

    public const RELEASE_PARALLEL   = 'parallel';
    public const RELEASE_SEQUENTIAL = 'sequential';

    protected $table = 'reservation_events';

    protected $fillable = [
        'uuid',
        'team_id',
        'name',
        'description',
        'date',
        'order_deadline_at',
        'status',
        'venue_id',
        'sales_list_id',
        'room_release_mode',
        'disabled_table_ids',
        'image_context_file_id',
        'events_event_id',
        'events_event_uuid',
    ];

    protected $casts = [
        'date'               => 'date',
        'order_deadline_at'  => 'datetime',
        'disabled_table_ids' => 'array',
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

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class, 'venue_id');
    }

    public function salesList(): BelongsTo
    {
        return $this->belongsTo(SalesList::class, 'sales_list_id');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(EventSlot::class, 'event_id')->orderBy('sort_order');
    }

    public function eventRooms(): HasMany
    {
        return $this->hasMany(EventRoom::class, 'event_id')->orderBy('sort_order');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'event_id');
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeUpcoming($query)
    {
        return $query->whereDate('date', '>=', now()->toDateString());
    }

    /** Bestellschluss erreicht? (kein Deadline gesetzt = offen) */
    public function isOrderable(): bool
    {
        if ($this->status !== self::STATUS_PUBLISHED) {
            return false;
        }

        if ($this->order_deadline_at && now()->isAfter($this->order_deadline_at)) {
            return false;
        }

        return true;
    }

    /** Verkaufsliste für Gäste: Event-Liste, sonst Team-Default. */
    public function resolveSalesList(): ?SalesList
    {
        return $this->salesList ?? SalesList::defaultForTeam($this->team_id);
    }

    /** Ist dieser Tisch für den Termin gesperrt (nicht buchbar)? */
    public function isTableDisabled(int $tableId): bool
    {
        return in_array($tableId, $this->disabled_table_ids ?? [], true);
    }

    /**
     * Verknüpfte Veranstaltung aus dem platforms-events-Modul, sofern das
     * Modul installiert ist (lose Kopplung, kein harter Dependency).
     */
    public function linkedEventsEvent(): ?object
    {
        if (!$this->events_event_id || !class_exists(\Platform\Events\Models\Event::class)) {
            return null;
        }

        return \Platform\Events\Models\Event::find($this->events_event_id);
    }
}
