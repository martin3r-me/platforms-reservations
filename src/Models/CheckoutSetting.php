<?php

namespace Platform\Reservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\Reservation\Models\Concerns\BelongsToTeam;
use Platform\Reservation\Models\Concerns\HasTranslations;

/**
 * Pro-Team konfigurierbare Checkout-Texte (18+-Hinweis, Rechtstext,
 * Datenschutz-Link). Leere Felder fallen auf sinnvolle Defaults zurück.
 */
class CheckoutSetting extends Model
{
    use BelongsToTeam;
    use HasTranslations;

    /** Übersetzbare Texte (#522). */
    protected array $translatable = ['age_check_text', 'legal_text'];

    public const DEFAULT_AGE_TEXT = 'Ihre Bestellung enthält alkoholische Getränke. Ich bestätige, dass ich mindestens 18 Jahre alt bin. Das Servicepersonal kann vor Ort einen Altersnachweis verlangen.';
    public const DEFAULT_LEGAL_TEXT = 'Ich habe die Hinweise zu Allergenen und Zusatzstoffen zur Kenntnis genommen und bestelle zahlungspflichtig.';

    // #520/#521 – Modi für konfigurierbare Anmeldefelder.
    public const MODE_REQUIRED = 'required';
    public const MODE_OPTIONAL = 'optional';
    public const MODE_HIDDEN   = 'hidden';
    public const MODES = [self::MODE_REQUIRED, self::MODE_OPTIONAL, self::MODE_HIDDEN];

    /** Konfigurierbare Gast-Kontaktfelder (Name & Personenzahl sind fest Pflicht). */
    public const CONFIGURABLE_FIELDS = ['email', 'phone', 'notes'];

    /** Default-Modus je Feld (#521: E-Mail Pflicht, Rufnummer optional). */
    public const DEFAULT_FIELD_MODES = [
        'email' => self::MODE_REQUIRED,
        'phone' => self::MODE_OPTIONAL,
        'notes' => self::MODE_OPTIONAL,
    ];

    protected $table = 'reservation_checkout_settings';

    protected $fillable = [
        'team_id',
        'age_check_text',
        'legal_text',
        'privacy_url',
        'default_room_release_mode',
        'field_email',
        'field_phone',
        'field_notes',
        'soft_table_capacity',
        'max_group_empty_table',
        'languages',
    ];

    protected $casts = [
        'soft_table_capacity'   => 'boolean',
        'max_group_empty_table' => 'integer',
        'languages'             => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    /**
     * Bestehende Einstellung oder ein neues (ungespeichertes) Objekt mit Defaults.
     * Ohne Team-Global-Scope, damit die Auflösung auch in nicht-Web-Kontexten
     * (Gast-API/MCP, in denen Auth::user()->currentTeam abweicht) korrekt bleibt.
     */
    public static function forTeam(int $teamId): self
    {
        return static::withoutGlobalScope('team')->firstOrNew(['team_id' => $teamId]);
    }

    /**
     * Modus eines Anmeldefelds (required|optional|hidden). Nicht konfigurierbare
     * Felder (name, count) sind immer Pflicht.
     */
    public function fieldMode(string $field): string
    {
        if (! in_array($field, self::CONFIGURABLE_FIELDS, true)) {
            return self::MODE_REQUIRED;
        }

        $mode = $this->{'field_' . $field} ?? null;

        return in_array($mode, self::MODES, true)
            ? $mode
            : (self::DEFAULT_FIELD_MODES[$field] ?? self::MODE_OPTIONAL);
    }

    public function fieldIsHidden(string $field): bool
    {
        return $this->fieldMode($field) === self::MODE_HIDDEN;
    }

    public function fieldIsRequired(string $field): bool
    {
        return $this->fieldMode($field) === self::MODE_REQUIRED;
    }

    public function fieldIsVisible(string $field): bool
    {
        return ! $this->fieldIsHidden($field);
    }

    /**
     * Baut die Validierungsregel für ein Gastfeld: Präfix required/nullable je
     * nach Modus, plus feldspezifische Zusatzregeln. Ausgeblendete Felder werden
     * ignoriert (nullable, ohne Formatprüfung).
     *
     * @param  array<int,string>  $extra
     * @return array<int,string>
     */
    public function guestFieldRule(string $field, array $extra = []): array
    {
        return match ($this->fieldMode($field)) {
            self::MODE_REQUIRED => array_merge([self::MODE_REQUIRED], $extra),
            self::MODE_HIDDEN   => ['nullable'],
            default             => array_merge(['nullable'], $extra),
        };
    }

    /** @return array<string,string> Feld => Modus (für Views/JSON). */
    public function guestFieldModes(): array
    {
        $modes = [];
        foreach (self::CONFIGURABLE_FIELDS as $field) {
            $modes[$field] = $this->fieldMode($field);
        }

        return $modes;
    }

    public function ageText(): string
    {
        return trim((string) $this->age_check_text) ?: self::DEFAULT_AGE_TEXT;
    }

    public function legalText(): string
    {
        return trim((string) $this->legal_text) ?: self::DEFAULT_LEGAL_TEXT;
    }

    /** Weiche Tisch-Kapazität: Großgruppen dürfen leere Tische überbelegen. */
    public function softTableCapacity(): bool
    {
        return (bool) $this->soft_table_capacity;
    }

    /** Max. Gruppengröße auf einem leeren Tisch (null = unbegrenzt). */
    public function maxGroupEmptyTable(): ?int
    {
        return $this->max_group_empty_table !== null ? (int) $this->max_group_empty_table : null;
    }

    /**
     * Angebotene Sprachen (locale-Codes). Basis-/Default-Sprache DE ist immer
     * enthalten und steht an erster Stelle.
     *
     * @return array<int,string>
     */
    public function languages(): array
    {
        $list = collect(is_array($this->languages) ? $this->languages : [])
            ->map(fn ($l) => strtolower(trim((string) $l)))
            ->filter()
            ->reject(fn ($l) => $l === HasTranslations::DEFAULT_LOCALE)
            ->unique()
            ->values()
            ->all();

        return array_merge([HasTranslations::DEFAULT_LOCALE], $list);
    }

    /** Standard-Raumfreigabe für neue Termine (parallel|sequential). */
    public function defaultRoomReleaseMode(): string
    {
        return in_array($this->default_room_release_mode, ['parallel', 'sequential'], true)
            ? $this->default_room_release_mode
            : 'parallel';
    }
}
