<?php

namespace Platform\Reservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\Reservation\Models\Concerns\BelongsToTeam;

class DropoffSlot extends Model
{
    use BelongsToTeam;

    protected $table = 'reservation_dropoff_slots';

    protected $fillable = [
        'team_id',
        'date',
        'time_from',
        'time_to',
        'capacity',
        'booked_count',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'date'         => 'date',
        'capacity'     => 'integer',
        'booked_count' => 'integer',
        'is_active'    => 'boolean',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    public function getRemainingCapacityAttribute(): int
    {
        return max(0, $this->capacity - $this->booked_count);
    }

    public function isAvailable(): bool
    {
        return $this->is_active && $this->remaining_capacity > 0;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailable($query)
    {
        return $query->active()->whereColumn('booked_count', '<', 'capacity');
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeForDate($query, string $date)
    {
        return $query->whereDate('date', $date);
    }
}
