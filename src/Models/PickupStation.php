<?php

namespace Platform\Reservation\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Platform\Reservation\Models\Concerns\BelongsToTeam;
use Platform\Reservation\Models\Concerns\HasPlanPosition;
use Platform\Reservation\Models\Concerns\HasTranslations;

/**
 * Eine Abholstation – „Foyer links", „Rang 1 Bar".
 *
 * Der zweite mögliche Zielort einer Buchung, neben dem Tisch. Sie gehört dem
 * VENUE, nicht dem Raum: Ein Foyer überlebt das Löschen eines Tischplans.
 *
 * Was sie ausdrücklich NICHT ist: ein Tisch. Sie taucht in keiner
 * Platzrechnung auf, hat keine weiche Kapazität, keine Großgruppen-Regel und
 * lässt sich nicht für einen Termin sperren. `capacity_per_slot` ist eine
 * Bremse gegen Überlast, keine Zahl von Sitzplätzen.
 */
class PickupStation extends Model
{
    use BelongsToTeam;
    use HasPlanPosition;
    use HasTranslations;

    protected $table = 'reservation_pickup_stations';

    protected $fillable = [
        'team_id',
        'venue_id',
        'name',
        'description',
        'capacity_per_slot',
        'sort_order',
        'is_active',
        'floor_plan_id',
        'x_pct',
        'y_pct',
        'w_pct',
        'h_pct',
        'shape',
        'rotation',
    ];

    protected $casts = [
        'capacity_per_slot' => 'integer',
        'sort_order'        => 'integer',
        'is_active'         => 'boolean',
        'x_pct'             => 'float',
        'y_pct'             => 'float',
        'w_pct'             => 'float',
        'h_pct'             => 'float',
        'rotation'          => 'integer',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class, 'venue_id');
    }

    /** Der Plan, in dem sie liegt – oder keiner. */
    public function floorPlan(): BelongsTo
    {
        return $this->belongsTo(FloorPlan::class, 'floor_plan_id');
    }

    public function eventStations(): HasMany
    {
        return $this->hasMany(EventStation::class, 'pickup_station_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'pickup_station_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * team_id aus dem Venue ableiten, auch ohne angemeldeten Anwender.
     *
     * Dasselbe Muster wie beim Tisch: Sonst bekäme eine über die Konsole oder
     * einen Seeder angelegte Station kein Team und wäre für den globalen Scope
     * unsichtbar – vorhanden, aber nirgends zu sehen.
     */
    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (! $model->team_id && $model->venue_id) {
                $model->team_id = Venue::withoutGlobalScope('team')
                    ->whereKey($model->venue_id)
                    ->value('team_id');
            }
        });
    }
}
