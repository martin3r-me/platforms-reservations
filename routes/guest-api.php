<?php

use Illuminate\Support\Facades\Route;
use Platform\Reservation\Http\Controllers\Api\GuestBookingController;
use Platform\Reservation\Http\Controllers\Api\GuestEventController;

// Token-gesicherte Gast-API (Prefix /api/reservation/guest, Middleware api+api.auth).
// Für das externe Gast-Frontend (culinaria.pauseplus.de). Team fest aus der
// Office-Config (RESERVATION_GUEST_TEAM_ID), nicht aus dem Token.
Route::prefix('guest')->name('reservation.api.guest.')->middleware('throttle:120,1')->group(function () {
    // Read
    Route::get('/events', [GuestEventController::class, 'index'])->name('events.index');
    Route::get('/events/{uuid}', [GuestEventController::class, 'show'])->name('events.show');
    Route::get('/events/{uuid}/products', [GuestEventController::class, 'products'])->name('events.products');
    Route::get('/events/{uuid}/floor-plan', [GuestEventController::class, 'floorPlan'])->name('events.floor-plan');

    // Write
    Route::post('/bookings', [GuestBookingController::class, 'store'])->name('bookings.store');
    Route::get('/bookings/{uuid}', [GuestBookingController::class, 'show'])->name('bookings.show');
});
