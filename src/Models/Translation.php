<?php

namespace Platform\Reservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Eine Feld-Übersetzung eines beliebigen Modells (#522). Basis-Sprache (DE)
 * liegt in den Modell-Spalten; hier nur abweichende Sprachen.
 */
class Translation extends Model
{
    /** Basis-/Default-Sprache (liegt in den Modell-Spalten). */
    public const DEFAULT_LOCALE = 'de';

    protected $table = 'reservation_translations';

    protected $fillable = [
        'translatable_type',
        'translatable_id',
        'locale',
        'field',
        'value',
    ];

    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }
}
