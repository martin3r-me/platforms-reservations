<?php

namespace Platform\Reservation\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Platform\Reservation\Exceptions\FloorPlanInUseException;
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
    /**
     * Termine, in denen diese Station noch eingeplant ist.
     *
     * Nur anstehende und nicht abgesagte – ein Abend von letzter Saison hält
     * nichts mehr fest. Wortgleich zu FloorPlan::anstehendeTermine(); die
     * beiden Fassungen unterscheiden sich nur in der Zwischentabelle.
     */
    public function anstehendeTermine(): \Illuminate\Database\Eloquent\Collection
    {
        return Event::withoutGlobalScope('team')
            ->whereIn('id', EventStation::query()->where('pickup_station_id', $this->getKey())->select('event_id'))
            ->upcoming()
            ->where('status', '!=', Event::STATUS_CANCELLED)
            ->orderBy('date')
            ->get();
    }

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (! $model->team_id && $model->venue_id) {
                $model->team_id = Venue::withoutGlobalScope('team')
                    ->whereKey($model->venue_id)
                    ->value('team_id');
            }
        });

        /**
         * Nicht löschbar, solange sie in einem anstehenden Termin hängt.
         *
         * Ohne diesen Schutz nimmt die Kaskade die Zuordnung mit, und der
         * Termin verliert lautlos einen Ort, an dem Gäste bestellt haben – die
         * Buchungen behielten nur noch ihren eingefrorenen Namen.
         *
         * Dieselbe Ausnahme wie beim Tischplan: Für die Oberfläche ist es
         * derselbe Fall, und zwei Ausnahmen für dieselbe Aussage müssten überall
         * doppelt gefangen werden.
         */
        static::deleting(function (self $model) {
            $termine = $model->anstehendeTermine();

            if ($termine->isNotEmpty()) {
                throw new FloorPlanInUseException(
                    'Die Abholstation „' . $model->name . '" ist noch in Terminen eingeplant: '
                    . $termine->map(fn ($e) => $e->name . ' (' . $e->date?->format('d.m.Y') . ')')->implode(', ')
                    . '. Bitte dort erst die Station entfernen oder den Termin absagen.'
                );
            }
        });
    }
}
