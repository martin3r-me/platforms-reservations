<?php

namespace Platform\Reservation\Livewire;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Platform\Reservation\Models\CheckoutSession;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Models\MenuItem;
use Platform\Reservation\Models\Table;
use Platform\Reservation\Services\LiveCheckoutService;

/**
 * Wer gerade im Bestellweg steht – als eigene Komponente im VA-Dashboard.
 *
 * Eigene Komponente mit Absicht: Die Karte lädt sich alle 15 Sekunden selbst
 * nach. Hinge das `wire:poll` am VA-Dashboard, rechnete es dabei jedes Mal
 * Buchungen, Auslastung und Raumempfehlung mit – vier Mal pro Minute, obwohl
 * sich davon nichts geändert hat. So bleibt das Nachladen eine Abfrage.
 *
 * Nur lesend. Es gibt hier nichts zu tun: Ein laufender Bestellweg gehört dem
 * Gast, das Haus schaut zu.
 */
class LiveCheckouts extends Component
{
    #[Locked]
    public int $eventId;

    /** Der Vorgang, dessen Warenkorb gerade offen liegt. */
    public ?int $offenerVorgang = null;

    /** Getrennt vom Vorgang, weil x-nx-modal einen Schalter erwartet. */
    public bool $showWarenkorb = false;

    /**
     * Der Termin im Team des Anwenders – oder null.
     *
     * Null statt 404: Die Komponente hängt in einer Seite, die den Zugriff
     * schon geprüft hat. Ein zweiter Abbruch an dieser Stelle würde beim
     * Nachladen die ganze Seite mitreißen.
     */
    #[Computed]
    public function event(): ?Event
    {
        return Event::forTeam(Auth::user()?->current_team_id ?? 0)->find($this->eventId);
    }

    /** @return Collection<int, \Platform\Reservation\Models\CheckoutSession> */
    #[Computed]
    public function laufende(): Collection
    {
        $event = $this->event;

        return $event ? app(LiveCheckoutService::class)->laufende($event) : collect();
    }

    /** @return array{anzahl: int, gaeste: int, warenkorb: float} */
    #[Computed]
    public function summe(): array
    {
        return app(LiveCheckoutService::class)->zusammenfassung($this->laufende);
    }

    public function warenkorbZeigen(int $id): void
    {
        $this->offenerVorgang = $id;
        $this->showWarenkorb  = true;
    }

    public function warenkorbSchliessen(): void
    {
        $this->showWarenkorb  = false;
        $this->offenerVorgang = null;
    }

    /**
     * Der offene Vorgang, nach Pausen gegliedert: Tisch und Artikel.
     *
     * Aus der laufenden Liste gesucht statt neu geladen: Damit gilt hier
     * derselbe Team-Filter, und ein Vorgang, der inzwischen ausgelaufen oder
     * bestellt ist, faellt von selbst heraus - das Modal ist dann leer statt
     * falsch.
     *
     * Die Namen kommen aus dem Menue des Teams, nicht aus der Meldung: Der
     * Shop schickt IDs, und ein hier nachgeschlagener Name ist immer der
     * aktuelle. Ein Artikel, den es nicht mehr gibt, verschwindet nicht - dann
     * stuende im Modal weniger, als der Gast im Korb hat.
     *
     * @return array<int, array{pause: ?string, tisch: ?string, zeilen: array<int, array{menge: int, name: string}>}>
     */
    #[Computed]
    public function warenkorb(): array
    {
        $vorgang = $this->offener;

        if (! $vorgang instanceof CheckoutSession) {
            return [];
        }

        $korb   = $vorgang->items ?? [];
        $tische = $vorgang->tables ?? [];

        // Beide Quellen, nicht nur der Korb: Beim Sitzplatz-Schritt kann ein
        // Tisch an einer Pause haengen, in der noch nichts liegt.
        $slotIds = array_unique(array_merge(array_keys($korb), array_keys($tische)));

        if ($slotIds === []) {
            return [];
        }

        $namen  = $this->artikelnamen($vorgang, $korb);
        $orte   = $this->tischnamen($tische);
        $pausen = $this->event?->slots->keyBy('id') ?? collect();

        // Mehrere Pausen bekommen eine Ueberschrift, eine einzelne nicht: Dort
        // waere "1. Pause" ueber der einzigen Liste nur ein Wort mehr.
        $mehrere = count($slotIds) > 1;

        $ergebnis = [];

        foreach ($slotIds as $slotId) {
            $zeilen = [];

            foreach (($korb[$slotId] ?? []) as $artikelId => $menge) {
                $zeilen[] = [
                    'menge' => (int) $menge,
                    'name'  => $namen[(int) $artikelId] ?? 'Artikel ' . (int) $artikelId . ' (gelöscht)',
                ];
            }

            $ergebnis[] = [
                'pause'  => $mehrere ? $pausen[(int) $slotId]?->displayLabel() : null,
                'tisch'  => $orte[(int) ($tische[$slotId] ?? 0)] ?? null,
                'zeilen' => $zeilen,
            ];
        }

        return $ergebnis;
    }

    /**
     * Artikelnamen im Menue des Teams nachschlagen.
     *
     * @param  array<string, array<string, int>>  $korb
     * @return \Illuminate\Support\Collection<int, string>
     */
    protected function artikelnamen(CheckoutSession $vorgang, array $korb): \Illuminate\Support\Collection
    {
        $ids = collect($korb)->flatMap(fn ($p) => array_keys($p))->map('intval')->unique();

        return $ids->isEmpty()
            ? collect()
            : MenuItem::forTeam((int) $vorgang->team_id)->whereIn('id', $ids)->pluck('name', 'id');
    }

    /**
     * Tischbeschriftungen - und HIER faellt ein fremder Tisch heraus.
     *
     * Gesucht wird nur in den Tischplaenen DIESES Termins. Eine Meldung, die
     * eine beliebige Tisch-Id enthaelt, loest sich damit nicht auf, und im
     * Fenster steht nichts statt eines fremden Tischs.
     *
     * @param  array<string, int>  $tische
     * @return \Illuminate\Support\Collection<int, string>
     */
    protected function tischnamen(array $tische): \Illuminate\Support\Collection
    {
        if ($tische === [] || ! $this->event) {
            return collect();
        }

        $plaene = $this->event->eventRooms()->pluck('floor_plan_id');

        return Table::whereIn('floor_plan_id', $plaene)
            ->whereIn('id', array_map('intval', array_values($tische)))
            ->with('floorPlan')
            ->get()
            ->mapWithKeys(fn (Table $t) => [
                $t->id => $t->label . ($t->floorPlan?->name ? ' · ' . $t->floorPlan->name : ''),
            ]);
    }

    /** Der Vorgang zum offenen Modal - fuer Kopf und Fuss. */
    #[Computed]
    public function offener(): ?CheckoutSession
    {
        return $this->offenerVorgang
            ? $this->laufende->firstWhere('id', $this->offenerVorgang)
            : null;
    }

    public function render()
    {
        return view('reservation::livewire.live-checkouts');
    }
}
