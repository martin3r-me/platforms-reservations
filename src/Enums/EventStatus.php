<?php

namespace Platform\Reservation\Enums;

/**
 * Lebenszyklus eines Termins. „Vergangen/gelaufen" ist bewusst KEIN Status,
 * sondern wird aus dem Datum abgeleitet (date < heute) – so kann der Status
 * nicht mit der Realität driften.
 */
enum EventStatus: string
{
    case Draft     = 'draft';       // Entwurf (nicht öffentlich)
    case Published = 'published';   // veröffentlicht, Vorbestellung offen
    case Closed    = 'closed';      // Bestellschluss – veröffentlicht, aber keine Vorbestellung mehr
    case Cancelled = 'cancelled';   // abgesagt

    public function label(): string
    {
        return match ($this) {
            self::Draft     => 'Entwurf',
            self::Published => 'Veröffentlicht',
            self::Closed    => 'Bestellschluss',
            self::Cancelled => 'Abgesagt',
        };
    }

    /** Badge-Variante für die UI. */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::Draft     => 'muted',
            self::Published => 'success',
            self::Closed    => 'warning',
            self::Cancelled => 'danger',
        };
    }

    /** Werte für Filter/Validierung. */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
