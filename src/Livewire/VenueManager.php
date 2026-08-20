<?php

namespace Platform\Reservation\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Platform\Reservation\Models\FloorPlan;
use Platform\Reservation\Models\Venue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VenueManager extends Component
{
    // Aus Auth im mount abgeleitet – darf clientseitig nicht überschrieben
    // werden, sonst würde eine Venue unter fremdem Team angelegt.
    #[Locked]
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

    /**
     * Tischplan kopieren – mit Tischen, Grundriss und Raumumriss.
     *
     * Gedacht zum Ausprobieren: Am Original hängen Termine und Buchungen, an
     * der Kopie nichts. Wer eine Bestuhlung durchspielen will, tut das an der
     * Kopie und lässt den laufenden Betrieb in Ruhe.
     *
     * Der Grundriss wird NICHT als Datei kopiert, sondern mitbenutzt – eine
     * Datei, zwei Verweise. Das spart Platz und hält die Kopie sofort
     * einsatzbereit; damit ein Bildwechsel an der einen Seite die andere nicht
     * beschädigt, löscht HasContextImage ein noch verwendetes File nicht mehr.
     *
     * Nicht mitkopiert werden die Atmosphäre-Bilder: Die hängen als eigene
     * Dateien am Raum und sind Marketing, keine Bestuhlung.
     */
    public function duplicateFloorPlan(int $floorPlanId): void
    {
        // findOrFail über den Team-Scope – eine fremde ID läuft ins Leere.
        $original = FloorPlan::with('tables')->findOrFail($floorPlanId);

        DB::transaction(function () use ($original) {
            $kopie = $original->replicate(['created_at', 'updated_at']);
            $kopie->name = $this->kopieName($original);
            $kopie->save();

            // Tische einzeln, damit die creating-Hooks (team_id) greifen.
            foreach ($original->tables as $tisch) {
                $neu = $tisch->replicate(['created_at', 'updated_at']);
                $neu->floor_plan_id = $kopie->id;
                $neu->save();
            }
        });

        unset($this->venues);
    }

    /** "ROSSINI (Kopie)", beim zweiten Mal "(Kopie 2)" – sonst raten alle. */
    protected function kopieName(FloorPlan $original): string
    {
        $basis = mb_substr($original->name, 0, 240) . ' (Kopie)';
        $name  = $basis;
        $lauf  = 2;

        while (FloorPlan::where('venue_id', $original->venue_id)->where('name', $name)->exists()) {
            $name = $basis . ' ' . $lauf++;
        }

        return $name;
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
