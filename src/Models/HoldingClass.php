<?php

namespace Platform\Reservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Platform\Reservation\Models\Concerns\BelongsToTeam;

/**
 * Standzeit-/Zeitkritikalitäts-Klasse eines Artikels (#523), pro Team pflegbar –
 * z. B. "Unbedenklich" (zeitunkritisch), "Sollte kalt sein", "Sollte heiß sein".
 * Bewusst als Stammliste (kein hartes Enum), damit Stufen frei angelegt,
 * benannt und sortiert werden können. sort_order steuert die Laufrunden-
 * Priorität im Function Sheet (kleiner = früher platzierbar / unkritischer).
 */
class HoldingClass extends Model
{
    use BelongsToTeam;

    protected $table = 'reservation_holding_classes';

    protected $fillable = [
        'team_id',
        'name',
        'description',
        'color',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'holding_class_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    /** Team-Helfer (scope-sicher, für nicht-Web-Kontexte wie MCP/API). */
    public static function forTeam(int $teamId)
    {
        return static::withoutGlobalScope('team')->where('team_id', $teamId);
    }
}
