<?php

use Illuminate\Support\Facades\Route;
use Platform\Reservation\Livewire\FloorPlanViewer;
use Platform\Reservation\Livewire\Guest\EventOverview;

// Öffentliche Gast-Routen (ohne Auth) – via ModuleRouter::group(..., requireAuth: false).
// Pfade müssen disjunkt zu den Admin-Routen sein (Admin: /events, Gast: /termine).

// Termin-Übersicht für Endkunden
Route::get('/termine', EventOverview::class)->name('reservation.guest.events.index');

// Tischplan-Buchung durch Gäste (Alt-Flow ohne Termin)
Route::get('/book/{floorPlanId}', FloorPlanViewer::class)->name('reservation.floor-plan.viewer');
