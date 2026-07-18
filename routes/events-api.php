<?php

use Illuminate\Support\Facades\Route;
use Platform\Reservation\Http\Controllers\Api\EventController;

/**
 * Reservation Events-API (Prefix /api/reservation, Passport api.auth).
 *
 * Token-gesicherte Read-Endpunkte für Termine – gleiches Grundmuster wie
 * helpdesk/planner, aber ein fachlicher Events-Endpunkt.
 */
Route::get('/events', [EventController::class, 'index'])
    ->name('reservation.api.events.index');
