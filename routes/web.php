<?php

use Illuminate\Support\Facades\Route;
use Platform\Reservation\Livewire\BookingList;
use Platform\Reservation\Livewire\BookingCreate;
use Platform\Reservation\Livewire\FloorPlanEditor;
use Platform\Reservation\Livewire\FloorPlanViewer;
use Platform\Reservation\Livewire\MenuManager;
use Platform\Reservation\Livewire\DropoffManager;
use Platform\Reservation\Livewire\Export;

Route::middleware(['web', 'auth'])->prefix('reservation')->name('reservation.')->group(function () {

    // Dashboard → Buchungsliste
    Route::get('/', BookingList::class)->name('dashboard');
    Route::get('/bookings', BookingList::class)->name('bookings.index');
    Route::get('/bookings/create', BookingCreate::class)->name('bookings.create');

    // Tischplan (Admin)
    Route::get('/floor-plan/{venueId}/edit/{floorPlanId?}', FloorPlanEditor::class)
        ->name('floor-plan.editor');

    // Tischplan (Gast / Public)
    Route::get('/book/{floorPlanId}', FloorPlanViewer::class)
        ->withoutMiddleware(['auth'])
        ->name('floor-plan.viewer');

    // Menü-Verwaltung
    Route::get('/menu', MenuManager::class)->name('menu.index');

    // Drop-off Slots
    Route::get('/dropoff', DropoffManager::class)->name('dropoff.index');

    // Export
    Route::get('/export', Export::class)->name('export');
});
