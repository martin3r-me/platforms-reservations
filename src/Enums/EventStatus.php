<?php

namespace Platform\Reservation\Enums;

/**
 * Lebenszyklus eines Termins. „Vergangen/gelaufen" ist bewusst KEIN Status,
 * sondern wird aus dem Datum abgeleitet (date < heute) – so kann der Status
 * nicht mit der Realität driften.
 */
enum EventStatus: string
{
    case Draft     = 'draft';       // Arbeitsstand, für Gäste unsichtbar
    case Announced = 'announced';   // steht im Shop, Vorbestellung noch nicht offen
    case Published = 'published';   // veröffentlicht, Vorbestellung offen
    case Closed    = 'closed';      // Bestellschluss – steht im Shop, aber keine Vorbestellung mehr
    case Cancelled = 'cancelled';   // abgesagt

    /**
     * Status, die der Gast im Shop zu sehen bekommt. Alles außer dem Entwurf:
     * der ist ein Arbeitsstand und geht niemanden etwas an, „Bestellschluss"
     * und „Abgesagt" dagegen schon – der Termin existiert ja, der Gast soll
     * nur wissen, woran er ist.
     *
     * @return array<int, string>
     */
    public static function publicValues(): array
    {
        return [
            self::Announced->value,
            self::Published->value,
            self::Closed->value,
            self::Cancelled->value,
        ];
    }

    /** Aus diesem Status heraus kann bestellt werden (Frist zählt extra). */
    public function allowsOrders(): bool
    {
        return $this === self::Published;
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft     => 'Entwurf',
            self::Announced => 'Bald verfügbar',
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
            self::Announced => 'info',
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
