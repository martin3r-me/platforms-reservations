<?php

namespace Platform\Reservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pro-Team konfigurierbare Checkout-Texte (18+-Hinweis, Rechtstext,
 * Datenschutz-Link). Leere Felder fallen auf sinnvolle Defaults zurück.
 */
class CheckoutSetting extends Model
{
    public const DEFAULT_AGE_TEXT = 'Ihre Bestellung enthält alkoholische Getränke. Ich bestätige, dass ich mindestens 18 Jahre alt bin. Das Servicepersonal kann vor Ort einen Altersnachweis verlangen.';
    public const DEFAULT_LEGAL_TEXT = 'Ich habe die Hinweise zu Allergenen und Zusatzstoffen zur Kenntnis genommen und bestelle zahlungspflichtig.';

    protected $table = 'reservation_checkout_settings';

    protected $fillable = [
        'team_id',
        'age_check_text',
        'legal_text',
        'privacy_url',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    /** Bestehende Einstellung oder ein neues (ungespeichertes) Objekt mit Defaults. */
    public static function forTeam(int $teamId): self
    {
        return static::firstOrNew(['team_id' => $teamId]);
    }

    public function ageText(): string
    {
        return trim((string) $this->age_check_text) ?: self::DEFAULT_AGE_TEXT;
    }

    public function legalText(): string
    {
        return trim((string) $this->legal_text) ?: self::DEFAULT_LEGAL_TEXT;
    }
}
