<?php

namespace Platform\Reservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Platform\Reservation\Models\Concerns\HasContextImage;

class FloorPlan extends Model
{
    use HasContextImage;

    protected $table = 'reservation_floor_plans';

    protected $fillable = [
        'venue_id',
        'name',
        'layout_json',
        'default_sales_list_id',
        'background_context_file_id',
        'background_rotation',
        'is_active',
    ];

    protected $casts = [
        'layout_json'         => 'array',
        'is_active'           => 'boolean',
        'background_rotation' => 'integer',
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

    /** Grundriss-/Hintergrundbild liegt in einer eigenen Spalte. */
    protected function contextImageColumn(): string
    {
        return 'background_context_file_id';
    }

    /**
     * Signierte URL des Grundrisses. Standard: ungeschnittenes Original-Ratio
     * (kein Crop), Fallback auf das Original (Varianten entstehen asynchron).
     * Delegiert an {@see HasContextImage::imageUrl()}.
     */
    public function backgroundUrl(string $variant = 'large_original'): ?string
    {
        return $this->imageUrl($variant);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
