<?php

namespace Platform\Reservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Table extends Model
{
    protected $table = 'reservation_tables';

    protected $fillable = [
        'floor_plan_id',
        'label',
        'capacity',
        'x',
        'y',
        'width',
        'height',
        'shape',
        'color',
        'is_active',
    ];

    protected $casts = [
        'capacity'  => 'integer',
        'x'         => 'float',
        'y'         => 'float',
        'width'     => 'float',
        'height'    => 'float',
        'is_active' => 'boolean',
    ];

    public function floorPlan(): BelongsTo
    {
        return $this->belongsTo(FloorPlan::class, 'floor_plan_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'table_id');
    }

    /**
     * Gibt zurück, ob der Tisch an einem bestimmten Datum/Zeitraum verfügbar ist.
     */
    public function isAvailableOn(string $date, string $timeStart, ?string $timeEnd = null): bool
    {
        $query = $this->bookings()
            ->whereDate('date', $date)
            ->whereNotIn('status', ['cancelled', 'no_show']);

        if ($timeEnd) {
            $query->where(function ($q) use ($timeStart, $timeEnd) {
                $q->where('time_start', '<', $timeEnd)
                  ->where(function ($q2) use ($timeStart) {
                      $q2->whereNull('time_end')
                         ->orWhere('time_end', '>', $timeStart);
                  });
            });
        } else {
            $query->where('time_start', $timeStart);
        }

        return $query->count() === 0;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
