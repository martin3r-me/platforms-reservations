<?php

namespace Platform\Reservation\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Services\EventBriefingService;

/**
 * Druckbare Abend-Übersicht (One-Pager) eines Termins: Kennzahlen, Pausen,
 * Top-Speisen und Gästeliste. Standalone-HTML mit Druck-Styles.
 */
class EventBriefingController
{
    public function __invoke(Request $request, int $eventId, EventBriefingService $service): View
    {
        $event = Event::findOrFail($eventId);

        $statuses = null;
        if ($request->filled('statuses')) {
            $statuses = array_values(array_filter(array_map('trim', explode(',', (string) $request->query('statuses')))));
        }

        return view('reservation::event-briefing', [
            'sheet' => $service->build($event, $statuses),
        ]);
    }
}
