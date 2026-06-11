<?php

namespace Platform\Reservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FloorPlan extends Model
{
    protected $table = 'reservation_floor_plans';

    protected $fillable = [
        'venue_id',
        'name',
        'layout_json',
        'default_sales_list_id',
        'is_active',
    ];

    protected $casts = [
        'layout_json' => 'array',
        'is_active'   => 'boolean',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class, 'venue_id');
    }

    public function tables(): HasMany
    {
        return $this->hasMany(Table::class, 'floor_plan_id');
    }

    /** Raum-Default: Vorbelegung der Verkaufsliste beim Anlegen eines Termins. */
    public function defaultSalesList(): BelongsTo
    {
        return $this->belongsTo(SalesList::class, 'default_sales_list_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
