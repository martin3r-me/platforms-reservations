<?php

use Illuminate\Support\Facades\Route;
use Platform\Reservation\Http\Controllers\Api\EventDatawarehouseController;

/**
 * Reservation Datawarehouse API (Prefix /api/reservation, Passport api.auth).
 *
 * Token-gesicherte Read-Endpunkte, die vom zentralen Datawarehouse abgeholt
 * werden – gleiches Muster wie helpdesk/planner.
 */
Route::get('/events/datawarehouse', [EventDatawarehouseController::class, 'index'])
    ->name('reservation.api.events.datawarehouse');
Route::get('/events/datawarehouse/health', [EventDatawarehouseController::class, 'health'])
    ->name('reservation.api.events.datawarehouse.health');
