<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Drop-off Slots" icon="heroicon-o-clock" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Drop-off'],
        ]" />
    </x-slot>

    <x-ui-page-container width="contained">
    <div class="space-y-4">
        <x-nx-callout variant="info" icon="heroicon-o-map-pin" title="Abholstationen – in Entwicklung">
            Statt an den Tisch bestellen Gäste künftig an eine <strong>Abholstation</strong>
            (z.&nbsp;B. „Foyer links", „Rang&nbsp;1 Bar") und holen dort in der Pause ab –
            ideal, wenn Tischservice zu eng wird. Küche und Laufzettel gruppieren dann nach Station.
            <br><br>
            Diese Ansicht ersetzt die frühere „Drop-off Slots" und wird als eigenes Feature gebaut.
        </x-nx-callout>
    </div>
    </x-ui-page-container>
</x-ui-page>
