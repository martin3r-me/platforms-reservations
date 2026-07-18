<?php

namespace Platform\Reservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Platform\Core\Models\User;
use Platform\Reservation\Models\Concerns\BelongsToTeam;
use Platform\Reservation\Models\Concerns\HasContextImage;

class MenuItem extends Model
{
    use BelongsToTeam;
    use HasContextImage;

    public const APPROVAL_DRAFT    = 'draft';
    public const APPROVAL_REVIEW   = 'review';
    public const APPROVAL_APPROVED = 'approved';

    protected $table = 'reservation_menu_items';

    protected $fillable = [
        'team_id',
        'category_id',
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
        'approval_status',
        'submitted_by',
        'approved_by',
        'approved_at',
        'image_context_file_id',
    ];

    protected $casts = [
        'price'         => 'decimal:2',
        'available'     => 'boolean',
        'sort_order'    => 'integer',
        'is_vegetarian' => 'boolean',
        'is_vegan'      => 'boolean',
        'is_alcoholic'  => 'boolean',
        'approved_at'   => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'category_id');
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
