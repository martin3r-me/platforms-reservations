@php
    // Nur für die Brotkrume – die Kategorien selbst stehen im Nav-Partial.
    $kategorieLabel = [
        'veranstaltung'      => 'Veranstaltung & Plätze',
        'checkout'           => 'Gast-Checkout',
        'zahlung'            => 'Zahlung',
        'belege'             => 'Belege & Druck',
        'benachrichtigungen' => 'Benachrichtigungen',
        'buchhaltung'        => 'Buchhaltung',
    ][$tab] ?? 'Einstellungen';
@endphp
<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Einstellungen" icon="heroicon-o-cog-6-tooth" />
    </x-slot>

    {{-- Kategorien in der zweiten Sidebar. Elf Abschnitte untereinander waren
         zu lang zum Suchen und zu viele für Reiter. --}}
    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Einstellungen" width="w-64" :defaultOpen="true" side="left">
            @include('reservation::partials.settings-nav', ['aktiv' => $tab, 'modus' => 'inline'])
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'PausePlus', 'href' => route('reservation.dashboard'), 'icon' => 'calendar-days'],
            ['label' => 'Einstellungen', 'href' => route('reservation.settings.checkout')],
            ['label' => $kategorieLabel],
        ]">
            {{-- Rueckmeldung in der Leiste: Die liegt ausserhalb des scrollenden
                 Bereichs und ist damit immer sichtbar. Ein Hinweis oben im Inhalt
                 war nach dem Speichern weiter unten gar nicht zu sehen.

                 Farbe per style statt Utility-Klasse und der Timer in x-data –
                 beides schon einmal an dieser Stelle schiefgegangen. --}}
            <span
                x-data="{ show: false, timer: null }"
                x-on:reservation-saved.window="
                    show = true;
                    clearTimeout(timer);
                    timer = setTimeout(() => show = false, 2500);
                "
                x-show="show"
                x-transition.opacity.duration.200ms
                role="status"
                aria-live="polite"
                class="flex items-center gap-1 text-xs"
                style="display: none; color: var(--nx-success);"
            >
                @svg('heroicon-o-check-circle', 'w-4 h-4')
                <span>Gespeichert</span>
            </span>
            <x-nx-button variant="primary" wire:click="save">Speichern</x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
    <div class="max-w-2xl space-y-5">

    {{-- Je Kategorie die zugehörigen Abschnitte. Alles bleibt EINE Komponente
         mit EINEM Speichern: Livewire hält auch die Eigenschaften, die gerade
         nicht gezeichnet sind, ein Wechsel verliert also keine Eingabe. Und der
         Speichern-Knopf steht in der Aktionsleiste außerhalb des scrollenden
         Bereichs, ist also in jeder Kategorie sichtbar. --}}
    @switch($tab)
        @case('checkout')
            @include('reservation::partials.settings.anmeldefelder')
            @include('reservation::partials.settings.shop-sprachen')
            @include('reservation::partials.settings.checkout-texte')
            @break

        @case('zahlung')
            @include('reservation::partials.settings.mollie')
            @include('reservation::partials.settings.selbst-storno')
            @break

        @case('belege')
            @include('reservation::partials.settings.rechnungsangaben')
            @include('reservation::partials.settings.beleg-design')
            @include('reservation::partials.settings.bon-druck')
            @break

        @case('benachrichtigungen')
            @include('reservation::partials.settings.bestaetigungsmail')
            @break

        @case('buchhaltung')
            @include('reservation::partials.settings.umsatz')
            @include('reservation::partials.settings.datev')
            @break

        @default
            @include('reservation::partials.settings.termine')
    @endswitch

    </div>
    </x-ui-page-container>
</x-ui-page>
