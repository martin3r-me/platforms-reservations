<?php

use Illuminate\Support\Facades\Route;
use Platform\Reservation\Livewire\FloorPlanViewer;

// Öffentliche Gast-Routen (ohne Auth) – via ModuleRouter::group(..., requireAuth: false).
// Tischplan-Buchung durch Gäste.
Route::get('/book/{floorPlanId}', FloorPlanViewer::class)->name('reservation.floor-plan.viewer');
