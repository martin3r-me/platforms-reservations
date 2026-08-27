<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Schnittbefehl zwischen zwei Bons
    |--------------------------------------------------------------------------
    |
    | Nur für den Sammel-Bon (alle Bons einer Veranstaltung in EINEM Auftrag).
    | Star-Geräte: ESC d 3 = Teilschnitt mit Vorschub.
    |
    | Versteht der Drucker die Folge nicht, kommt ein durchgehender Bon statt
    | einzelner - unschön, aber es geht nichts verloren. Dann hier die für das
    | Gerät passende Folge eintragen.
    |
    */
    'bon_cut' => "\x1b\x64\x33",

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

    /**
     * Gast-API (/api/reservation/guest/*): token-gesichert (Passport/api.auth).
     * Das Team kommt NICHT aus dem Token, sondern fest aus der Office-Config –
     * eine Instanz bedient genau ein Team. Ohne gesetztes Team ist die API
     * inaktiv (503).
     */
    'guest_api' => [
        'team_id' => env('RESERVATION_GUEST_TEAM_ID'),
    ],

    /**
     * Gäste-Terminübersicht (Kopfbereich). Logo/Text/Farbe je Kunde anpassbar.
     */
    'guest' => [
        // Logo: leer = gebündeltes Culinaria-Logo (Route reservation.guest.brand.logo).
        'logo_url' => env('RESERVATION_GUEST_LOGO', ''),
        'eyebrow'  => env('RESERVATION_GUEST_EYEBROW', 'PausePlus'),
        'intro'    => env('RESERVATION_GUEST_INTRO', 'Drinks & Snacks vorbestellen und die Veranstaltungspausen in der Stadthalle genießen.'),
        'accent'   => env('RESERVATION_GUEST_ACCENT', '#285567'),
    ],
];
