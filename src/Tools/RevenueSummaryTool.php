<?php

namespace Platform\Reservation\Tools;

use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\Booking;
use Platform\Reservation\Support\Vat;

/**
 * Umsatz + MwSt-Aufschlüsselung des aktiven Teams für einen Zeitraum.
 *
 * Umsatz = Summe der eingefrorenen Positionen (Menge × Einzelpreis) aller
 * aktiven Buchungen (ohne storniert/No-Show). Preise sind brutto.
 */
class RevenueSummaryTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.finance.GET';
    }

    public function getDescription(): string
    {
        return 'GET /reservation/finance - Umsatz (brutto) und MwSt-Aufschlüsselung (Netto/Steuer je Satz) des '
            . 'aktiven Teams. REST-Parameter (optional): date_from (YYYY-MM-DD), date_to (YYYY-MM-DD).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'date_from' => [
                    'type'        => 'string',
                    'description' => 'Startdatum YYYY-MM-DD (inklusive).',
                ],
                'date_to'   => [
                    'type'        => 'string',
                    'description' => 'Enddatum YYYY-MM-DD (inklusive).',
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

            $from = $arguments['date_from'] ?? null;
            $to   = $arguments['date_to'] ?? null;

            $items = fn () => DB::table('reservation_booking_items as bi')
                ->join('reservation_bookings as b', 'b.id', '=', 'bi.booking_id')
                ->where('b.team_id', $teamId)
                ->whereNotIn('b.status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW])
                ->when($from, fn ($q) => $q->whereDate('b.date', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('b.date', '<=', $to));

            $gross = (float) $items()->sum(DB::raw('bi.quantity * bi.unit_price'));

            $breakdown = $items()
                ->groupBy('bi.tax_rate')
                ->selectRaw('bi.tax_rate as tax_rate, SUM(bi.quantity * bi.unit_price) as revenue')
                ->orderByDesc('revenue')
                ->get()
                ->map(function ($row) {
                    $split = Vat::fromGross((float) $row->revenue, (float) $row->tax_rate);

                    return [
                        'tax_rate' => (float) $row->tax_rate,
                        'net'      => $split['net'],
                        'vat'      => $split['vat'],
                        'gross'    => $split['gross'],
                    ];
                });

            $split = Vat::fromGross($gross, 0.0); // Gesamt-Brutto ohne Einzelsatz

            return ToolResult::success([
                'date_from'      => $from,
                'date_to'        => $to,
                'currency'       => strtoupper((string) config('reservation.currency', 'EUR')),
                'total_gross'    => $split['gross'],
                'total_net'      => round($breakdown->sum('net'), 2),
                'total_vat'      => round($breakdown->sum('vat'), 2),
                'by_tax_rate'    => $breakdown->all(),
                'bookings_count' => (int) DB::table('reservation_bookings as b')
                    ->where('b.team_id', $teamId)
                    ->whereNotIn('b.status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW])
                    ->when($from, fn ($q) => $q->whereDate('b.date', '>=', $from))
                    ->when($to, fn ($q) => $q->whereDate('b.date', '<=', $to))
                    ->count(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Laden der Umsatzdaten: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['reservation', 'finance', 'revenue', 'tax'],
            'requires_team' => true,
            'read_only'     => true,
            'idempotent'    => true,
            'risk_level'    => 'safe',
            'examples'      => [
                'Wie hoch war der Umsatz im August 2026?',
                'Zeig mir die MwSt-Aufschlüsselung des letzten Monats.',
            ],
        ];
    }
}
