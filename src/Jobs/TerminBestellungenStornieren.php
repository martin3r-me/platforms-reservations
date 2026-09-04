<?php

namespace Platform\Reservation\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Services\EventCancellationService;

/**
 * Storniert und erstattet die Bestellungen eines abgesagten Termins.
 *
 * In der Warteschlange und nicht im Klick: Fünfzig Bestellungen sind fünfzig
 * Aufrufe bei Mollie. Läuft die Warteschlange nicht (Treiber „sync"), führt
 * Laravel das hier sofort aus – der Weg stimmt also in beiden Fällen.
 *
 * Nur die ID, nicht das Modell: Ein serialisiertes Event würde beim
 * Wiederauftauchen in der Warteschlange über den Team-Scope geladen, und dort
 * ist niemand angemeldet.
 *
 * KEIN zweiter Versuch. Es geht um Geld, und die Fehler stehen im Log statt
 * blind wiederholt zu werden. Gefährlich wäre eine Wiederholung zwar nicht –
 * eine erstattete Zahlung erkennt der Dienst an refunded_at –, aber bei einer
 * Massenaktion über echtes Geld ist „einmal und dann hinsehen" die richtige
 * Vorgabe.
 */
class TerminBestellungenStornieren implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(
        public int $eventId,
        public ?string $grund = null,
    ) {
    }

    public function handle(EventCancellationService $dienst): void
    {
        $event = Event::withoutGlobalScope('team')->find($this->eventId);

        if (! $event) {
            return;
        }

        $ergebnis = $dienst->alleStornieren($event, $this->grund);

        Log::info('[Reservation] Termin-Absage: Bestellungen storniert', [
            'event_id'  => $event->id,
            'storniert' => $ergebnis['storniert'],
            'fehler'    => $ergebnis['fehler'],
        ]);
    }
}
