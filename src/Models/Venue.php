<?php

namespace Platform\Reservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Platform\Reservation\Exceptions\FloorPlanInUseException;
use Platform\Reservation\Models\Concerns\BelongsToTeam;
use Symfony\Component\Uid\UuidV7;

class Venue extends Model
{
    use BelongsToTeam;

    protected $table = 'reservation_venues';

    protected $fillable = [
        'uuid',
        'team_id',
        'name',
        'address',
        'city',
        'postal_code',
        'country',
        'phone',
        'email',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) UuidV7::generate();
            }
        });

        // Das Venue nimmt seine Räume mit – und das erledigt die Datenbank per
        // Kaskade, ohne dass die Modelle davon erfahren. Der Schutz am Raum
        // greift dort also nicht; er muss hier stehen.
        static::deleting(function (self $model) {
            // Auch die Abholstationen, nicht nur die Räume.
            //
            // Sie hängen am Venue und werden von der Kaskade mitgenommen -
            // ohne diese Prüfung nähme ein gelöschtes Venue eine Abholstelle
            // mit, die morgen gebraucht wird. Dass die Station bei „Foyer
            // links" gar nicht in einem Raum liegt, macht es schlimmer: Der
            // Blick auf die Räume beantwortet die Frage dann nicht.
            foreach ($model->pickupStations as $station) {
                $termine = $station->anstehendeTermine();

                if ($termine->isNotEmpty()) {
                    throw new FloorPlanInUseException(
                        'Zum Venue „' . $model->name . '" gehört die Abholstation „' . $station->name
                        . '", die noch in Terminen eingeplant ist: '
                        . $termine->map(fn ($e) => $e->name . ' (' . $e->date?->format('d.m.Y') . ')')->implode(', ')
                        . '. Bitte dort erst die Station entfernen oder den Termin absagen.'
                    );
                }
            }

            foreach ($model->floorPlans as $plan) {
                $termine = $plan->anstehendeTermine();

                if ($termine->isNotEmpty()) {
                    throw new FloorPlanInUseException(
                        'Zum Venue „' . $model->name . '" gehört der Raum „' . $plan->name
                        . '", der noch in Terminen eingeplant ist: '
                        . $termine->map(fn ($e) => $e->name . ' (' . $e->date?->format('d.m.Y') . ')')->implode(', ')
                        . '. Bitte dort erst den Raum entfernen oder den Termin absagen.'
                    );
                }
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    public function floorPlans(): HasMany
    {
        return $this->hasMany(FloorPlan::class, 'venue_id');
    }

    /** Die Abholstationen dieses Hauses – sie gehören dem Venue, nicht dem Raum. */
    public function pickupStations(): HasMany
    {
        return $this->hasMany(PickupStation::class, 'venue_id')->orderBy('sort_order');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }
}
