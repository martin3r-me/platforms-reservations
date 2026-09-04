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
        'submitted_at',
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
        'submitted_at'  => 'datetime',
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
            'submitted_at'    => now(),
            'approved_by'     => null,
            'approved_at'     => null,
        ]);
    }

    /**
     * Vier-Augen-Schritt 2: Freigabe – verweigert, wenn Prüfer = Einreicher.
     *
     * Ob die Pflicht gilt, entscheidet das Team-Setting. Zwei Feinheiten:
     *
     * Wurde der Artikel eingereicht, SOLANGE die Pflicht galt, bleibt sie für
     * ihn bestehen – auch wenn sie inzwischen abgeschaltet wurde. Sonst wäre
     * das Abschalten der bequeme Umweg um eine Prüfung, die bereits lief.
     *
     * Und: Ohne Einreicher (Altbestand ohne submitted_by) greift die Sperre
     * ohnehin nicht – dann gibt es niemanden, gegen den zu prüfen wäre.
     */
    public function approve(User $user): bool
    {
        if (! $this->canBeApprovedBy($user)) {
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
     * Darf dieser Mensch diesen Artikel freigeben?
     *
     * Gilt die Pflicht, scheidet der Einreicher aus. Sie gilt, wenn das Team
     * sie eingeschaltet hat – ODER wenn der Artikel eingereicht wurde, SOLANGE
     * sie galt: sonst waere das Abschalten ein Umweg um eine laufende Pruefung.
     *
     * Zusammen mit scopeAwaitingApprovalBy() unten sind das zwei Ausdrücke
     * derselben Regel – einmal für einen Artikel, einmal als Filter über viele.
     * Sie gehören zusammen geändert, sonst zeigt der Zähler am Menü etwas
     * anderes an, als die Freigabe danach zulässt.
     */
    public function canBeApprovedBy(User $user): bool
    {
        if ($this->submitted_by === null || (int) $this->submitted_by !== (int) $user->id) {
            return true;
        }

        $setting = CheckoutSetting::forTeam((int) $this->team_id);

        if ($setting->fourEyesRequired()) {
            return false;
        }

        // Pflicht ist aus: die eigene Einreichung ist frei, sofern sie nach dem
        // Abschalten entstand.
        $stichtag = $setting->four_eyes_changed_at;

        return $stichtag === null
            || ($this->submitted_at !== null && $this->submitted_at->gte($stichtag));
    }

    /**
     * Artikel, die auf die Freigabe DURCH DIESEN MENSCHEN warten – die eigenen
     * Einreichungen also nicht, solange die Pflicht für sie gilt. Siehe
     * canBeApprovedBy(): dieselbe Regel, hier als Filter.
     */
    public function scopeAwaitingApprovalBy($query, User $user, ?int $teamId = null)
    {
        $setting  = CheckoutSetting::forTeam((int) ($teamId ?? $user->current_team_id));
        $stichtag = $setting->four_eyes_changed_at;

        $query->where('approval_status', self::APPROVAL_REVIEW);

        // Pflicht aus und nie eine gewesen: niemand ist ausgeschlossen.
        if (! $setting->fourEyesRequired() && $stichtag === null) {
            return $query;
        }

        return $query->where(function ($q) use ($user, $setting, $stichtag) {
            $q->whereNull('submitted_by')->orWhere('submitted_by', '!=', $user->id);

            if (! $setting->fourEyesRequired()) {
                $q->orWhere(fn ($qq) => $qq->whereNotNull('submitted_at')->where('submitted_at', '>=', $stichtag));
            }
        });
    }

    /**
     * Felder, deren Änderung eine erteilte Freigabe entwertet.
     *
     * Bewusst NICHT dabei: available, category_id, holding_class_id. Einen
     * Artikel auszublenden, umzusortieren oder anders zu takten ändert nichts
     * an dem, was der Gast bekommt – dafür braucht es kein zweites Augenpaar.
     *
     * Die Liste steht hier und nicht in der Oberfläche, weil inzwischen auch
     * das MCP-Werkzeug danach entscheidet. Stünde sie zweimal da, liefe sie
     * beim nächsten neuen Feld auseinander.
     */
    public const CONTENT_FIELDS = [
        'name', 'description', 'portion_size', 'price', 'tax_rate',
        'is_vegetarian', 'is_vegan', 'is_alcoholic', 'min_age',
        'is_caffeinated', 'caffeine_mg',
        // Aus einem Artikel ein Bundle zu machen (oder umgekehrt) ändert das
        // Produkt grundlegend – Freigabe muss neu erteilt werden.
        'is_bundle',
    ];

    /**
     * Muss dieser Artikel nach einer inhaltlichen Änderung erneut freigegeben
     * werden?
     *
     * Nur, wenn die Vier-Augen-Pflicht GILT. Ist sie abgeschaltet, bleibt die
     * Freigabe stehen: Ein Team, das ausdrücklich kein zweites Augenpaar will,
     * soll nicht bei jedem korrigierten Tippfehler den Artikel aus dem Shop
     * fallen sehen und ihn von Hand wieder hereinholen müssen.
     *
     * Anders als canBeApprovedBy() ohne Stichtags-Feinheit, und das mit Absicht:
     * Der Stichtag schützt eine LAUFENDE Prüfung davor, per Abschalten umgangen
     * zu werden. Hier läuft keine – hier entsteht gerade erst ein neuer
     * Prüfbedarf, und ob es den gibt, entscheidet die Einstellung von heute.
     */
    public function requiresReapprovalAfterChange(): bool
    {
        if ($this->approval_status === self::APPROVAL_DRAFT) {
            return false;
        }

        // Eine LAUFENDE Prüfung fällt immer zurück – auch ohne Pflicht. Sie
        // beschreibt den Artikel nach der Änderung nicht mehr, und im Shop
        // steht er ohnehin nicht; es geht also nichts verloren.
        //
        // Ohne diese Zeile entstand eine Sackgasse: Ein Artikel, der beim
        // ABSCHALTEN der Pflicht gerade in Prüfung war, behält sie (siehe
        // canBeApprovedBy) – sein Einreicher darf ihn nie freigeben, und die
        // Oberfläche bietet für „in Prüfung" nur „Freigeben" an. Wer allein
        // pflegt, bekam ihn nie wieder los.
        if ($this->approval_status === self::APPROVAL_REVIEW) {
            return true;
        }

        return CheckoutSetting::forTeam((int) $this->team_id)->fourEyesRequired();
    }

    /**
     * Zurück auf Entwurf (z.B. nach inhaltlicher Änderung).
     */
    public function resetApproval(): void
    {
        $this->update([
            'approval_status' => self::APPROVAL_DRAFT,
            'submitted_by'    => null,
            'submitted_at'    => null,
            'approved_by'     => null,
            'approved_at'     => null,
        ]);
    }
}
