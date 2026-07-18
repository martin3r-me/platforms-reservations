<?php

namespace Platform\Reservation\Services;

use Illuminate\Support\Collection;
use Platform\Reservation\Models\MenuItem;
use Platform\Reservation\Support\Vat;

/**
 * Autoritative Warenkorb-Kalkulation für den Gast-Checkout.
 *
 * Kapselt die Preis-/MwSt-/Alters-Logik, die bisher als #[Computed] direkt im
 * CheckoutWizard lag, damit sie sowohl vom aktuellen Livewire-Flow als auch von
 * der künftigen Gast-API (POST /api/guest/bookings) genutzt werden kann. Preise
 * und Steuersätze kommen immer aus der DB (nie aus dem Request).
 *
 * Reine Extraktion – Verhalten identisch zum bisherigen CheckoutWizard.
 */
class CartCalculator
{
    /**
     * Warenkorb-Positionen für eine Auswahl (menu_item_id => Menge).
     *
     * @param array<int, int> $selection
     * @return Collection<int, array{item: MenuItem, quantity: int, total: float}>
     */
    public function lines(array $selection): Collection
    {
        if (empty($selection)) {
            return collect();
        }

        return MenuItem::with('category')
            ->whereIn('id', array_keys($selection))
            ->get()
            ->map(fn (MenuItem $item) => [
                'item'     => $item,
                'quantity' => $selection[$item->id],
                'total'    => $item->price * $selection[$item->id],
            ]);
    }

    /** Bruttosumme aller Positionen. */
    public function total(Collection $lines): float
    {
        return (float) $lines->sum('total');
    }

    /**
     * Bruttosummen je MwSt-Satz (absteigend) – für die Checkout-Zusammenfassung.
     *
     * @return Collection<string, float>
     */
    public function totalsByTaxRate(Collection $lines): Collection
    {
        return $lines
            ->groupBy(fn ($line) => $line['item']->tax_rate)
            ->map(fn ($group) => $group->sum('total'))
            ->sortKeysDesc();
    }

    /**
     * Netto/MwSt/Brutto je Satz (gemischte MwSt, autoritativ) – für Beleg/API.
     * Nicht in der Gast-UI verdrahtet; nutzt {@see Vat}.
     *
     * @return Collection<string, array{net: float, vat: float, gross: float}>
     */
    public function taxBreakdown(Collection $lines): Collection
    {
        return $this->totalsByTaxRate($lines)
            ->map(fn (float $gross, $rate) => Vat::fromGross($gross, (float) $rate));
    }

    /** Enthält der Warenkorb altersbeschränkte (alkoholische) Artikel? */
    public function containsAgeRestricted(Collection $lines): bool
    {
        return $lines->contains(fn ($line) => $line['item']->is_alcoholic);
    }

    /**
     * Einfrier-Attribute für reservation_booking_items (Preis/Steuer aus der DB).
     *
     * @return array<int, array{menu_item_id: int, quantity: int, unit_price: mixed, tax_rate: mixed}>
     */
    public function frozenItemAttributes(Collection $lines): array
    {
        return $lines->map(fn ($line) => [
            'menu_item_id' => $line['item']->id,
            'quantity'     => $line['quantity'],
            'unit_price'   => $line['item']->price,   // Preis einfrieren
            'tax_rate'     => $line['item']->tax_rate, // Steuersatz einfrieren
        ])->all();
    }
}
