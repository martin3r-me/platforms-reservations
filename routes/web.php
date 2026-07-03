<?php

use Illuminate\Support\Facades\Route;
use Platform\Reservation\Livewire\BookingList;
use Platform\Reservation\Livewire\BookingCreate;
use Platform\Reservation\Livewire\FloorPlanEditor;
use Platform\Reservation\Livewire\MenuManager;
use Platform\Reservation\Livewire\MenuImport;
use Platform\Reservation\Livewire\DropoffManager;
use Platform\Reservation\Livewire\Export;
use Platform\Reservation\Livewire\VenueManager;
use Platform\Reservation\Livewire\SalesListManager;
use Platform\Reservation\Livewire\EventManager;
use Platform\Reservation\Livewire\EventOrders;

// Prefix + Middleware (web/auth/Modul-Guard/Permission) kommen aus ModuleRouter::group().
// Route-Namen daher voll qualifiziert ('reservation.*').

// Dashboard (Kennzahlen, nächste Termine, neueste Buchungen)
Route::get('/', \Platform\Reservation\Livewire\Dashboard::class)->name('reservation.dashboard');
Route::get('/bookings', BookingList::class)->name('reservation.bookings.index');
Route::get('/bookings/create', BookingCreate::class)->name('reservation.bookings.create');

// Termine (Veranstaltungen mit Pausen-Slots und Räumen)
Route::get('/events', EventManager::class)->name('reservation.events.index');

// Küchen-Übersicht: Gesamtbestellungen eines Termins je Pause
Route::get('/events/{eventId}/orders', EventOrders::class)->name('reservation.events.orders');

// Venues & Tischpläne verwalten
Route::get('/venues', VenueManager::class)->name('reservation.venues.index');
Route::get('/venues/import', \Platform\Reservation\Livewire\GuestofyImport::class)->name('reservation.venues.import');

// Tischplan (Admin)
Route::get('/floor-plan/{venueId}/edit/{floorPlanId?}', FloorPlanEditor::class)
    ->name('reservation.floor-plan.editor');

// Verkaufslisten (segmentierte Sortimente)
Route::get('/sales-lists', SalesListManager::class)->name('reservation.sales-lists.index');

// Menü-Verwaltung
Route::get('/menu', MenuManager::class)->name('reservation.menu.index');
Route::get('/menu/import', MenuImport::class)->name('reservation.menu.import');
Route::get('/menu/import/vorlage', \Platform\Reservation\Http\Controllers\MenuImportSampleController::class)->name('reservation.menu.import.sample');

// Drop-off Slots
Route::get('/dropoff', DropoffManager::class)->name('reservation.dropoff.index');

// Export
Route::get('/export', Export::class)->name('reservation.export');

// Finanzen (Umsatz nach Monaten/Terminen)
Route::get('/finance', \Platform\Reservation\Livewire\Finance::class)->name('reservation.finance.index');

// Zahlungseinstellungen (Mollie)
Route::get('/settings/payment', \Platform\Reservation\Livewire\PaymentSettings::class)->name('reservation.settings.payment');

// Checkout-Texte (18+, Rechtstext, Datenschutz-Link)
Route::get('/settings/checkout', \Platform\Reservation\Livewire\CheckoutSettings::class)->name('reservation.settings.checkout');
