<?php

namespace Platform\Reservation\Http\Controllers;

use Illuminate\Http\Request;
use Platform\Reservation\Models\Order;
use Platform\Reservation\Support\Vat;

/**
 * Rendert einen Beleg zu einer Bestellung als PDF (Core-PdfRenderer / Browsershot)
 * und streamt ihn. Aufruf über eine signierte Gast-URL. type = confirmation
 * (Bestellbestätigung) | bewirtungsbeleg.
 */
class OrderReceiptController
{
    public function __invoke(Request $request, string $uuid)
    {
        $type = in_array($request->query('type'), ['confirmation', 'bewirtungsbeleg'], true)
            ? $request->query('type')
            : 'confirmation';

        $order = Order::withoutGlobalScope('team')
            ->where('uuid', $uuid)
            ->with([
                'event',
                'bookings' => fn ($q) => $q->withoutGlobalScope('team')->with(['slot', 'table.floorPlan', 'items.menuItem', 'items.bundleMenuItem']),
                'payment',
            ])
            ->first();

        if (! $order) {
            abort(404);
        }

        // Bewirtungsbeleg nur mit Unternehmensdaten (Firma).
        if ($type === 'bewirtungsbeleg' && ! $order->hasBusinessData()) {
            abort(403, 'Bewirtungsbeleg nur mit Unternehmensdaten verfügbar.');
        }

        $data = $this->buildData($order);
        $view = $type === 'bewirtungsbeleg' ? 'reservation::pdf.bewirtungsbeleg' : 'reservation::pdf.order-receipt';
        $html = view($view, $data)->render();

        try {
            $pdf = $this->renderPdf($html);
        } catch (\Throwable $e) {
            report($e);
            abort(500, 'PDF konnte nicht erstellt werden.');
        }

        $filename = ($type === 'bewirtungsbeleg' ? 'Bewirtungsbeleg' : 'Bestellbestaetigung')
            . '-' . substr($order->uuid, 0, 8) . '.pdf';

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    /**
     * PDF rendern – bevorzugt dompdf (reines PHP, kein Browser), sonst der
     * Core-PdfRenderer (Browsershot). dompdf ist robust auf allen Instanzen.
     */
    protected function renderPdf(string $html): string
    {
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4')->output();
        }

        if (class_exists(\Platform\Core\Services\Documents\PdfRenderer::class)) {
            return app(\Platform\Core\Services\Documents\PdfRenderer::class)->render($html);
        }

        abort(501, 'PDF-Renderer nicht verfügbar.');
    }

    /**
     * Belegdaten: Positionen (flach), MwSt-Aufschlüsselung nach Satz, Summen.
     */
    protected function buildData(Order $order): array
    {
        $lines   = [];
        $groups  = []; // je Buchung/Pause: Slot, Tisch, Raum + Positionen
        $byRate  = []; // rate => brutto-Summe

        foreach ($order->bookings as $booking) {
            // Blöcke statt einer flachen Liste: ein Bundle wird zu EINEM Block
            // mit Überschrift und seinen Bestandteilen darunter (Lösung A aus
            // der Abstimmung). Nur so lässt sich die MwSt je Bestandteil
            // ausweisen und der Gast sieht trotzdem, was er als Bundle gekauft hat.
            $blocks     = [];
            $bundleSeen = [];   // bundle_ref => Index in $blocks

            foreach ($booking->items as $item) {
                $rate  = (float) $item->tax_rate;
                $gross = round((float) $item->quantity * (float) $item->unit_price, 2);

                $entry = [
                    'name'       => $item->menuItem?->name ?? 'Produkt',
                    'quantity'   => (int) $item->quantity,
                    'unit_price' => round((float) $item->unit_price, 2),
                    'tax_rate'   => $rate,
                    'total'      => $gross,
                ];

                // Für den Bewirtungsbeleg (flache Aufstellung) mitführen, aus
                // welchem Bundle eine Position stammt. Gesplittete Zeilen
                // desselben Artikels werden auch hier zusammengefasst.
                $flachKey = ($item->bundle_ref ?? 'x') . '|' . (int) $item->menu_item_id . '|' . $rate;

                if (isset($lines[$flachKey])) {
                    $lines[$flachKey]['quantity'] += (int) $item->quantity;
                    $lines[$flachKey]['total']     = round($lines[$flachKey]['total'] + $gross, 2);
                } else {
                    $lines[$flachKey] = $entry + [
                        'slot'        => $booking->slot?->name,
                        'bundle_name' => $item->bundleMenuItem?->name,
                    ];
                }

                $key          = number_format($rate, 2, '.', '');
                $byRate[$key] = round(($byRate[$key] ?? 0) + $gross, 2);

                $ref = $item->bundle_ref;

                if ($ref === null) {
                    $blocks[] = ['type' => 'item'] + $entry;

                    continue;
                }

                if (! isset($bundleSeen[$ref])) {
                    $bundleSeen[$ref] = count($blocks);
                    $blocks[] = [
                        'type'  => 'bundle',
                        'name'  => $item->bundleMenuItem?->name ?? 'Bundle',
                        'total' => 0.0,
                        'items' => [],
                    ];
                }

                $index = $bundleSeen[$ref];
                $blocks[$index]['total'] = round($blocks[$index]['total'] + $gross, 2);

                // Bestandteile je Artikel addieren. Der Preis-Verteiler splittet
                // eine Position in mehrere Zeilen, damit Menge × Einzelpreis
                // exakt aufgeht – bei 3 Bier zu 16,61 € etwa 2 × 5,54 € plus
                // 1 × 5,53 €. Auf einem Beleg liest sich das, als koste
                // dasselbe Bier unterschiedlich viel.
                $teilKey = (int) $item->menu_item_id;

                if (! isset($blocks[$index]['items'][$teilKey])) {
                    $blocks[$index]['items'][$teilKey] = [
                        'name'     => $entry['name'],
                        'quantity' => 0,
                        // Bewusst KEIN Einzelpreis: Er ist ein interner
                        // Aufteilungswert, den der Gast nie gesehen hat, und
                        // Menge × Einzelpreis ginge nach dem Zusammenfassen
                        // nicht mehr auf. Gekauft wurde das Bundle.
                        'tax_rate' => $rate,
                        'total'    => 0.0,
                    ];
                }

                $blocks[$index]['items'][$teilKey]['quantity'] += (int) $item->quantity;
                $blocks[$index]['items'][$teilKey]['total'] = round(
                    $blocks[$index]['items'][$teilKey]['total'] + $gross,
                    2
                );
            }

            foreach ($blocks as &$block) {
                if (($block['type'] ?? 'item') === 'bundle') {
                    $block['items'] = array_values($block['items']);
                }
            }
            unset($block);

            $groups[] = [
                'slot'   => $booking->slot?->displayLabel() ?? ($booking->slot?->name ?? 'Pause'),
                'table'  => $booking->table?->label,
                'room'   => $booking->table?->floorPlan?->name,
                'blocks' => $blocks,
            ];
        }

        $vat       = [];
        $totalNet  = 0.0;
        $totalVat  = 0.0;
        $totalGross = 0.0;

        ksort($byRate);
        foreach ($byRate as $rate => $gross) {
            $b = Vat::fromGross($gross, (float) $rate);
            $vat[] = ['tax_rate' => (float) $rate, 'net' => $b['net'], 'vat' => $b['vat'], 'gross' => $b['gross']];
            $totalNet   = round($totalNet + $b['net'], 2);
            $totalVat   = round($totalVat + $b['vat'], 2);
            $totalGross = round($totalGross + $b['gross'], 2);
        }

        // Einstellungen einmal laden – Aussteller UND Branding kommen daher.
        $settings = \Platform\Reservation\Models\CheckoutSetting::forTeam((int) $order->team_id);

        return [
            'order'       => $order,
            'issuer'      => $settings->issuer(),
            'branding'    => $settings->receiptBranding(),
            'lines'       => array_values($lines),
            'groups'      => $groups,
            'vat'         => $vat,
            'total_net'   => $totalNet,
            'total_vat'   => $totalVat,
            'total_gross' => $totalGross,
            'date'        => $order->bookings->first()?->date,
        ];
    }
}
