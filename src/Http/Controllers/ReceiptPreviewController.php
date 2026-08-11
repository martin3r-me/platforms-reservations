<?php

namespace Platform\Reservation\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Platform\Reservation\Models\CheckoutSetting;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Models\Order;
use Platform\Reservation\Support\Vat;

/**
 * Testbeleg mit den aktuellen Branding-Einstellungen.
 *
 * Ohne Vorschau müsste man für jede Farb- oder Logoänderung eine echte
 * Bestellung durchspielen – das macht aus einer Einstellung ein Support-Thema.
 *
 * Die Beispielbestellung wird NICHT gespeichert: die Models werden nur im
 * Speicher aufgebaut und den Vorlagen übergeben.
 */
class ReceiptPreviewController
{
    public function __invoke()
    {
        $teamId = Auth::user()?->current_team_id;

        if (! $teamId) {
            abort(403);
        }

        $settings = CheckoutSetting::forTeam((int) $teamId);
        $html     = view('reservation::pdf.order-receipt', $this->sampleData($settings, (int) $teamId))->render();

        try {
            $pdf = $this->renderPdf($html);
        } catch (\Throwable $e) {
            report($e);
            abort(500, 'Vorschau konnte nicht erstellt werden.');
        }

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Beleg-Vorschau.pdf"',
        ]);
    }

    /**
     * Beispieldaten in der Form, die order-receipt.blade.php erwartet.
     * Bewusst zwei Steuersätze, damit die MwSt-Aufschlüsselung sichtbar wird.
     */
    protected function sampleData(CheckoutSetting $settings, int $teamId): array
    {
        $event = new Event(['name' => 'Beispiel-Veranstaltung', 'date' => now()]);

        $order = new Order([
            'team_id'    => $teamId,
            'first_name' => 'Erika',
            'last_name'  => 'Mustermann',
            'email'      => 'erika@example.org',
            'status'     => Order::STATUS_CONFIRMED,
        ]);
        $order->uuid = 'VORSCHAU-' . str_pad((string) $teamId, 4, '0', STR_PAD_LEFT);
        $order->setRelation('event', $event);
        $order->setRelation('bookings', collect());

        $items = [
            ['name' => 'Brezel',      'quantity' => 2, 'unit_price' => 2.50, 'tax_rate' => 7.0],
            ['name' => 'Bier 0,3 l',  'quantity' => 2, 'unit_price' => 4.20, 'tax_rate' => 19.0],
            ['name' => 'Apfelschorle','quantity' => 1, 'unit_price' => 3.10, 'tax_rate' => 19.0],
        ];

        $byRate = [];
        foreach ($items as $i => $line) {
            $items[$i]['total'] = round($line['quantity'] * $line['unit_price'], 2);
            $byRate[(string) $line['tax_rate']] = round(($byRate[(string) $line['tax_rate']] ?? 0) + $items[$i]['total'], 2);
        }

        $vat = [];
        $totalNet = $totalVat = $totalGross = 0.0;
        ksort($byRate);

        foreach ($byRate as $rate => $gross) {
            $b = Vat::fromGross($gross, (float) $rate);
            $vat[] = ['tax_rate' => (float) $rate] + $b;
            $totalNet   = round($totalNet + $b['net'], 2);
            $totalVat   = round($totalVat + $b['vat'], 2);
            $totalGross = round($totalGross + $b['gross'], 2);
        }

        return [
            'order'       => $order,
            'issuer'      => $settings->issuer(),
            'branding'    => $settings->receiptBranding(),
            'lines'       => $items,
            'groups'      => [[
                'slot'  => 'Pause',
                'table' => 'Stehtisch 1',
                'room'  => 'Saal',
                'items' => $items,
            ]],
            'vat'         => $vat,
            'total_net'   => $totalNet,
            'total_vat'   => $totalVat,
            'total_gross' => $totalGross,
            'date'        => now(),
        ];
    }

    /** Wie im OrderReceiptController: dompdf bevorzugt, sonst Core-Renderer. */
    protected function renderPdf(string $html): string
    {
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4')->output();
        }

        if (class_exists(\Platform\Core\Services\Documents\PdfRenderer::class)) {
            return app(\Platform\Core\Services\Documents\PdfRenderer::class)->render($html);
        }

        throw new \RuntimeException('Kein PDF-Renderer verfügbar.');
    }
}
