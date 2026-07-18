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

// Artikel eines Termins (aus dessen Verkaufsliste; slot-unabhaengig).
Route::get('/events/{event}/products', [EventController::class, 'products'])
    ->name('reservation.api.events.products');

// Checkout-Felder (Pflicht/optional/aus) + Texte fuers Gast-Formular.
Route::get('/events/{event}/checkout-fields', [EventController::class, 'checkoutFields'])
    ->name('reservation.api.events.checkout-fields');

// Tischplan(e) + Verfuegbarkeit je Pause (optional ?room=&slot=).
Route::get('/events/{event}/floor-plan', [EventController::class, 'floorPlan'])
    ->name('reservation.api.events.floor-plan');

// Bestellung anlegen (Order + N Slot-Buchungen) und Status abfragen.
Route::post('/events/{event}/orders', [EventController::class, 'createOrder'])
    ->name('reservation.api.events.orders.store');
Route::get('/events/{event}/orders/{order}', [EventController::class, 'orderStatus'])
    ->name('reservation.api.events.orders.show');
