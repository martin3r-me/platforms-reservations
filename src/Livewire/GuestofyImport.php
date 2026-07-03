<?php

namespace Platform\Reservation\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Reservation\Models\Venue;
use Platform\Reservation\Services\GuestofyImporter;

/**
 * Import von Räumen/Tischplänen aus dem Alt-System (Guestofy).
 * Ablauf: URL eingeben → Vorschau abrufen → in ein Venue importieren.
 */
class GuestofyImport extends Component
{
    public string $sourceUrl = '';
    public ?int $venueId = null;
    public string $newVenueName = '';

    /** @var array<int, array{name:string, tables:array}> */
    public array $previewRooms = [];

    public ?array $result = null;

    protected function getTeamId(): int
    {
        return (int) (Auth::user()?->current_team_id ?? 0);
    }

    #[Computed]
    public function venues(): \Illuminate\Database\Eloquent\Collection
    {
        return Venue::where('team_id', $this->getTeamId())->orderBy('name')->get();
    }

    public function fetchPreview(): void
    {
        $this->validate([
            'sourceUrl' => 'required|url',
        ], [
            'sourceUrl.required' => 'Bitte die URL des Alt-Systems angeben.',
            'sourceUrl.url'      => 'Bitte eine gültige URL angeben (inkl. https://).',
        ]);

        $this->reset('previewRooms', 'result');

        try {
            $this->previewRooms = app(GuestofyImporter::class)->fetchRooms($this->sourceUrl);
        } catch (\Throwable $e) {
            $this->addError('sourceUrl', $e->getMessage());
            return;
        }

        if (empty($this->previewRooms)) {
            $this->addError('sourceUrl', 'Keine Räume gefunden.');
        }
    }

    public function import(): void
    {
        if (empty($this->previewRooms)) {
            return;
        }

        // Venue bestimmen: bestehendes wählen oder neues anlegen.
        if ($this->venueId) {
            $venue = Venue::where('team_id', $this->getTeamId())->findOrFail($this->venueId);
        } else {
            $this->validate([
                'newVenueName' => 'required|string|max:255',
            ], [
                'newVenueName.required' => 'Bitte ein Venue wählen oder einen Namen für ein neues Venue angeben.',
            ]);

            $venue = Venue::create([
                'team_id'   => $this->getTeamId(),
                'name'      => $this->newVenueName,
                'is_active' => true,
            ]);
            $this->venueId = $venue->id;
        }

        $this->result = app(GuestofyImporter::class)->importRooms($this->previewRooms, $venue->id);
        $this->previewRooms = [];
        $this->newVenueName = '';
    }

    public function resetImport(): void
    {
        $this->reset('previewRooms', 'result', 'sourceUrl', 'newVenueName');
    }

    public function render()
    {
        return view('reservation::livewire.guestofy-import')
            ->layout('platform::layouts.app');
    }
}
