<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Models\Event;

/**
 * Einstiegs-/Übersichts-Tool für das Reservations-Modul (PausePlus).
 */
class ReservationOverviewTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.overview.GET';
    }

    public function getDescription(): string
    {
        return 'GET /reservation/overview - Übersicht über das Reservierungs-/Vorbestell-Modul (PausePlus): '
            . 'Konzepte (Termine, Pausen-Slots, Verkaufslisten, Buchungen, Orders), verwandte Tools und '
            . 'Kurzstatistik des aktiven Teams. EMPFOHLEN zuerst aufrufen. REST-Parameter: keine.';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => new \stdClass(),
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

            $events = Event::withoutGlobalScope('team')->where('team_id', $teamId);
            $bookings = Booking::withoutGlobalScope('team')->where('team_id', $teamId);

            $stats = [
                'events_total'      => (clone $events)->count(),
                'events_published'  => (clone $events)->where('status', Event::STATUS_PUBLISHED)->count(),
                'events_upcoming'   => (clone $events)->where('status', Event::STATUS_PUBLISHED)
                    ->whereDate('date', '>=', now()->toDateString())->count(),
                'bookings_total'    => (clone $bookings)->count(),
                'bookings_confirmed'=> (clone $bookings)->where('status', Booking::STATUS_CONFIRMED)->count(),
                'bookings_pending'  => (clone $bookings)->where('status', Booking::STATUS_PENDING)->count(),
            ];

            return ToolResult::success([
                'module'      => 'reservation',
                'description' => 'PausePlus: Vorbestellung von Speisen/Getränken für Veranstaltungspausen. '
                    . 'Gäste bestellen je Pause (Slot) Artikel und wählen einen Tisch; die Bestellung läuft als '
                    . 'Order (eine Zahlung, mehrere Slot-Buchungen) über Mollie.',
                'concepts'    => [
                    'event'      => 'Termin/Veranstaltung mit Datum, Status (draft|published|closed) und Pausen-Slots.',
                    'slot'       => 'Pause innerhalb eines Termins; je Slot bestellt der Gast eigene Artikel.',
                    'sales_list' => 'Verkaufsliste – welche Artikel für einen Termin gast-sichtbar sind.',
                    'booking'    => 'Eine Slot-Buchung (Gast, Tisch, Positionen mit eingefrorenem Preis/MwSt).',
                    'order'      => 'Klammer über mehrere Slot-Buchungen mit genau einer Zahlung.',
                ],
                'related_tools' => [
                    'reservation.events.GET'   => 'Termine auflisten (Filter: status, upcoming).',
                    'reservation.bookings.GET' => 'Buchungen auflisten (Filter: event_uuid, date, status).',
                    'reservation.finance.GET'  => 'Umsatz + MwSt-Aufschlüsselung für einen Zeitraum.',
                ],
                'stats' => $stats,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Laden der Übersicht: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'overview',
            'tags'          => ['overview', 'help', 'reservation', 'pauseplus'],
            'requires_team' => true,
            'read_only'     => true,
            'idempotent'    => true,
            'risk_level'    => 'safe',
            'examples'      => [
                'Gib mir eine Übersicht über das Reservierungs-Modul.',
                'Wie viele Buchungen gibt es aktuell?',
            ],
        ];
    }
}
