<?php

namespace Platform\Reservation\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Reservation\Models\FloorPlan;
use Platform\Reservation\Models\Venue;
use Illuminate\Support\Facades\Auth;

class VenueManager extends Component
{
    public int $teamId;

    // Venue-Formular
    public bool   $showVenueForm    = false;
    public ?int   $editingVenueId   = null;
    public string $venueName        = '';
    public string $venueAddress     = '';

    // FloorPlan-Formular
    public bool   $showFloorPlanForm   = false;
    public ?int   $floorPlanVenueId    = null;
    public ?int   $editingFloorPlanId  = null;
    public string $floorPlanName       = '';

    public function mount(): void
    {
        $this->teamId = Auth::user()->current_team_id;
    }

    #[Computed]
    public function venues(): \Illuminate\Database\Eloquent\Collection
    {
        return Venue::where('team_id', $this->teamId)
            ->with(['floorPlans' => fn ($q) => $q->withCount('tables')->orderBy('name')])
            ->orderBy('name')
            ->get();
    }

    // ── Venue ────────────────────────────────────────────────────────

    public function openVenueForm(?int $venueId = null): void
    {
        $this->showVenueForm  = true;
        $this->editingVenueId = $venueId;

        if ($venueId) {
            $venue              = Venue::findOrFail($venueId);
            $this->venueName    = $venue->name;
            $this->venueAddress = $venue->address ?? '';
        } else {
            $this->venueName    = '';
            $this->venueAddress = '';
        }
    }

    public function saveVenue(): void
    {
        $this->validate([
            'venueName'    => 'required|string|max:255',
            'venueAddress' => 'nullable|string|max:500',
        ]);

        if ($this->editingVenueId) {
            Venue::findOrFail($this->editingVenueId)->update([
                'name'    => $this->venueName,
                'address' => $this->venueAddress ?: null,
            ]);
        } else {
            Venue::create([
                'team_id' => $this->teamId,
                'name'    => $this->venueName,
                'address' => $this->venueAddress ?: null,
            ]);
        }

        $this->showVenueForm  = false;
        $this->editingVenueId = null;
        unset($this->venues);
    }

    public function deleteVenue(int $venueId): void
    {
        Venue::findOrFail($venueId)->delete();
        unset($this->venues);
    }

    // ── FloorPlan ────────────────────────────────────────────────────

    public function openFloorPlanForm(int $venueId, ?int $floorPlanId = null): void
    {
        $this->showFloorPlanForm  = true;
        $this->floorPlanVenueId   = $venueId;
        $this->editingFloorPlanId = $floorPlanId;

        if ($floorPlanId) {
            $this->floorPlanName = FloorPlan::findOrFail($floorPlanId)->name;
        } else {
            $this->floorPlanName = '';
        }
    }

    public function saveFloorPlan(): void
    {
        $this->validate(['floorPlanName' => 'required|string|max:255']);

        if ($this->editingFloorPlanId) {
            FloorPlan::findOrFail($this->editingFloorPlanId)
                ->update(['name' => $this->floorPlanName]);
        } else {
            FloorPlan::create([
                'venue_id' => $this->floorPlanVenueId,
                'name'     => $this->floorPlanName,
            ]);
        }

        $this->showFloorPlanForm  = false;
        $this->editingFloorPlanId = null;
        $this->floorPlanVenueId   = null;
        unset($this->venues);
    }

    public function deleteFloorPlan(int $floorPlanId): void
    {
        FloorPlan::findOrFail($floorPlanId)->delete();
        unset($this->venues);
    }

    public function render()
    {
        return view('reservation::livewire.venue-manager')
            ->layout('platform::layouts.app');
    }
}
