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

    /** ContextFile-Kontext der Atmosphäre-Bilder dieses Raums. */
    public const ATMOSPHERE_CONTEXT = 'reservation.floor_plan.atmosphere';

    public function tables(): HasMany
    {
        return $this->hasMany(Table::class, 'floor_plan_id');
    }

    /**
     * Atmosphäre-Bilder des Raums – beliebig viele platform-core ContextFiles
     * am Kontext (context_type, context_id). Kein Pivot, keine Single-Image-Spalte.
     */
    public function atmosphereFiles(): HasMany
    {
        return $this->hasMany(\Platform\Core\Models\ContextFile::class, 'context_id')
            ->where('context_type', self::ATMOSPHERE_CONTEXT)
            ->orderBy('id');
    }

    /**
     * Atmosphäre-Bilder als URL-Liste (für UI/API).
     *
     * @return array<int, array{id:int, url:string, thumbnail:string}>
     */
    public function atmosphereImages(): array
    {
        return $this->atmosphereFiles->map(fn ($file) => [
            'id'        => $file->id,
            'url'       => $this->contextFileVariantUrl($file, 'large_original'),
            'thumbnail' => $this->contextFileVariantUrl($file, 'medium_1_1'),
        ])->all();
    }

    /** Signierte URL einer Variante (mit Ratio-/Original-Fallback). */
    protected function contextFileVariantUrl(\Platform\Core\Models\ContextFile $file, string $variant): string
    {
        $match = $file->variants->firstWhere('variant_type', $variant);

        if (! $match && str_contains($variant, '_')) {
            $ratio = substr($variant, strpos($variant, '_') + 1);
            $match = $file->variants->first(fn ($v) => str_ends_with($v->variant_type, '_' . $ratio));
        }

        return $match?->url ?? $file->url;
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
