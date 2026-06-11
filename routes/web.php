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

// Prefix + Middleware (web/auth/Modul-Guard/Permission) kommen aus ModuleRouter::group().
// Route-Namen daher voll qualifiziert ('reservation.*').

// Dashboard → Buchungsliste
Route::get('/', BookingList::class)->name('reservation.dashboard');
Route::get('/bookings', BookingList::class)->name('reservation.bookings.index');
Route::get('/bookings/create', BookingCreate::class)->name('reservation.bookings.create');

// Termine (Veranstaltungen mit Pausen-Slots und Räumen)
Route::get('/events', EventManager::class)->name('reservation.events.index');

// Venues & Tischpläne verwalten
Route::get('/venues', VenueManager::class)->name('reservation.venues.index');

// Tischplan (Admin)
Route::get('/floor-plan/{venueId}/edit/{floorPlanId?}', FloorPlanEditor::class)
    ->name('reservation.floor-plan.editor');

// Verkaufslisten (segmentierte Sortimente)
Route::get('/sales-lists', SalesListManager::class)->name('reservation.sales-lists.index');

// Menü-Verwaltung
Route::get('/menu', MenuManager::class)->name('reservation.menu.index');
Route::get('/menu/import', MenuImport::class)->name('reservation.menu.import');

// Drop-off Slots
Route::get('/dropoff', DropoffManager::class)->name('reservation.dropoff.index');

// Export
Route::get('/export', Export::class)->name('reservation.export');
