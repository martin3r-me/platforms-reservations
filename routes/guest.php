<?php

use Illuminate\Support\Facades\Route;
use Platform\Reservation\Livewire\FloorPlanViewer;
use Platform\Reservation\Livewire\Guest\CheckoutWizard;
use Platform\Reservation\Livewire\Guest\EventOverview;
use Platform\Reservation\Livewire\Guest\PaymentReturn;

// Öffentliche Gast-Routen (ohne Auth) – via ModuleRouter::group(..., requireAuth: false).
// Pfade müssen disjunkt zu den Admin-Routen sein (Admin: /events, Gast: /termine).

// Marken-Logo (gebündeltes Modul-Asset)
Route::get('/brand/logo', [\Platform\Reservation\Http\Controllers\BrandAssetController::class, 'logo'])
    ->name('reservation.guest.brand.logo');

// Termin-Übersicht für Endkunden
Route::get('/termine', EventOverview::class)->name('reservation.guest.events.index');

// Buchungs-Wizard für einen Termin (Gastdaten → Produkte → Sitzplatz → Checkout)
Route::get('/termine/{uuid}', CheckoutWizard::class)->name('reservation.guest.checkout');

// Rückkehr von der Mollie-Bezahlseite (zeigt Zahlungsstatus der Buchung)
Route::get('/payment/return/{uuid}', PaymentReturn::class)->name('reservation.guest.payment.return');

// Tischplan-Buchung durch Gäste (Alt-Flow ohne Termin)
Route::get('/book/{floorPlanId}', FloorPlanViewer::class)->name('reservation.floor-plan.viewer');
