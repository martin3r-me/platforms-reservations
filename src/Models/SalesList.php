<?php

namespace Platform\Reservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SalesList extends Model
{
    protected $table = 'reservation_sales_lists';

    protected $fillable = [
        'team_id',
        'name',
        'description',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    public function menuItems(): BelongsToMany
    {
        return $this->belongsToMany(
            MenuItem::class,
            'reservation_sales_list_items',
            'sales_list_id',
            'menu_item_id'
        )->withPivot('sort_order')->withTimestamps()->orderByPivot('sort_order');
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    /** Team-Default-Liste (Fallback, wenn ein Termin keine Liste gesetzt hat). */
    public static function defaultForTeam(int $teamId): ?self
    {
        return static::forTeam($teamId)->where('is_default', true)->first();
    }

    /** Artikel, die Gäste sehen dürfen: freigegeben + verfügbar. */
    public function guestVisibleItems()
    {
        return $this->menuItems()->guestVisible()->with(['allergens', 'additives', 'category', 'imageFile.variants'])->get();
    }
}
