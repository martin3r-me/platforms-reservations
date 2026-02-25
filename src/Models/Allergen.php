<?php

namespace Platform\Reservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Allergen extends Model
{
    protected $table = 'reservation_allergens';

    protected $fillable = [
        'name',
        'code',
        'icon',
    ];

    public function menuItems(): BelongsToMany
    {
        return $this->belongsToMany(
            MenuItem::class,
            'reservation_menu_item_allergen',
            'allergen_id',
            'menu_item_id'
        )->withTimestamps();
    }
}
