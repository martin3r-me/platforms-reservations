<?php

use Illuminate\Support\Facades\Route;
use Platform\Reservation\Livewire\Guest\OrderCancel;
use Platform\Reservation\Livewire\Guest\PaymentReturn;

// Öffentliche Gast-Routen (ohne Auth) – via ModuleRouter::group(..., requireAuth: false).
//
// Das Gast-Shop-Frontend (Terminübersicht, Checkout, Sitzplan-Buchung) ist als
// eigenes Projekt ausgelagert (culinaria.pauseplus.de) und spricht das Office nur
// über die Gast-API an (routes/guest-api.php). Hier bleiben nur die Lifecycle-Seiten,
// die an Office-Mails/Payment hängen: Payment-Return (Mollie-Fallback), Selbst-Storno,
// Beleg-PDF – plus die gebündelten Marken-Assets.

// Marken-Logo (gebündeltes Modul-Asset)
Route::get('/brand/logo', [\Platform\Reservation\Http\Controllers\BrandAssetController::class, 'logo'])
    ->name('reservation.guest.brand.logo');

// Standard-Stimmungsbild (Stadthalle) für die Ultrawide-Ambient-Zone
Route::get('/brand/hero', [\Platform\Reservation\Http\Controllers\BrandAssetController::class, 'hero'])
    ->name('reservation.guest.brand.hero');

// Rückkehr von der Mollie-Bezahlseite (zeigt Zahlungsstatus der Buchung).
// Fallback-Landing, falls beim Anlegen keine redirect_url (Gast-Frontend) übergeben
// wurde; normal übernimmt die Payment-Return-Seite die Gast-Subdomain.
Route::get('/payment/return/{uuid}', PaymentReturn::class)->name('reservation.guest.payment.return');

// Selbst-Storno per signiertem Link aus der Bestätigungs-Mail
Route::get('/order/{uuid}/cancel', OrderCancel::class)
    ->middleware('signed')
    ->name('reservation.guest.order.cancel');

// Beleg-PDF (Bestellbestätigung | Bewirtungsbeleg) per signierter URL
Route::get('/order/{uuid}/receipt', \Platform\Reservation\Http\Controllers\OrderReceiptController::class)
    ->middleware('signed')
    ->name('reservation.guest.order.receipt');
