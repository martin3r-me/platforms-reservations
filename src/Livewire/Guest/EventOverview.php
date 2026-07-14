<?php

namespace Platform\Reservation\Livewire\Guest;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Reservation\Models\Event;

/**
 * Öffentliche Termin-Übersicht (ohne Auth).
 *
 * M1: zeigt alle veröffentlichten, kommenden Termine der Instanz
 * (Team-Scoping per Slug folgt vor Go-live).
 */
class EventOverview extends Component
{
    public string $search = '';
    public string $filterDate = '';

    #[Computed]
    public function events(): \Illuminate\Database\Eloquent\Collection
    {
        return Event::published()
            ->upcoming()
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->when($this->filterDate !== '', fn ($q) => $q->whereDate('date', $this->filterDate))
            ->with(['venue', 'slots', 'imageFile.variants'])
            ->orderBy('date')
            ->get();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'filterDate');
    }

    public function render()
    {
        return view('reservation::livewire.guest.event-overview')
            ->layout('platform::layouts.guest');
    }
}
