<?php

return [
    /**
     * Routing – wie die übrigen Module über den Pfad (…/reservation/…).
     */
    'routing' => [
        'mode'   => env('RESERVATION_MODE', 'path'),
        'prefix' => 'reservation',
    ],

    'guard' => 'web',

    /**
     * Hauptnavigation.
     */
    'navigation' => [
        'route' => 'reservation.dashboard',
        'icon'  => 'heroicon-o-calendar-days',
        'order' => 50,
    ],

    /**
     * Sidebar-Struktur (Metadaten + wird von Livewire\Sidebar gerendert).
     */
    'sidebar' => [
        [
            'group' => 'Übersicht',
            'items' => [
                ['label' => 'Buchungen', 'route' => 'reservation.bookings.index', 'icon' => 'heroicon-o-calendar-days'],
            ],
        ],
        [
            'group' => 'Verwaltung',
            'items' => [
                ['label' => 'Termine', 'route' => 'reservation.events.index', 'icon' => 'heroicon-o-ticket'],
                ['label' => 'Venues & Tischpläne', 'route' => 'reservation.venues.index', 'icon' => 'heroicon-o-building-storefront'],
                ['label' => 'Verkaufslisten', 'route' => 'reservation.sales-lists.index', 'icon' => 'heroicon-o-queue-list'],
                ['label' => 'Menü', 'route' => 'reservation.menu.index', 'icon' => 'heroicon-o-rectangle-stack'],
                ['label' => 'Artikel-Import', 'route' => 'reservation.menu.import', 'icon' => 'heroicon-o-arrow-up-tray'],
                ['label' => 'Drop-off', 'route' => 'reservation.dropoff.index', 'icon' => 'heroicon-o-clock'],
            ],
        ],
        [
            'group' => 'Finanzen',
            'items' => [
                ['label' => 'Umsatz', 'route' => 'reservation.finance.index', 'icon' => 'heroicon-o-banknotes'],
            ],
        ],
        [
            'group' => 'Auswertung',
            'items' => [
                ['label' => 'Export', 'route' => 'reservation.export', 'icon' => 'heroicon-o-arrow-down-tray'],
            ],
        ],
    ],

    /**
     * Mollie-Zahlungsintegration.
     */
    'mollie' => [
        'enabled'     => env('MOLLIE_ENABLED', false),
        'api_key'     => env('MOLLIE_API_KEY', ''),
        'webhook_url' => env('MOLLIE_WEBHOOK_URL', ''),
    ],

    // Standard-Währung für Buchungen
    'currency' => env('RESERVATION_CURRENCY', 'EUR'),
];
