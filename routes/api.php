<?php

use Illuminate\Support\Facades\Route;
use Platform\Reservation\Http\Controllers\MollieWebhookController;

// Öffentliche API-Routen (Prefix /api/reservation, api-Middleware, kein CSRF).

// Mollie-Webhook: meldet Statusänderungen einer Zahlung.
Route::post('/payment/webhook', MollieWebhookController::class)
    ->middleware('throttle:60,1')
    ->name('reservation.api.payment.webhook');

// Freundliche Info bei direktem Browser-Aufruf (GET) – der Webhook selbst ist POST-only.
Route::get('/payment/webhook', fn () => response()->json([
    'ok'      => true,
    'message' => 'Dieser Endpunkt empfängt Mollie-Zahlungs-Webhooks (POST). Ein direkter Aufruf im Browser ist ohne Funktion.',
]))->name('reservation.api.payment.webhook.info');
