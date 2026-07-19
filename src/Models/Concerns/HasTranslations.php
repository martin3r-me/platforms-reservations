<?php

namespace Platform\Reservation\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Platform\Reservation\Models\Translation;

/**
 * Generische Mehrsprachigkeit (#522) für beliebige Modelle und beliebige
 * Sprachen. Das Modell definiert die übersetzbaren Felder via $translatable.
 *
 * DE ist die Basis-/Default-Sprache und lebt in den Modell-Spalten; die
 * Übersetzungstabelle enthält nur abweichende Sprachen. Fehlt eine Übersetzung,
 * greift der Fallback auf die Basis-Sprache.
 *
 * In Listen `->with('translations')` eager-laden (kein N+1).
 */
trait HasTranslations
{
    public const DEFAULT_LOCALE = 'de';

    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    /** @return array<int,string> übersetzbare Felder dieses Modells. */
    public function translatableFields(): array
    {
        return property_exists($this, 'translatable') ? $this->translatable : [];
    }

    public function isTranslatableField(string $field): bool
    {
        return in_array($field, $this->translatableFields(), true);
    }

    /**
     * Wert eines Feldes in der gewünschten Sprache (Fallback: Basis-Sprache).
     */
    public function translate(string $field, ?string $locale = null): mixed
    {
        $base   = $this->getAttribute($field);
        $locale = $locale ?: self::DEFAULT_LOCALE;

        if ($locale === self::DEFAULT_LOCALE || ! $this->isTranslatableField($field)) {
            return $base;
        }

        $match = $this->translations->first(
            fn (Translation $t) => $t->locale === $locale && $t->field === $field
        );

        $value = $match?->value;

        return ($value === null || $value === '') ? $base : $value;
    }

    /**
     * Übersetzung setzen/löschen. locale == Basis-Sprache schreibt in die
     * Modell-Spalte; leerer Wert entfernt die Übersetzung.
     */
    public function setTranslation(string $field, string $locale, ?string $value): void
    {
        if (! $this->isTranslatableField($field)) {
            return;
        }

        if ($locale === self::DEFAULT_LOCALE) {
            $this->update([$field => $value]);
            return;
        }

        if ($value === null || $value === '') {
            $this->translations()->where('locale', $locale)->where('field', $field)->delete();
            $this->unsetRelation('translations');
            return;
        }

        Translation::updateOrCreate(
            [
                'translatable_type' => $this->getMorphClass(),
                'translatable_id'   => $this->getKey(),
                'locale'            => $locale,
                'field'             => $field,
            ],
            ['value' => $value],
        );

        $this->unsetRelation('translations');
    }

    /**
     * Alle Übersetzungen gruppiert: [ locale => [ field => value ] ].
     *
     * @return array<string, array<string, string>>
     */
    public function translationsByLocale(): array
    {
        $out = [];

        foreach ($this->translations as $t) {
            $out[$t->locale][$t->field] = $t->value;
        }

        return $out;
    }
}
