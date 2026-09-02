<?php

namespace Platform\Reservation\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\Reservation\Models\Concerns\BelongsToTeam;

/**
 * Ein beendeter Bestellweg – bestellt oder abgebrochen.
 *
 * Entsteht in dem Moment, in dem die zugehörige CheckoutSession verschwindet
 * (siehe Migration). Wird nie geändert und nie gelöscht: Sie trägt keine
 * Kennung mehr und ist damit auf niemanden mehr beziehbar.
 */
class CheckoutStat extends Model
{
    use BelongsToTeam;

    protected $table = 'reservation_checkout_stats';

    /** Angelegt und nie wieder angefasst – ended_at ist der einzige Zeitpunkt. */
    public $timestamps = false;

    public const AUSGANG_BESTELLT    = 'ordered';
    public const AUSGANG_ABGEBROCHEN = 'abandoned';

    protected $fillable = [
        'team_id',
        'event_id',
        'event_date',
        'last_step',
        'step_no',
        'step_count',
        'outcome',
        'party_size',
        'items_count',
        'cart_total',
        'duration_seconds',
        'ended_at',
    ];

    protected $casts = [
        'event_date'       => 'date',
        'step_no'          => 'integer',
        'step_count'       => 'integer',
        'party_size'       => 'integer',
        'items_count'      => 'integer',
        'cart_total'       => 'decimal:2',
        'duration_seconds' => 'integer',
        'ended_at'         => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function scopeAbgebrochen(Builder $query): Builder
    {
        return $query->where('outcome', self::AUSGANG_ABGEBROCHEN);
    }

    public function scopeBestellt(Builder $query): Builder
    {
        return $query->where('outcome', self::AUSGANG_BESTELLT);
    }

    /** Zeitraum über das Ende des Bestellwegs, nicht über das Termindatum. */
    public function scopeImZeitraum(Builder $query, string $von, string $bis): Builder
    {
        return $query->whereBetween('ended_at', [$von . ' 00:00:00', $bis . ' 23:59:59']);
    }
}
