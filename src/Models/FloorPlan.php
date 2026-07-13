<?php

namespace Platform\Reservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Platform\Core\Models\ContextFile;

class FloorPlan extends Model
{
    protected $table = 'reservation_floor_plans';

    protected $fillable = [
        'venue_id',
        'name',
        'layout_json',
        'default_sales_list_id',
        'background_context_file_id',
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

    /** Grundriss-/Hintergrundbild (ContextFile aus platform-core). */
    public function backgroundFile(): BelongsTo
    {
        return $this->belongsTo(ContextFile::class, 'background_context_file_id');
    }

    /**
     * Signierte URL des Grundrisses. Große Formate über Varianten, sonst
     * Original als Fallback (Varianten entstehen asynchron).
     */
    public function backgroundUrl(string $variant = 'large_original'): ?string
    {
        $file = $this->backgroundFile;

        if (!$file) {
            return null;
        }

        $match = $file->variants->firstWhere('variant_type', $variant)
            ?? $file->variants->first(fn ($v) => str_starts_with($v->variant_type, 'large_'))
            ?? $file->variants->first(fn ($v) => str_starts_with($v->variant_type, 'medium_'));

        return $match?->url ?? $file->url;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
