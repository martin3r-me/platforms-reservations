<?php

namespace Platform\Reservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Platform\Reservation\Models\Concerns\BelongsToTeam;
use Platform\Reservation\Models\Concerns\HasContextImage;
use Platform\Core\Services\ContextFileService;
use Platform\Reservation\Exceptions\FloorPlanInUseException;

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

        // Ein eingeplanter Raum darf nicht verschwinden. Am Löschen hängen drei
        // Kaskaden in der Datenbank: die Raumzuordnung des Termins, die Tische
        // des Plans – und über nullOnDelete verlieren die Buchungen der Gäste
        // ihren Tisch. Nichts davon meldet sich, es wäre einfach weg.
        //
        // Die Prüfung sitzt am Modell und nicht in der Oberfläche, damit jeder
        // Weg sie trifft: Oberfläche, MCP-Werkzeug, Skript.
        static::deleting(function (self $model) {
            $termine = $model->anstehendeTermine();

            if ($termine->isEmpty()) {
                return;
            }

            throw new FloorPlanInUseException(
                'Der Raum „' . $model->name . '" ist noch in Terminen eingeplant: '
                . $termine->map(fn ($e) => $e->name . ' (' . $e->date?->format('d.m.Y') . ')')->implode(', ')
                . '. Bitte dort erst den Raum entfernen oder den Termin absagen.'
            );
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

    /** Termine, in die dieser Raum eingeplant ist. */
    public function eventRooms(): HasMany
    {
        return $this->hasMany(EventRoom::class, 'floor_plan_id');
    }

    /**
     * Termine, die diesen Raum noch brauchen: ab heute und nicht abgesagt.
     *
     * Abgesagte zählen nicht – da findet nichts mehr statt. Entwürfe zählen
     * mit: Der Raum ist dort eingeplant, und ein verschwundener Raum in einem
     * Entwurf fällt erst beim Veröffentlichen auf.
     *
     * "Closed" zählt ebenfalls mit: Der Bestellschluss ist vorbei, die
     * Veranstaltung aber nicht – Küche und Laufzettel brauchen die Tische noch.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Event>
     */
    public function anstehendeTermine(): \Illuminate\Database\Eloquent\Collection
    {
        return Event::withoutGlobalScope('team')
            ->whereIn('id', EventRoom::query()->where('floor_plan_id', $this->getKey())->select('event_id'))
            ->upcoming()
            ->where('status', '!=', Event::STATUS_CANCELLED)
            ->orderBy('date')
            ->get();
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
    /**
     * Link zum Herunterladen des hinterlegten Grundrisses.
     *
     * Nicht die download_url der Datei: Beim Hochladen wandelt der
     * ContextFileService jedes Bild in WebP um, der gespeicherte
     * original_name trägt aber weiterhin die alte Endung. Der Download hieße
     * dann "grundriss.png", wäre aber eine WebP-Datei – manche Programme
     * öffnen das nicht. Deshalb der Name aus der Datei plus der Endung, die
     * tatsächlich im Speicher liegt.
     */
    public function backgroundDownloadUrl(): ?string
    {
        $file = $this->imageFile;

        if (! $file) {
            return null;
        }

        $endung = pathinfo((string) $file->path, PATHINFO_EXTENSION) ?: 'webp';
        $name   = pathinfo((string) ($file->original_name ?: $this->name), PATHINFO_FILENAME) ?: 'grundriss';

        return ContextFileService::generateDownloadUrl(
            $file->disk,
            $file->path,
            $file->token,
            $name . '.' . $endung,
        );
    }

    public function backgroundUrl(string $variant = 'large_original'): ?string
    {
        return $this->imageUrl($variant);
    }

    /**
     * Angezeigtes Seitenverhältnis (Breite/Höhe) der Grundriss-Fläche –
     * aus den Bildmaßen, rotationsbewusst (90°/270° tauschen). Ohne Bild 4:3.
     * Editor und Gast-Viewer richten ihren Container danach aus (kein Letterbox).
     */
    /**
     * Höhe/Breite-Verhältnis je Tischform (1 = optisch quadratisch bzw. rund).
     */
    public const SHAPE_RATIO = [
        'round'     => 1.0,
        'square'    => 1.0,
        'rectangle' => 0.6,
    ];

    /**
     * Faktor, mit dem sich h_pct aus w_pct ergibt.
     *
     * w_pct und h_pct beziehen sich auf UNTERSCHIEDLICHE Achsen – ohne die
     * Korrektur um displayAspect() wäre ein "quadratischer" Tisch auf einem
     * 4:3-Plan sichtbar flachgedrückt.
     */
    public function heightFactor(string $shape): float
    {
        return $this->displayAspect() * (self::SHAPE_RATIO[$shape] ?? 1.0);
    }

    /**
     * Passende Höhe (Anteil 0…1) zu einer Breite. Einzige Quelle dieser Regel –
     * genutzt vom Editor, von der Blade-Ansicht und von den MCP-Tools.
     */
    public function heightForWidth(float $wPct, string $shape): float
    {
        return min(0.9, max(0.02, $wPct * $this->heightFactor($shape)));
    }

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
