<?php

namespace Platform\Reservation\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Reservation\Models\DropoffSlot;
use Illuminate\Support\Facades\Auth;

class DropoffManager extends Component
{
    public bool $showForm = false;
    public ?int $editingId = null;
    public string $slotDate = '';
    public string $slotTimeFrom = '';
    public string $slotTimeTo = '';
    public int $slotCapacity = 10;
    public string $slotNotes = '';
    public string $filterDate = '';

    protected function getTeamId(): ?int
    {
        $user = Auth::user();
        return $user?->current_team_id;
    }

    #[Computed]
    public function slots(): \Illuminate\Database\Eloquent\Collection
    {
        $query = DropoffSlot::where('team_id', $this->getTeamId())
            ->orderBy('date')
            ->orderBy('time_from');

        if ($this->filterDate) {
            $query->whereDate('date', $this->filterDate);
        }

        return $query->get();
    }

    public function openForm(?int $id = null): void
    {
        $this->showForm = true;
        $this->editingId = $id;

        if ($id) {
            $slot = DropoffSlot::findOrFail($id);
            $this->slotDate     = $slot->date->toDateString();
            $this->slotTimeFrom = $slot->time_from;
            $this->slotTimeTo   = $slot->time_to;
            $this->slotCapacity = $slot->capacity;
            $this->slotNotes    = $slot->notes ?? '';
        } else {
            $this->slotDate     = now()->toDateString();
            $this->slotTimeFrom = '';
            $this->slotTimeTo   = '';
            $this->slotCapacity = 10;
            $this->slotNotes    = '';
        }
    }

    public function save(): void
    {
        $this->validate([
            'slotDate'     => 'required|date',
            'slotTimeFrom' => 'required|date_format:H:i',
            'slotTimeTo'   => 'required|date_format:H:i|after:slotTimeFrom',
            'slotCapacity' => 'required|integer|min:1|max:999',
        ]);

        $data = [
            'team_id'   => $this->getTeamId(),
            'date'      => $this->slotDate,
            'time_from' => $this->slotTimeFrom,
            'time_to'   => $this->slotTimeTo,
            'capacity'  => $this->slotCapacity,
            'notes'     => $this->slotNotes ?: null,
        ];

        if ($this->editingId) {
            DropoffSlot::findOrFail($this->editingId)->update($data);
        } else {
            DropoffSlot::create($data);
        }

        $this->showForm = false;
        $this->editingId = null;
        unset($this->slots);
    }

    public function delete(int $id): void
    {
        DropoffSlot::findOrFail($id)->delete();
        unset($this->slots);
    }

    public function render()
    {
        return view('reservation::livewire.dropoff-manager')
            ->layout('platform::layouts.app');
    }
}
