<?php

namespace Platform\Reservation\Services;

use Illuminate\Support\Collection;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Models\MenuItem;
use Platform\Reservation\Models\SalesList;
use Platform\Reservation\Support\BundlePriceAllocator;
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

        return $this->linesFrom($selection, $this->allowedItems($event));
    }

    /**
     * Positionen aus einer bereits ermittelten Artikelmenge bauen.
     *
     * Für Wege ohne Event-Verkaufsliste – die Backoffice-Buchung bucht gegen
     * das Team-Menü, nicht gegen eine Verkaufsliste. Der Aufrufer verantwortet,
     * WELCHE Artikel erlaubt sind; Mengenbegrenzung und Preisherkunft (immer
     * aus der DB) bleiben hier, damit beide Wege nicht auseinanderlaufen.
     *
     * @param  array<int, int>  $selection  [menu_item_id => Menge]
     * @param  Collection<int, MenuItem>  $allowed  nach ID indiziert
     * @return Collection<int, array{item: MenuItem, quantity: int, total: float}>
     */
    public function linesFrom(array $selection, Collection $allowed): Collection
    {
        $lines = collect();

        foreach ($selection as $id => $quantity) {
            $item = $allowed->get((int) $id);
            if (!$item) {
                continue; // unbekannte/fremde ID → verworfen
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
            // Bestandteile mitladen: Preis, Steuersatz und Altersgrenze eines
            // Bundles ergeben sich aus ihnen (kein N+1 in der Kalkulation).
            ->with(['components' => fn ($q) => $q->withoutGlobalScope('team')])
            ->get()
            // Ein Bundle fällt weg, sobald ein Bestandteil nicht verfügbar oder
            // nicht freigegeben ist – sonst verkauft man etwas Unlieferbares.
            ->filter(fn (MenuItem $item) => $item->effectivelyAvailable())
            ->keyBy('id');
    }

    /**
     * Warenkorb-Positionen in ABRECHNUNGS-Positionen auflösen.
     *
     * Ein Bundle ist ein Verkaufsobjekt, aber keine Abrechnungsposition: es
     * zerfällt hier in seine Bestandteile, jeder mit eigenem Steuersatz. Diese
     * Stufe ist die einzige Quelle sowohl für die MwSt-Rechnung als auch für die
     * eingefrorenen Positionen – sonst würden Anzeige und Beleg auseinanderlaufen.
     *
     * Preise in CENT, damit die Summe der Bestandteile exakt dem Bundle-Preis
     * entspricht (siehe BundlePriceAllocator).
     *
     * @param  Collection<int, array{item: MenuItem, quantity: int, total: float}>  $lines
     * @return Collection<int, array{item: MenuItem, quantity: int, unit_price_cents: int, tax_rate: float, bundle_ref: ?string, bundle_item: ?MenuItem, bundle_quantity: ?int}>
     */
    public function explodedLines(Collection $lines): Collection
    {
        $out = collect();

        foreach ($lines as $line) {
            /** @var MenuItem $item */
            $item     = $line['item'];
            $quantity = (int) $line['quantity'];

            if (! $item->isBundle()) {
                $out->push([
                    'item'             => $item,
                    'quantity'         => $quantity,
                    'unit_price_cents' => self::toCents($item->price),
                    'tax_rate'         => (float) $item->tax_rate,
                    'bundle_ref'       => null,
                    'bundle_item'      => null,
                    'bundle_quantity'  => null,
                ]);

                continue;
            }

            $components = $item->components;

            if ($components->isEmpty()) {
                continue; // Bundle ohne Inhalt ist nicht verkaufbar
            }

            // Eine Referenz je Bundle-ZEILE: gruppiert die Bestandteile im Beleg
            // und macht das Storno ganzer Bundles möglich.
            $ref = (string) \Symfony\Component\Uid\UuidV7::generate();

            $allocation = BundlePriceAllocator::allocate(
                self::toCents($item->price),
                $components->map(fn (MenuItem $c) => [
                    'key'              => $c->id,
                    'list_price_cents' => self::toCents($c->price),
                    'quantity'         => (int) ($c->pivot->quantity ?? 1),
                ])->all(),
                $quantity,
            );

            $byId = $components->keyBy('id');

            foreach ($allocation as $row) {
                $component = $byId->get($row['key']);

                if (! $component) {
                    continue;
                }

                $out->push([
                    'item'             => $component,
                    'quantity'         => $row['quantity'],
                    'unit_price_cents' => $row['unit_price_cents'],
                    'tax_rate'         => (float) $component->tax_rate,
                    'bundle_ref'       => $ref,
                    'bundle_item'      => $item,
                    // Wie viele Bundles – steht sonst nirgends. bundle_ref
                    // gruppiert die Zeilen, zaehlt sie aber nicht.
                    'bundle_quantity'  => $quantity,
                ]);
            }
        }

        return $out;
    }

    /** Dezimalpreis in ganze Cent (kaufmännisch gerundet). */
    protected static function toCents($price): int
    {
        return (int) round((float) $price * 100);
    }

    /**
     * Bruttosumme aller Positionen.
     *
     * Bewusst aus den AUFGELÖSTEN Positionen: das ist der Betrag, der später als
     * Summe der booking_items in der Datenbank steht und bei Mollie belastet
     * wird. Aus den Anzeigezeilen gerechnet könnten Anzeige und Belastung um
     * Bruchteile auseinanderliegen.
     */
    public function total(Collection $lines): float
    {
        $cents = $this->explodedLines($lines)
            ->sum(fn (array $l) => $l['quantity'] * $l['unit_price_cents']);

        return round($cents / 100, 2);
    }

    /**
     * Bruttosummen je MwSt-Satz (absteigend) – für die Checkout-Zusammenfassung.
     *
     * @return Collection<string, float>
     */
    public function totalsByTaxRate(Collection $lines): Collection
    {
        // Über die aufgelösten Positionen: der Steuersatz des Bundles selbst ist
        // bedeutungslos, maßgeblich sind die Sätze seiner Bestandteile.
        return $this->explodedLines($lines)
            ->groupBy(fn (array $line) => (string) $line['tax_rate'])
            ->map(fn ($group) => round($group->sum(fn (array $l) => $l['quantity'] * $l['unit_price_cents']) / 100, 2))
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

    /**
     * Höchste Altersgrenze im Warenkorb (16 | 18 | null). null = keine.
     */
    public function requiredMinAge(Collection $lines): ?int
    {
        // effectiveMinAge(): bei einem Bundle zählt die höchste Grenze seiner
        // Bestandteile – ein Bier im Bundle macht es zum 18+-Bundle.
        $ages = $lines
            ->map(fn ($line) => $line['item']->effectiveMinAge()?->value)
            ->filter()
            ->values();

        return $ages->isEmpty() ? null : (int) $ages->max();
    }

    /** Enthält der Warenkorb altersbeschränkte Artikel (16/18)? */
    public function containsAgeRestricted(Collection $lines): bool
    {
        return $this->requiredMinAge($lines) !== null;
    }

    /**
     * Einfrier-Attribute für reservation_booking_items (Preis/Steuer aus der DB).
     *
     * @return array<int, array{menu_item_id: int, quantity: int, unit_price: mixed, tax_rate: mixed, bundle_ref: ?string, bundle_menu_item_id: ?int, bundle_quantity: ?int}>
     */
    public function frozenItemAttributes(Collection $lines): array
    {
        // Bundles sind hier bereits in ihre Bestandteile aufgelöst: jede Position
        // trägt ihren eigenen Steuersatz und den anteiligen Preis. bundle_ref hält
        // zusammen, was zu einem Bundle gehört (Beleg-Gruppierung, Storno).
        return $this->explodedLines($lines)->map(fn (array $line) => [
            'menu_item_id'        => $line['item']->id,
            'quantity'            => $line['quantity'],
            'unit_price'          => round($line['unit_price_cents'] / 100, 2), // Preis einfrieren
            'tax_rate'            => $line['tax_rate'],                          // Steuersatz einfrieren
            'bundle_ref'          => $line['bundle_ref'],
            'bundle_menu_item_id' => $line['bundle_item']?->id,
            'bundle_quantity'     => $line['bundle_quantity'],   // Menge einfrieren
        ])->all();
    }
}
