<?php

namespace Platform\Reservation\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Reservation\Exceptions\FloorPlanInUseException;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Models\PickupStation;
use Platform\Reservation\Models\Venue;

/**
 * Abholstationen pflegen – nach Haus gruppiert, wie die Tischpläne.
 *
 * Eine Station ist der zweite mögliche Zielort einer Buchung: „Foyer links",
 * „Rang 1 Bar". Sie gehört dem Venue, nicht dem Raum – deshalb steht sie hier
 * neben den Tischplänen und nicht in deren Editor.
 *
 * Was hier bewusst FEHLT, ist ein Platz-Feld. `capacity_per_slot` heißt „wie
 * viele Gäste das Haus in einer Pause an dieser Stelle bedienen kann", nicht
 * „wie viele Stühle dastehen". Die Beschriftung sagt es, und leer heißt
 * unbegrenzt – nicht null.
 */
class StationManager extends Component
{
    public int $teamId;

    public bool   $showForm   = false;
    public ?int   $editingId  = null;
    public ?int   $formVenueId = null;

    public string $stationName        = '';
    public string $stationDescription = '';
    public string $stationCapacity    = '';
    public bool   $stationActive      = true;

    /** Rückfrage vor dem Löschen – wie bei Räumen und Venues. */
    public bool   $showDeleteConfirm = false;
    public ?int   $pendingId         = null;
    public string $pendingName       = '';

    public function mount(): void
    {
        $this->teamId = (int) (Auth::user()?->current_team_id ?? 0);
    }

    /**
     * Häuser mit ihren Stationen.
     *
     * Die anstehenden Termine werden mitgezählt, damit der Löschknopf gar nicht
     * erst anklickbar ist, wenn die Station eingeplant ist – ein Knopf, der
     * immer nur eine Fehlermeldung bringt, ist eine Falle. Dasselbe Muster wie
     * bei den Tischplänen.
     */
    #[Computed]
    public function venues(): \Illuminate\Database\Eloquent\Collection
    {
        return Venue::where('team_id', $this->teamId)
            ->with(['pickupStations' => fn ($q) => $q
                ->withCount(['eventStations as anstehende_termine_count' => fn ($q2) => $q2
                    ->whereHas('event', fn ($q3) => $q3->upcoming()->where('status', '!=', Event::STATUS_CANCELLED))])
                ->with('floorPlan')
                ->orderBy('sort_order')
                ->orderBy('name')])
            ->orderBy('name')
            ->get();
    }

    public function openForm(int $venueId, ?int $id = null): void
    {
        $this->showForm   = true;
        $this->formVenueId = $venueId;
        $this->editingId  = $id;
        $this->resetValidation();

        if ($id) {
            $station = $this->eigene($id);

            $this->stationName        = $station->name;
            $this->stationDescription = $station->description ?? '';
            $this->stationCapacity    = (string) ($station->capacity_per_slot ?? '');
            $this->stationActive      = $station->is_active;

            return;
        }

        $this->stationName        = '';
        $this->stationDescription = '';
        $this->stationCapacity    = '';
        $this->stationActive      = true;
    }

    public function closeForm(): void
    {
        $this->showForm  = false;
        $this->editingId = null;
    }

    public function save(): void
    {
        $this->validate([
            'stationName'        => 'required|string|max:255',
            'stationDescription' => 'nullable|string|max:1000',
            // Leer heisst unbegrenzt. Deshalb nullable und nicht "min:0":
            // Eine 0 waere eine Station, an der nichts geht - wer das will,
            // schaltet sie ab.
            'stationCapacity'    => 'nullable|integer|min:1|max:9999',
        ], [], [
            'stationName'     => 'Name',
            'stationCapacity' => 'Gäste je Pause',
        ]);

        $daten = [
            'name'              => trim($this->stationName),
            'description'       => trim($this->stationDescription) ?: null,
            'capacity_per_slot' => $this->stationCapacity === '' ? null : (int) $this->stationCapacity,
            'is_active'         => $this->stationActive,
        ];

        if ($this->editingId) {
            $this->eigene($this->editingId)->update($daten);
        } else {
            // Das Venue gegen das eigene Team prüfen, nicht der Anfrage glauben:
            // formVenueId kommt aus dem Browser.
            $venue = Venue::where('team_id', $this->teamId)->findOrFail($this->formVenueId);

            PickupStation::create($daten + [
                'team_id'  => $this->teamId,
                'venue_id' => $venue->id,
            ]);
        }

        $this->closeForm();
        unset($this->venues);
    }

    public function askDelete(int $id): void
    {
        $station = $this->eigene($id);

        $this->pendingId         = $station->id;
        $this->pendingName       = $station->name;
        $this->showDeleteConfirm = true;
    }

    public function deleteAndCloseModal(): void
    {
        $this->showDeleteConfirm = false;

        if ($this->pendingId) {
            try {
                $this->eigene($this->pendingId)->delete();
            } catch (FloorPlanInUseException $e) {
                // Der Löschknopf ist bei eingeplanten Stationen schon
                // abgeschaltet. Diese Meldung fängt den Fall ab, dass jemand
                // die Seite offen hat, während anderswo ein Termin entsteht.
                session()->flash('station_error', $e->getMessage());
            }
        }

        $this->pendingId = null;
        unset($this->venues);
    }

    /** Nur Stationen des eigenen Teams – 404 statt fremder Ressource. */
    protected function eigene(int $id): PickupStation
    {
        return PickupStation::forTeam($this->teamId)->findOrFail($id);
    }

    public function render()
    {
        return view('reservation::livewire.station-manager')
            ->layout('platform::layouts.app');
    }
}
