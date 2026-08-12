<?php

namespace Platform\Reservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Platform\Core\Models\User;
use Platform\Reservation\Models\Concerns\BelongsToTeam;
use Platform\Reservation\Models\Concerns\HasContextImage;
use Platform\Reservation\Models\Concerns\HasTranslations;

class MenuItem extends Model
{
    use BelongsToTeam;
    use HasContextImage;
    use HasTranslations;

    /** Übersetzbare Felder (#522). */
    protected array $translatable = ['name', 'description'];

    public const APPROVAL_DRAFT    = 'draft';
    public const APPROVAL_REVIEW   = 'review';
    public const APPROVAL_APPROVED = 'approved';

    /** Erlaubte MwSt-Sätze (DE-Gastronomie): 7 % ermäßigt, 19 % regulär. */
    public const TAX_RATES = [7.0, 19.0];

    // Hinweis: price ist ein BRUTTOPREIS (inkl. MwSt); siehe Support\Vat.

    protected $table = 'reservation_menu_items';

    protected $fillable = [
        'team_id',
        'category_id',
        'is_bundle',
        'holding_class_id',
        'name',
        'description',
        'portion_size',
        'price',
        'tax_rate',
        'available',
        'sort_order',
        'is_vegetarian',
        'is_vegan',
        'is_alcoholic',
        'min_age',
        'is_caffeinated',
        'caffeine_mg',
        'approval_status',
        'submitted_by',
        'approved_by',
        'approved_at',
        'image_context_file_id',
    ];

    protected $casts = [
        'price'         => 'decimal:2',
        'tax_rate'      => 'decimal:2',
        'available'     => 'boolean',
        'is_bundle'     => 'boolean',
        'sort_order'    => 'integer',
        'is_vegetarian' => 'boolean',
        'is_vegan'      => 'boolean',
        'is_alcoholic'  => 'boolean',
        'min_age'       => \Platform\Reservation\Enums\AgeRestriction::class,
        'is_caffeinated' => 'boolean',
        'caffeine_mg'   => 'decimal:1',
        'approved_at'   => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    /**
     * Bestandteile eines Bundles (mit Menge). Flach – ein Bundle darf kein
     * Bundle enthalten, das wird beim Speichern geprüft.
     */
    public function components(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'reservation_menu_item_components',
            'bundle_id',
            'component_id',
        )->withPivot(['quantity', 'sort_order'])
         ->withTimestamps()
         ->orderByPivot('sort_order');
    }

    /** Bundles, in denen dieser Artikel enthalten ist (Löschschutz, Hinweise). */
    public function partOfBundles(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'reservation_menu_item_components',
            'component_id',
            'bundle_id',
        );
    }

    public function isBundle(): bool
    {
        return (bool) $this->is_bundle;
    }

    /**
     * Artikel, aus denen sich die Eigenschaften ergeben: beim Bundle die
     * Bestandteile, sonst der Artikel selbst.
     *
     * Allergene, Alkohol und Mindestalter werden BEWUSST abgeleitet und nicht
     * am Bundle gepflegt – von Hand gepflegt liefe das früher oder später
     * auseinander, und bei Allergenen ist das ein rechtliches Problem.
     *
     * @return \Illuminate\Support\Collection<int, self>
     */
    public function effectiveItems(): \Illuminate\Support\Collection
    {
        return $this->isBundle() ? $this->components : collect([$this]);
    }

    /** Allergene inkl. der Bestandteile, ohne Dubletten. */
    public function effectiveAllergens(): \Illuminate\Support\Collection
    {
        return $this->effectiveItems()
            ->flatMap(fn (self $i) => $i->allergens)
            ->unique('id')
            ->values();
    }

    /** Zusatzstoffe inkl. der Bestandteile, ohne Dubletten. */
    public function effectiveAdditives(): \Illuminate\Support\Collection
    {
        return $this->effectiveItems()
            ->flatMap(fn (self $i) => $i->additives)
            ->unique('id')
            ->values();
    }

    /** Enthält das Bundle Alkohol? Ein Bier im Bundle macht es zum 18+-Bundle. */
    public function effectiveIsAlcoholic(): bool
    {
        return $this->effectiveItems()->contains(fn (self $i) => (bool) $i->is_alcoholic);
    }

    /** Höchste Altersgrenze der Bestandteile. */
    public function effectiveMinAge(): ?\Platform\Reservation\Enums\AgeRestriction
    {
        return $this->effectiveItems()
            ->map(fn (self $i) => $i->min_age)
            ->filter()
            ->sortByDesc(fn ($age) => $age->value)
            ->first();
    }

    /**
     * Verkaufbar? Ein Bundle fällt weg, sobald ein Bestandteil nicht verfügbar
     * oder nicht freigegeben ist – sonst verkauft man etwas, das nicht
     * ausgeliefert werden kann.
     */
    public function effectivelyAvailable(): bool
    {
        if (! $this->available || $this->approval_status !== self::APPROVAL_APPROVED) {
            return false;
        }

        if (! $this->isBundle()) {
            return true;
        }

        $components = $this->components;

        return $components->isNotEmpty()
            && $components->every(
                fn (self $c) => $c->available && $c->approval_status === self::APPROVAL_APPROVED
            );
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'category_id');
    }

    /** Standzeit-/Zeitkritikalitäts-Klasse (#523), optional. */
    public function holdingClass(): BelongsTo
    {
        return $this->belongsTo(HoldingClass::class, 'holding_class_id');
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

    public function additives(): BelongsToMany
    {
        return $this->belongsToMany(
            Additive::class,
            'reservation_menu_item_additive',
            'menu_item_id',
            'additive_id'
        )->withTimestamps();
    }

    public function bookingItems(): HasMany
    {
        return $this->hasMany(BookingItem::class, 'menu_item_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeAvailable($query)
    {
        return $query->where('available', true);
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeApproved($query)
    {
        return $query->where('approval_status', self::APPROVAL_APPROVED);
    }

    /** Für Gäste sichtbar: freigegeben UND verfügbar. */
    public function scopeGuestVisible($query)
    {
        return $query->approved()->available();
    }

    /**
     * Vier-Augen-Schritt 1: Artikel zur Prüfung einreichen.
     */
    public function submitForReview(User $user): void
    {
        $this->update([
            'approval_status' => self::APPROVAL_REVIEW,
            'submitted_by'    => $user->id,
            'approved_by'     => null,
            'approved_at'     => null,
        ]);
    }

    /**
     * Vier-Augen-Schritt 2: Freigabe – verweigert, wenn Prüfer = Einreicher.
     */
    public function approve(User $user): bool
    {
        if ($this->submitted_by !== null && (int) $this->submitted_by === (int) $user->id) {
            return false;
        }

        $this->update([
            'approval_status' => self::APPROVAL_APPROVED,
            'approved_by'     => $user->id,
            'approved_at'     => now(),
        ]);

        return true;
    }

    /**
     * Zurück auf Entwurf (z.B. nach inhaltlicher Änderung).
     */
    public function resetApproval(): void
    {
        $this->update([
            'approval_status' => self::APPROVAL_DRAFT,
            'submitted_by'    => null,
            'approved_by'     => null,
            'approved_at'     => null,
        ]);
    }
}
