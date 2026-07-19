<?php

namespace Platform\Reservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Platform\Reservation\Models\Concerns\HasTranslations;

class Additive extends Model
{
    use HasTranslations;

    /** Übersetzbare Felder (#522) – der Code bleibt gleich. */
    protected array $translatable = ['name'];

    protected $table = 'reservation_additives';

    protected $fillable = [
        'team_id',
        'name',
        'code',
        'icon',
    ];

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function menuItems(): BelongsToMany
    {
        return $this->belongsToMany(
            MenuItem::class,
            'reservation_menu_item_additive',
            'additive_id',
            'menu_item_id'
        )->withTimestamps();
    }
}
