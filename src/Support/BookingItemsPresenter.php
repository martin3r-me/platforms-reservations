<?php

namespace Platform\Reservation\Support;

/**
 * Buchungspositionen für die ANZEIGE aufbereiten.
 *
 * Zwei Dinge macht diese Klasse, und beide aus demselben Grund: Was gespeichert
 * ist, taugt nicht als Darstellung.
 *
 * 1. Ein Bundle wird zu EINER Position mit Bundle-Preis. Gespeichert sind seine
 *    Bestandteile – nötig für MwSt je Satz, Allergene und Standzeiten –, aber
 *    gekauft wurde das Bundle. Die anteiligen Beträge hat der Gast nie gesehen.
 *
 * 2. Bestandteile werden je Artikel zusammengefasst und OHNE Beträge gezeigt.
 *    Der Preis-Verteiler splittet eine Position in mehrere Zeilen, damit
 *    Menge × Einzelpreis exakt aufgeht. Bei drei Bier zu 16,61 € entsteht so
 *    2 × 5,54 € plus 1 × 5,53 € – das liest sich, als koste dasselbe Bier
 *    unterschiedlich viel. Ein einheitlicher Preis ist dort arithmetisch
 *    unmöglich: 16,61 € lassen sich nicht durch drei teilen.
 *
 * Einzelartikel bleiben, wie sie sind – mit Einzelpreis und Steuersatz.
 *
 * Zentral, weil Beleg, Gast-API und die Buchungsansicht im Backoffice sonst
 * jeweils eigene Fassungen hätten. Genau so ist die uneinheitliche Darstellung
 * überhaupt erst entstanden.
 */
class BookingItemsPresenter
{
    /**
     * @param  iterable<\Platform\Reservation\Models\BookingItem>  $items
     * @return array<int, array{
     *     name: string, quantity: int, unit_price: ?float, tax_rate: ?float,
     *     total: float, is_bundle: bool, contents: array<string, int>,
     *     notes: array<int, string>
     * }>
     */
    public static function blocks(iterable $items): array
    {
        $out     = [];
        $gesehen = [];   // bundle_ref => Index in $out

        foreach ($items as $item) {
            $gross = round((float) $item->unit_price * (int) $item->quantity, 2);
            $name  = $item->menuItem?->name ?? 'Gelöschter Artikel';
            $ref   = $item->bundle_ref;

            if ($ref === null) {
                $out[] = [
                    'name'       => $name,
                    'quantity'   => (int) $item->quantity,
                    'unit_price' => round((float) $item->unit_price, 2),
                    'tax_rate'   => round((float) $item->tax_rate, 2),
                    'total'      => $gross,
                    'is_bundle'  => false,
                    'contents'   => [],
                    // Gast-Hinweise zur Position ("ohne Zwiebeln") – die
                    // brauchen Küche und Service.
                    'notes'      => array_filter([$item->notes]),
                ];

                continue;
            }

            if (! isset($gesehen[$ref])) {
                $gesehen[$ref] = count($out);
                $out[] = [
                    'name' => $item->bundleMenuItem?->name ?? 'Bundle',
                    // Aus Bestellungen vor Einführung der Spalte kann die Menge
                    // fehlen; dann 1 statt einer geratenen Zahl.
                    'quantity'   => max(1, (int) ($item->bundle_quantity ?? 1)),
                    // Ein Bundle hat weder einen sinnvollen Einzelpreis noch
                    // EINEN Steuersatz – seine Bestandteile haben verschiedene.
                    'unit_price' => null,
                    'tax_rate'   => null,
                    'total'      => 0.0,
                    'is_bundle'  => true,
                    'contents'   => [],
                    // Hinweise an Bestandteilen laufen im Bundle zusammen.
                    'notes'      => [],
                ];
            }

            $index = $gesehen[$ref];
            $out[$index]['total'] = round($out[$index]['total'] + $gross, 2);
            $out[$index]['contents'][$name] = ($out[$index]['contents'][$name] ?? 0) + (int) $item->quantity;

            if (filled($item->notes) && ! in_array($item->notes, $out[$index]['notes'], true)) {
                $out[$index]['notes'][] = $item->notes;
            }
        }

        return $out;
    }

    /** Inhalt eines Bundles als Text: "3× BIER · 1× das". */
    public static function contentsLabel(array $contents): string
    {
        $teile = [];

        foreach ($contents as $name => $menge) {
            $teile[] = $menge . '× ' . $name;
        }

        return implode(' · ', $teile);
    }
}
