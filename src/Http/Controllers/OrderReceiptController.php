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
                'bookings' => fn ($q) => $q->withoutGlobalScope('team')->with(['slot', 'table', 'items.menuItem']),
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

        if (! class_exists(\Platform\Core\Services\Documents\PdfRenderer::class)) {
            abort(501, 'PDF-Renderer nicht verfügbar.');
        }

        try {
            $pdf = app(\Platform\Core\Services\Documents\PdfRenderer::class)->render($html);
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
     * Belegdaten: Positionen (flach), MwSt-Aufschlüsselung nach Satz, Summen.
     */
    protected function buildData(Order $order): array
    {
        $lines   = [];
        $byRate  = []; // rate => brutto-Summe

        foreach ($order->bookings as $booking) {
            foreach ($booking->items as $item) {
                $rate  = (float) $item->tax_rate;
                $gross = round((float) $item->quantity * (float) $item->unit_price, 2);

                $lines[] = [
                    'name'       => $item->menuItem?->name ?? 'Produkt',
                    'quantity'   => (int) $item->quantity,
                    'unit_price' => round((float) $item->unit_price, 2),
                    'tax_rate'   => $rate,
                    'total'      => $gross,
                    'slot'       => $booking->slot?->name,
                ];

                $key         = number_format($rate, 2, '.', '');
                $byRate[$key] = round(($byRate[$key] ?? 0) + $gross, 2);
            }
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

        return [
            'order'       => $order,
            'lines'       => $lines,
            'vat'         => $vat,
            'total_net'   => $totalNet,
            'total_vat'   => $totalVat,
            'total_gross' => $totalGross,
            'date'        => $order->bookings->first()?->date,
        ];
    }
}
