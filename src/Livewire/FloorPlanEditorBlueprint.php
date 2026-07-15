<?php

namespace Platform\Reservation\Livewire;

/**
 * Blueprint-Variante des Tischplan-Editors: identische Logik wie {@see FloorPlanEditor},
 * nur eine modernere Darstellung des Grundrisses (Blaupausen-Style). Als eigener Reiter,
 * damit der klassische Stand erhalten bleibt.
 */
class FloorPlanEditorBlueprint extends FloorPlanEditor
{
    public function mount(int $venueId, ?int $floorPlanId = null): void
    {
        $this->blueprint = true;
        parent::mount($venueId, $floorPlanId);
    }
}
