<?php

namespace Platform\Reservation\Livewire;

use Livewire\Component;

/**
 * Modul-Sidebar (wird von der Platform-Hauptsidebar eingebunden).
 */
class Sidebar extends Component
{
    public function render()
    {
        return view('reservation::livewire.sidebar');
    }
}
