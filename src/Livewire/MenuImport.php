<?php

namespace Platform\Reservation\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;
use Platform\Reservation\Services\MenuItemCsvImporter;

class MenuImport extends Component
{
    use WithFileUploads;

    public $csvFile = null;

    /** Dry-Run-Ergebnis (parse), nichts ist geschrieben */
    public array $previewRows = [];
    public array $parseErrors = [];

    /** Import-Ergebnis */
    public ?int $createdCount = null;
    public ?int $skippedCount = null;

    protected function getTeamId(): ?int
    {
        return Auth::user()?->current_team_id;
    }

    public function updatedCsvFile(): void
    {
        $this->validate([
            'csvFile' => 'required|file|max:2048|mimes:csv,txt',
        ]);

        $this->reset('previewRows', 'parseErrors', 'createdCount', 'skippedCount');

        $result = app(MenuItemCsvImporter::class)->parse(
            file_get_contents($this->csvFile->getRealPath()),
            $this->getTeamId()
        );

        $this->previewRows = $result['rows'];
        $this->parseErrors = $result['errors'];
    }

    public function import(): void
    {
        if (empty($this->previewRows)) {
            return;
        }

        $result = app(MenuItemCsvImporter::class)->import($this->previewRows, $this->getTeamId());

        $this->createdCount = $result['created'];
        $this->skippedCount = $result['skipped'];
        $this->reset('previewRows', 'parseErrors', 'csvFile');
    }

    public function resetImport(): void
    {
        $this->reset('previewRows', 'parseErrors', 'csvFile', 'createdCount', 'skippedCount');
    }

    public function render()
    {
        return view('reservation::livewire.menu-import')
            ->layout('platform::layouts.app');
    }
}
