<?php

namespace Platform\Reservation\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Services\FunctionSheetService;

/**
 * Druckbarer Laufzettel (#523) eines Termins: pro Pause die Laufrunden mit
 * Tisch/Bestellung/Artikel. Standalone-HTML mit Druck-Styles.
 */
class FunctionSheetController
{
    public function __invoke(Request $request, int $eventId, FunctionSheetService $service): View
    {
        $event = Event::findOrFail($eventId);

        // Optional: Status-Filter überschreiben (?statuses=confirmed,completed).
        $statuses = null;
        if ($request->filled('statuses')) {
            $statuses = array_values(array_filter(array_map('trim', explode(',', (string) $request->query('statuses')))));
        }

        return view('reservation::function-sheet', [
            'sheet' => $service->build($event, $statuses),
        ]);
    }
}
