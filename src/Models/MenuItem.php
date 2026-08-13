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
use Platform\Reservation\Support\BundlePriceAllocator;

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

    /**
     * Was die Bestandteile einzeln kosten würden – Grundlage für „statt 8,40 €".
     * null bei Einzelartikeln.
     */
    /**
     * Brutto-Anteile EINER Bundle-Einheit je Steuersatz.
     *
     * Ein Bundle hat keinen eigenen Steuersatz – seine Bestandteile haben
     * verschiedene. Wer das Bundle nur mit dessen tax_rate-Spalte verrechnet,
     * weist die MwSt falsch aus (die Spalte ist beim Bundle bedeutungslos).
     *
     * Damit das Gast-Frontend die Vorschau vor der Bestellung korrekt zeigen
     * kann, ohne die cent-genaue Verteilung nachzubauen, liefern wir die
     * fertige Aufteilung mit. Der Aufrufer multipliziert nur noch mit der Menge.
     *
     * ACHTUNG: Das ist eine VORSCHAU für eine Einheit. Die endgültige Aufteilung
     * einer Bestellung macht CartCalculator::explodedLines() über die tatsächliche
     * Menge; bei Mengen > 1 kann sie um einen Cent abweichen, weil dort Positionen
     * gesplittet werden. Für die Anzeige im Warenkorb ist das gewollt – für den
     * Beleg zählt allein die Rechnung im Checkout.
     *
     * @return array<int, array{tax_rate: float, gross: float}>  absteigend nach Satz
     */
    public function bundleTaxShares(): array
    {
        if (! $this->isBundle()) {
            return [];
        }

        $components = $this->components;

        if ($components->isEmpty()) {
            return [];
        }

        $toCents = fn ($v) => (int) round((float) $v * 100);

        $allocation = BundlePriceAllocator::allocate(
            $toCents($this->price),
            $components->map(fn (self $c) => [
                'key'              => $c->id,
                'list_price_cents' => $toCents($c->price),
                'quantity'         => max(1, (int) ($c->pivot->quantity ?? 1)),
            ])->all(),
            1,
        );

        $byId        = $components->keyBy('id');
        $grossByRate = [];

        foreach ($allocation as $row) {
            $component = $byId->get($row['key']);

            if (! $component) {
                continue;
            }

            $rate = (string) (float) $component->tax_rate;
            $grossByRate[$rate] = ($grossByRate[$rate] ?? 0) + $row['quantity'] * $row['unit_price_cents'];
        }

        krsort($grossByRate, SORT_NUMERIC); // 19 % vor 7 %, wie in der MwSt-Aufstellung

        $shares = [];

        foreach ($grossByRate as $rate => $cents) {
            $shares[] = ['tax_rate' => (float) $rate, 'gross' => round($cents / 100, 2)];
        }

        return $shares;
    }

    public function bundleReferencePrice(): ?float
    {
        if (! $this->isBundle()) {
            return null;
        }

        return round($this->components->sum(
            fn (self $c) => (float) $c->price * max(1, (int) ($c->pivot->quantity ?? 1))
        ), 2);
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
    /**
     * Ein Bundle trägt keine Standzeit.
     *
     * Die Standzeit hängt am einzelnen Artikel: Brezel und Bier gehen zu
     * verschiedenen Zeiten raus und stehen auf dem Küchenzettel getrennt.
     * Der Küchenzettel gruppiert über die Buchungsposition, und die zeigt
     * bei Bundles auf den Bestandteil – eine Standzeit am Bundle würde
     * nirgends gelesen.
     *
     * Zentral hier, weil Maske, Create-Tool und Update-Tool sonst jedes für
     * sich daran denken müssten. Betrifft auch den Umbau eines bestehenden
     * Artikels zum Bundle: der alte Wert darf nicht hängen bleiben.
     */
    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            if ($item->is_bundle) {
                $item->holding_class_id = null;
            }
        });
    }

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
