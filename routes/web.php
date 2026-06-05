<?php

use Illuminate\Support\Facades\Route;
use Platform\Reservation\Livewire\BookingList;
use Platform\Reservation\Livewire\BookingCreate;
use Platform\Reservation\Livewire\FloorPlanEditor;
use Platform\Reservation\Livewire\MenuManager;
use Platform\Reservation\Livewire\DropoffManager;
use Platform\Reservation\Livewire\Export;
use Platform\Reservation\Livewire\VenueManager;

// Prefix + Middleware (web/auth/Modul-Guard/Permission) kommen aus ModuleRouter::group().
// Route-Namen daher voll qualifiziert ('reservation.*').

// Dashboard → Buchungsliste
Route::get('/', BookingList::class)->name('reservation.dashboard');
Route::get('/bookings', BookingList::class)->name('reservation.bookings.index');
Route::get('/bookings/create', BookingCreate::class)->name('reservation.bookings.create');

// Venues & Tischpläne verwalten
Route::get('/venues', VenueManager::class)->name('reservation.venues.index');

// Tischplan (Admin)
Route::get('/floor-plan/{venueId}/edit/{floorPlanId?}', FloorPlanEditor::class)
    ->name('reservation.floor-plan.editor');

// Menü-Verwaltung
Route::get('/menu', MenuManager::class)->name('reservation.menu.index');

// Drop-off Slots
Route::get('/dropoff', DropoffManager::class)->name('reservation.dropoff.index');

// Export
Route::get('/export', Export::class)->name('reservation.export');
