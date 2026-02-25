<?php

return [
    // Scope-Type: 'parent' = root-scoped, 'single' = team-spezifisch
    'scope_type' => 'single',

    'routing' => [
        'mode'   => env('RESERVATION_MODE', 'subdomain'),
        'prefix' => 'reservation',
    ],

    'guard' => 'web',

    'navigation' => [
        'route' => 'reservation.dashboard',
        'icon'  => 'heroicon-o-calendar-days',
        'order' => 50,
    ],

    // Mollie Zahlungsintegration
    'mollie' => [
        'enabled'    => env('MOLLIE_ENABLED', false),
        'api_key'    => env('MOLLIE_API_KEY', ''),
        'webhook_url' => env('MOLLIE_WEBHOOK_URL', ''),
    ],

    // Standard-Währung für Buchungen
    'currency' => env('RESERVATION_CURRENCY', 'EUR'),
];
