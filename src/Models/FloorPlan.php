<?php

namespace Platform\Reservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Platform\Reservation\Models\Concerns\BelongsToTeam;
use Platform\Reservation\Models\Concerns\HasContextImage;

class FloorPlan extends Model
{
    use BelongsToTeam;
    use HasContextImage;

    protected $table = 'reservation_floor_plans';

    protected $fillable = [
        'venue_id',
        'team_id',
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

    protected static function booted(): void
    {
        // team_id autoritativ aus dem Venue ableiten (auch ohne Auth, z.B. Seeder),
        // damit der globale Team-Scope auch für neu angelegte Pläne greift.
        static::creating(function (self $model) {
            if (! $model->team_id && $model->venue_id) {
                $model->team_id = Venue::withoutGlobalScope('team')
                    ->whereKey($model->venue_id)
                    ->value('team_id');
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

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

    /**
     * Angezeigtes Seitenverhältnis (Breite/Höhe) der Grundriss-Fläche –
     * aus den Bildmaßen, rotationsbewusst (90°/270° tauschen). Ohne Bild 4:3.
     * Editor und Gast-Viewer richten ihren Container danach aus (kein Letterbox).
     */
    public function displayAspect(): float
    {
        $file = $this->imageFile;
        $w = (float) ($file->width ?? 0);
        $h = (float) ($file->height ?? 0);

        if ($w <= 0 || $h <= 0) {
            return 4 / 3;
        }

        $rot = ((($this->background_rotation ?? 0) % 360) + 360) % 360;

        return $rot % 180 === 0 ? $w / $h : $h / $w;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
