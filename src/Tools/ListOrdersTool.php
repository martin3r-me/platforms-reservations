<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Models\Order;

/**
 * Bestellvorgänge des aktiven Teams lesen.
 *
 * Es gab bisher nur reservation.orders.cancel.POST: Man konnte eine Bestellung
 * stornieren, aber keine ansehen. Wer wissen wollte, was im Posteingang steht,
 * musste den Bildschirm aufmachen.
 *
 * Die ZUSAMMENFASSUNG ist der eigentliche Grund für dieses Werkzeug. Die Zahl
 * am Menüpunkt wirft zwei verschiedene Dinge zusammen: neue Bestellungen sind
 * eine Nachricht, eine Stornierungsanfrage ist eine Aufgabe. Wie sich die Zahl
 * aufteilt, liess sich bis jetzt nirgends ablesen - auch nicht in der
 * Oberfläche. Die Zusammenfassung zählt deshalb IMMER über den ganzen
 * Posteingang, unabhängig von Filter und limit: Sonst zählte sie die Seite und
 * nicht den Stapel.
 */
class ListOrdersTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.orders.GET';
    }

    public function getDescription(): string
    {
        return 'GET /reservation/orders - Listet Bestellvorgänge des aktiven Teams und fasst den Posteingang '
            . 'zusammen. Die Antwort enthält immer "inbox": die Aufteilung der ungesehenen Vorgänge nach '
            . 'new / cancellation_requested / cancelled / payment_failed - das ist die Zahl am Menüpunkt, '
            . 'aufgeschlüsselt. REST-Parameter (optional): status (pending|confirmed|cancelled|'
            . 'cancellation_requested), inbox_type (new|cancellation_requested|cancelled|payment_failed), '
            . 'unseen (bool - nur ungesehene), event_uuid, limit (1-200, Default 50).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'status'     => [
                    'type'        => 'string',
                    'enum'        => ['pending', 'confirmed', 'cancelled', 'cancellation_requested'],
                    'description' => 'Nur Vorgänge mit diesem Status.',
                ],
                'inbox_type' => [
                    'type'        => 'string',
                    'enum'        => ['new', 'cancellation_requested', 'cancelled', 'payment_failed'],
                    'description' => 'Nur Vorgänge dieser Art. "payment_failed" ist ein Sonderfall von '
                        . 'cancelled und wird deshalb nach dem Laden gefiltert.',
                ],
                'unseen'     => [
                    'type'        => 'boolean',
                    'description' => 'true = nur ungesehene Vorgänge, also genau das, was die Zahl am Menüpunkt zählt.',
                ],
                'event_uuid' => [
                    'type'        => 'string',
                    'description' => 'UUID eines Termins – nur dessen Vorgänge.',
                ],
                'limit'      => [
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'maximum'     => 200,
                    'description' => 'Maximale Anzahl (Default 50). Gilt NICHT für die Zusammenfassung.',
                ],
            ],
            'required'   => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $context->team?->id;

            if (!$teamId) {
                return ToolResult::error('Kein Team-Kontext vorhanden.', 'MISSING_TEAM');
            }

            $limit = (int) ($arguments['limit'] ?? 50);
            $limit = max(1, min(200, $limit));

            $query = Order::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->with(['bookings', 'event', 'payment']);

            if (!empty($arguments['event_uuid'])) {
                $eventId = Event::withoutGlobalScope('team')
                    ->where('team_id', $teamId)
                    ->where('uuid', $arguments['event_uuid'])
                    ->value('id');

                if (!$eventId) {
                    return ToolResult::error('Termin nicht gefunden.', 'EVENT_NOT_FOUND');
                }

                $query->where('event_id', $eventId);
            }

            if (!empty($arguments['status'])) {
                $query->where('status', $arguments['status']);
            }

            if (!empty($arguments['unseen'])) {
                $query->whereNull('seen_at')->whereIn('status', Order::INBOX_STATUSES);
            }

            $orders = $query->orderByDesc('created_at')->limit($limit)->get();

            // inbox_type kennt nur das Modell (payment_failed haengt an der
            // Zahlung, nicht am Status) - also nach dem Laden filtern.
            if (!empty($arguments['inbox_type'])) {
                $orders = $orders->filter(
                    fn (Order $order) => $order->inboxType() === $arguments['inbox_type']
                )->values();
            }

            return ToolResult::success([
                'count'  => $orders->count(),
                'inbox'  => $this->posteingang($teamId),
                'orders' => $orders->map(fn (Order $order) => [
                    'uuid'          => $order->uuid,
                    'status'        => $order->status,
                    'inbox_type'    => $order->inboxType(),
                    'seen'          => $order->seen_at !== null,
                    'customer'      => $order->customerName(),
                    'event'         => $order->event?->name,
                    'bookings'      => $order->bookings->count(),
                    'total_amount'  => round((float) $order->total_amount, 2),
                    'payment'       => $order->payment?->status,
                    'created_at'    => $order->created_at?->toDateTimeString(),
                    'cancellation_requested_at' => $order->cancellation_requested_at?->toDateTimeString(),
                ])->all(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Laden der Bestellungen: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    /**
     * Die Zahl am Menüpunkt, aufgeschlüsselt.
     *
     * Über ALLE ungesehenen Vorgänge, nie über die gefilterte Liste: Die Frage
     * lautet "wie teilt sich der Posteingang auf", nicht "wie teilt sich auf,
     * was ich gerade abgefragt habe".
     *
     * @return array<string, int>
     */
    private function posteingang(int $teamId): array
    {
        $ungesehen = Order::withoutGlobalScope('team')
            ->where('team_id', $teamId)
            ->whereIn('status', Order::INBOX_STATUSES)
            ->whereNull('seen_at')
            ->with('payment')
            ->get();

        $nachArt = $ungesehen->countBy(fn (Order $order) => $order->inboxType());

        return [
            // Gesamtzahl = die Zahl am Menuepunkt.
            'unseen_total'           => $ungesehen->count(),
            // Nachrichten: etwas ist passiert, zu tun ist nichts.
            'new'                    => (int) $nachArt->get('new', 0),
            'cancelled'              => (int) $nachArt->get('cancelled', 0),
            'payment_failed'         => (int) $nachArt->get('payment_failed', 0),
            // Aufgabe: hier wartet ein Gast auf eine Entscheidung.
            'cancellation_requested' => (int) $nachArt->get('cancellation_requested', 0),
        ];
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['reservation', 'orders', 'inbox', 'list'],
            'requires_team' => true,
            'read_only'     => true,
            'idempotent'    => true,
            'risk_level'    => 'safe',
            'examples'      => [
                'Wie teilt sich die Zahl im Posteingang auf?',
                'Welche Stornierungsanfragen warten noch auf eine Entscheidung?',
                'Zeige die ungesehenen Vorgänge der letzten Bestellungen.',
            ],
        ];
    }
}
