<?php

namespace Platform\Reservation\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Services\FunctionSheetService;

/**
 * Laufzettel (Function Sheet) als vollwertige In-App-View: Laufrunden je Pause
 * (Standzeit-Klasse → Tisch → Bestellung). Für den Druck verweist der Button
 * auf die Standalone-Print-Ansicht.
 */
class EventFunctionSheet extends Component
{
    #[Locked]
    public int $eventId;

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
        $this->event; // Team-Scope prüfen (404 bei fremdem Team)
    }

    #[Computed]
    public function event(): Event
    {
        return Event::forTeam(Auth::user()?->current_team_id ?? 0)
            ->findOrFail($this->eventId);
    }

    #[Computed]
    public function sheet(): array
    {
        return app(FunctionSheetService::class)->build($this->event);
    }

    public function render()
    {
        return view('reservation::livewire.event-function-sheet')
            ->layout('platform::layouts.app');
    }
}
