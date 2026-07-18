<?php

namespace Platform\Reservation\Services;

use Illuminate\Support\Collection;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Models\MenuItem;
use Platform\Reservation\Models\SalesList;
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
    /** Obergrenze je Position (Manipulations-/Missbrauchsschutz). */
    public const MAX_QUANTITY_PER_ITEM = 99;

    /**
     * Autoritative Warenkorb-Positionen für eine Auswahl (menu_item_id => Menge).
     *
     * Nur Artikel aus der freigegebenen Verkaufsliste des Events werden
     * berücksichtigt (fremde/unbekannte/nicht sichtbare IDs verworfen); Mengen
     * werden auf ganze Zahlen in [1, MAX_QUANTITY_PER_ITEM] begrenzt. Preis und
     * Steuer stammen aus der DB.
     *
     * @param array<int, int> $selection
     * @return Collection<int, array{item: MenuItem, quantity: int, total: float}>
     */
    public function lines(array $selection, Event $event): Collection
    {
        if (empty($selection)) {
            return collect();
        }

        $allowed = $this->allowedItems($event);
        $lines   = collect();

        foreach ($selection as $id => $quantity) {
            $item = $allowed->get((int) $id);
            if (!$item) {
                continue; // nicht in der freigegebenen Verkaufsliste → verworfen
            }

            $quantity = (int) $quantity;
            if ($quantity < 1) {
                continue;
            }
            $quantity = min(self::MAX_QUANTITY_PER_ITEM, $quantity);

            $lines->push([
                'item'     => $item,
                'quantity' => $quantity,
                'total'    => $item->price * $quantity,
            ]);
        }

        return $lines->values();
    }

    /**
     * Gast-sichtbare Artikel der Event-Verkaufsliste, nach ID indiziert.
     *
     * Bewusst scope-sicher (withoutGlobalScope + explizites Team des Events),
     * damit die Kalkulation auch im authentifizierten API-Kontext (Gast-API,
     * Service-Token-User) auf das richtige Team auflöst – nicht nur im
     * auth-losen Livewire-Gastflow.
     */
    protected function allowedItems(Event $event): Collection
    {
        $salesList = $event->sales_list_id
            ? SalesList::withoutGlobalScope('team')->find($event->sales_list_id)
            : SalesList::withoutGlobalScope('team')
                ->where('team_id', $event->team_id)
                ->where('is_default', true)
                ->first();

        if (!$salesList) {
            return collect();
        }

        return $salesList->menuItems()
            ->withoutGlobalScope('team')
            ->where('approval_status', MenuItem::APPROVAL_APPROVED)
            ->where('available', true)
            ->get()
            ->keyBy('id');
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
