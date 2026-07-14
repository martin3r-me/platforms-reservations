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
                ['label' => 'Dashboard', 'route' => 'reservation.dashboard', 'icon' => 'heroicon-o-home'],
                ['label' => 'Buchungen', 'route' => 'reservation.bookings.index', 'icon' => 'heroicon-o-calendar-days'],
            ],
        ],
        [
            'group' => 'Verwaltung',
            'items' => [
                ['label' => 'Termine', 'route' => 'reservation.events.index', 'icon' => 'heroicon-o-ticket'],
                ['label' => 'Venues & Tischpläne', 'route' => 'reservation.venues.index', 'icon' => 'heroicon-o-building-storefront'],
                ['label' => 'Verkaufslisten', 'route' => 'reservation.sales-lists.index', 'icon' => 'heroicon-o-queue-list'],
                ['label' => 'Artikel', 'route' => 'reservation.menu.index', 'icon' => 'heroicon-o-rectangle-stack'],
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
        [
            'group' => 'Einstellungen',
            'items' => [
                ['label' => 'Zahlungen', 'route' => 'reservation.settings.payment', 'icon' => 'heroicon-o-credit-card'],
                ['label' => 'Checkout-Texte', 'route' => 'reservation.settings.checkout', 'icon' => 'heroicon-o-document-text'],
            ],
        ],
    ],

    /**
     * Mollie-Zahlungsintegration.
     *
     * Pro Team wird der API-Key i.d.R. in den Modul-Einstellungen hinterlegt
     * (reservation_payment_settings, verschlüsselt). Die ENV-Werte dienen nur
     * als globaler Fallback (z.B. Single-Tenant-Demo).
     */
    'mollie' => [
        'enabled' => env('MOLLIE_ENABLED', false),
        'mode'    => env('MOLLIE_MODE', 'test'), // test | live
        'api_key' => env('MOLLIE_API_KEY', ''),  // Fallback, falls keine Team-Einstellung
    ],

    // Standard-Währung für Buchungen
    'currency' => env('RESERVATION_CURRENCY', 'EUR'),
];
