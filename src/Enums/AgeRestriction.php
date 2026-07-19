<?php

namespace Platform\Reservation\Enums;

/**
 * Altersgrenze eines Artikels (Jugendschutzgesetz §9):
 *  - Sixteen (16): Bier, Wein, Sekt (gegoren, nicht gebrannt)
 *  - Eighteen (18): Spirituosen / branntweinhaltige Getränke
 * NULL (keine Zuordnung) = keine Altersgrenze.
 */
enum AgeRestriction: int
{
    case Sixteen  = 16;
    case Eighteen = 18;

    /** Anzeige-Label, z. B. "16+". */
    public function label(): string
    {
        return $this->value . '+';
    }

    /** @return array<int,int> erlaubte Werte. */
    public static function values(): array
    {
        return [self::Sixteen->value, self::Eighteen->value];
    }
}
