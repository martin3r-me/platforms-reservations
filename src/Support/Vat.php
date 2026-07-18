<?php

namespace Platform\Reservation\Support;

/**
 * MwSt-Rechnung für die Preise dieses Moduls.
 *
 * Konvention: alle gespeicherten Preise (MenuItem.price, booking_items.unit_price)
 * sind BRUTTOPREISE (inkl. MwSt). Aus einem Bruttobetrag und dem Steuersatz
 * werden Netto- und Steuerbetrag extrahiert. vat wird zuerst gerundet und netto
 * als Rest berechnet, sodass netto + vat == brutto exakt aufgeht.
 */
class Vat
{
    /**
     * @return array{net: float, vat: float, gross: float}
     */
    public static function fromGross(float $gross, float $rate): array
    {
        $gross = round($gross, 2);
        $vat   = $rate > 0 ? round($gross * $rate / (100 + $rate), 2) : 0.0;

        return [
            'net'   => round($gross - $vat, 2),
            'vat'   => $vat,
            'gross' => $gross,
        ];
    }
}
