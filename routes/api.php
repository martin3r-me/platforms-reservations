<?php

use Illuminate\Support\Facades\Route;
use Platform\Reservation\Http\Controllers\MollieWebhookController;

// Öffentliche API-Routen (Prefix /api/reservation, api-Middleware, kein CSRF).

// Mollie-Webhook: meldet Statusänderungen einer Zahlung.
Route::post('/payment/webhook', MollieWebhookController::class)
    ->middleware('throttle:60,1')
    ->name('reservation.api.payment.webhook');
