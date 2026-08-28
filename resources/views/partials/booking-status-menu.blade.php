{{-- Kebab-Menü einer Buchungszeile: No-Show, Abgeschlossen, Zurücknehmen.

     Erwartet $booking und einen umgebenden Alpine-Zustand aus
     partials/booking-status-menu-state.blade.php.

     Steht bewusst als LETZTES Element der Zeile, ganz rechts. Die übrigen
     Symbole erscheinen je nach Status; säße das Menü zwischen ihnen, wanderte
     es von Zeile zu Zeile. Als letztes vor dem rechten Rand liegt es immer an
     derselben Stelle. --}}
<div @click.stop>
    <x-nx-button icon variant="ghost" type="button" x-ref="kebab"
        @click="open ? open = false : auf()" title="Status ändern">
        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <circle cx="10" cy="4" r="1.4"/><circle cx="10" cy="10" r="1.4"/><circle cx="10" cy="16" r="1.4"/>
        </svg>
    </x-nx-button>

    {{-- "fixed" statt "absolute", anders als bei x-nx-dropdown: Die Tabelle
         steckt in einem Kasten mit "overflow-x-auto", und der beschneidet nach
         CSS auch senkrecht – ein Menü in den unteren Zeilen wäre dort
         abgeschnitten. Der Preis ist die Lage von Hand (auf()) und das
         Schließen beim Scrollen.

         Aussehen und der Name "open" bewusst wie in x-nx-dropdown: Nur so
         passen die x-nx-dropdown-item darin, die selbst "open = false" setzen. --}}
    <div x-ref="menu" x-show="open" style="display:none" x-transition
        @click.outside="open = false"
        :style="{ top: oben + 'px', right: rechts + 'px' }"
        class="fixed z-50 w-56 rounded-[8px] border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] p-1 shadow-[var(--nx-shadow-pop)]">
        @if ($booking->status !== 'no_show')
            <x-nx-dropdown-item wire:click="askNoShow({{ $booking->id }})">
                @svg('heroicon-o-user-minus', 'w-4 h-4') <span>No-Show</span>
            </x-nx-dropdown-item>
        @endif
        @if ($booking->status !== 'completed')
            <x-nx-dropdown-item wire:click="markCompleted({{ $booking->id }})">
                @svg('heroicon-o-check-circle', 'w-4 h-4') <span>Abgeschlossen</span>
            </x-nx-dropdown-item>
        @endif
        @if (in_array($booking->status, ['no_show', 'completed'], true))
            <x-nx-dropdown-divider />
            {{-- Nicht „Zurück auf Bestätigt": Wohin es geht, hängt an der
                 Bestellung – bei unbezahlten Shop-Buchungen auf ausstehend.
                 Die Rückfrage sagt es. --}}
            <x-nx-dropdown-item wire:click="askReopen({{ $booking->id }})">
                @svg('heroicon-o-arrow-uturn-left', 'w-4 h-4') <span>Zurücknehmen</span>
            </x-nx-dropdown-item>
        @endif
    </div>
</div>
