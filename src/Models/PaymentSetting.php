<?php

namespace Platform\Reservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\Reservation\Models\Concerns\BelongsToTeam;

/**
 * Pro-Team-Zahlungseinstellungen (aktuell Mollie). API-Keys werden
 * verschlüsselt gespeichert. Quelle für den MollieCredentialResolver.
 */
class PaymentSetting extends Model
{
    use BelongsToTeam;

    public const MODE_TEST = 'test';
    public const MODE_LIVE = 'live';

    protected $table = 'reservation_payment_settings';

    protected $fillable = [
        'team_id',
        'provider',
        'enabled',
        'mode',
        'test_api_key',
        'live_api_key',
    ];

    protected $casts = [
        'enabled'      => 'boolean',
        'test_api_key' => 'encrypted',
        'live_api_key' => 'encrypted',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    /** Aktiver Key gemäß Modus (test|live). */
    public function activeApiKey(): ?string
    {
        $key = $this->mode === self::MODE_LIVE ? $this->live_api_key : $this->test_api_key;

        return $key !== '' ? $key : null;
    }

    /** Einsatzbereit = aktiviert und passender Key vorhanden. */
    public function isReady(): bool
    {
        return $this->enabled && $this->activeApiKey() !== null;
    }
}
