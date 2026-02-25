<?php

namespace Platform\Reservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    protected $table = 'reservation_menu_items';

    protected $fillable = [
        'team_id',
        'category_id',
        'name',
        'description',
        'price',
        'tax_rate',
        'available',
        'sort_order',
    ];

    protected $casts = [
        'price'      => 'decimal:2',
        'available'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'category_id');
    }

    public function allergens(): BelongsToMany
    {
        return $this->belongsToMany(
            Allergen::class,
            'reservation_menu_item_allergen',
            'menu_item_id',
            'allergen_id'
        )->withTimestamps();
    }

    public function bookingItems(): HasMany
    {
        return $this->hasMany(BookingItem::class, 'menu_item_id');
    }

    public function scopeAvailable($query)
    {
        return $query->where('available', true);
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }
}
