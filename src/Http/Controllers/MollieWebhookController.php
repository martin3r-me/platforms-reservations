<?php

namespace Platform\Reservation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Platform\Reservation\Services\MolliePaymentService;

/**
 * Öffentlicher Mollie-Webhook (Server-zu-Server, kein CSRF, keine Auth).
 * Mollie sendet die Payment-ID als `id`; wir holen den Status frisch ab.
 */
class MollieWebhookController
{
    public function __invoke(Request $request, MolliePaymentService $payments): Response
    {
        $molliePaymentId = (string) $request->input('id');

        if ($molliePaymentId !== '') {
            try {
                $payments->syncFromMollie($molliePaymentId);
            } catch (\Throwable $e) {
                report($e);
                // 200 zurückgeben wäre falsch bei echtem Fehler – Mollie wiederholt bei 5xx.
                return response('', 500);
            }
        }

        // Mollie erwartet 200 OK.
        return response('', 200);
    }
}
