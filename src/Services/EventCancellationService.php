<?php

namespace Platform\Reservation\Services;

use Illuminate\Support\Facades\Log;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Models\Order;

/**
 * Alle Bestellungen eines abgesagten Termins stornieren und erstatten.
 *
 * Bis zum 04.09.2026 setzte eine Absage nur den Status des Termins. Die
 * Buchungen blieben „bestätigt", die Gäste standen weiter auf dem Laufzettel,
 * und das Geld lag beim Haus – ohne dass irgendwo stand, dass es zurück muss.
 *
 * BEWUSST NICHT automatisch beim Absagen: Ein Haus sagt ab und verlegt,
 * erstattet in Gutscheinen oder verhandelt einzeln. Absagen und Erstatten sind
 * zwei Entscheidungen, und die zweite kostet echtes Geld.
 *
 * Erstattet wird über denselben Weg wie beim Einzelstorno
 * ({@see OrderCancellationService::approveAndCancel()}) – nicht aus
 * Bequemlichkeit, sondern damit nicht zwei Stellen entscheiden, was
 * „stornieren" heißt. Wer im Mollie-Dashboard erstattet, lässt die Buchung
 * bestätigt stehen: Der Gast bleibt auf dem Laufzettel, und der Betrag zählt
 * weiter in Umsatz und DATEV, obwohl das Geld zurück ist.
 */
class EventCancellationService
{
    /** Bestellungen, die eine Erstattung auslösen würden. */
    public const ERSTATTBAR = [
        Order::STATUS_CONFIRMED,
        Order::STATUS_CANCELLATION_REQUESTED,
    ];

    public function __construct(protected OrderCancellationService $stornos)
    {
    }

    /**
     * Was eine Absage kosten würde – für die Rückfrage.
     *
     * Die Zahl UND die Summe, weil „12 Bestellungen" etwas anderes ist als
     * „12 Bestellungen über 1.240 €". Wer bestätigt, soll wissen, worüber.
     *
     * @return array{anzahl:int, summe:float, offen:int}
     */
    public function vorschau(Event $event): array
    {
        $bestellungen = $this->betroffene($event);

        return [
            'anzahl' => $bestellungen->count(),
            'summe'  => (float) $bestellungen->sum(fn (Order $o) => (float) $o->total_amount),
            // Unbezahlte: Die fallen ohnehin weg, kosten aber nichts und
            // gehören deshalb nicht in die Summe.
            'offen'  => Order::withoutGlobalScope('team')
                ->where('event_id', $event->id)
                ->where('status', Order::STATUS_PENDING)
                ->count(),
        ];
    }

    /**
     * Alle betroffenen Bestellungen stornieren und erstatten.
     *
     * Ein Fehler bei einer Bestellung hält die anderen NICHT auf: Bleibt eine
     * Erstattung bei Mollie hängen, wären sonst die restlichen Gäste die
     * Leidtragenden. Was schiefging, kommt namentlich zurück.
     *
     * @return array{storniert:int, fehler:array<int, array{uuid:string, grund:string}>}
     */
    public function alleStornieren(Event $event, ?string $grund = null): array
    {
        $grund ??= 'Die Veranstaltung wurde abgesagt.';

        $storniert = 0;
        $fehler    = [];

        foreach ($this->betroffene($event) as $order) {
            try {
                $ergebnis = $this->stornos->approveAndCancel($order, $grund);

                if (($ergebnis['status'] ?? '') === 'cancelled') {
                    $storniert++;

                    continue;
                }

                $fehler[] = ['uuid' => (string) $order->uuid, 'grund' => (string) ($ergebnis['message'] ?? $ergebnis['status'] ?? 'unbekannt')];
            } catch (\Throwable $e) {
                Log::warning('[Reservation\\EventCancellationService] Storno fehlgeschlagen', [
                    'event_id' => $event->id,
                    'order_id' => $order->id,
                    'error'    => $e->getMessage(),
                ]);

                $fehler[] = ['uuid' => (string) $order->uuid, 'grund' => $e->getMessage()];
            }
        }

        return ['storniert' => $storniert, 'fehler' => $fehler];
    }

    /** @return \Illuminate\Support\Collection<int, Order> */
    protected function betroffene(Event $event)
    {
        return Order::withoutGlobalScope('team')
            ->where('event_id', $event->id)
            ->whereIn('status', self::ERSTATTBAR)
            ->with(['bookings' => fn ($q) => $q->withoutGlobalScope('team'), 'payment'])
            ->get();
    }
}
