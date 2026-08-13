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
            // Ein Bundle ist EINE Position: Bundle-Preis und was drin ist.
            // Bewusst OHNE Einzelbeträge je Bestandteil – die entstehen erst
            // durch die interne Aufteilung, der Gast hat sie nie gesehen, und
            // sie ergeben je nach Menge krumme oder ungleiche Werte. Die
            // MwSt-Aufschlüsselung unten bleibt davon unberührt: Sie wird aus
            // den echten Positionen gerechnet, nicht aus dieser Darstellung.
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

                // Bewirtungsbeleg (flache Aufstellung): Einzelartikel wie bisher,
                // ein Bundle dagegen als EINE Zeile mit dem Bundle-Preis. Der
                // Inhalt steht als Text darunter, ohne Beträge.
                if ($item->bundle_ref === null) {
                    $lines[] = $entry + [
                        'slot'     => $booking->slot?->name,
                        'contents' => null,
                    ];
                } else {
                    $flachKey = 'b' . $item->bundle_ref;

                    if (! isset($lines[$flachKey])) {
                        $lines[$flachKey] = [
                            'name'     => $item->bundleMenuItem?->name ?? 'Bundle',
                            'quantity' => max(1, (int) ($item->bundle_quantity ?? 1)),
                            // Ein Bundle hat keinen einzelnen Steuersatz – die
                            // Aufschlüsselung steht unten.
                            'tax_rate' => null,
                            'total'    => 0.0,
                            'slot'     => $booking->slot?->name,
                            'contents' => [],
                        ];
                    }

                    $lines[$flachKey]['total'] = round($lines[$flachKey]['total'] + $gross, 2);

                    $teil = $item->menuItem?->name ?? 'Produkt';
                    $lines[$flachKey]['contents'][$teil] =
                        ($lines[$flachKey]['contents'][$teil] ?? 0) + (int) $item->quantity;
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
                        'type'     => 'bundle',
                        'name'     => $item->bundleMenuItem?->name ?? 'Bundle',
                        'quantity' => max(1, (int) ($item->bundle_quantity ?? 1)),
                        'total'    => 0.0,
                        // Nur Name und Menge – keine Beträge, keine Steuersätze.
                        'contents' => [],
                    ];
                }

                $index = $bundleSeen[$ref];
                $blocks[$index]['total'] = round($blocks[$index]['total'] + $gross, 2);

                $teil = $entry['name'];
                $blocks[$index]['contents'][$teil] =
                    ($blocks[$index]['contents'][$teil] ?? 0) + (int) $item->quantity;
            }

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
