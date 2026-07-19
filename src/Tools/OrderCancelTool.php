<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\Order;
use Platform\Reservation\Services\OrderCancellationService;

/**
 * Storniert eine Bestellung des aktiven Teams (Team-/Freigabe-Storno) inkl.
 * Mollie-Rückerstattung. Dient auch der Freigabe eines vom Kunden angefragten
 * Stornos (Status cancellation_requested).
 */
class OrderCancelTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.orders.cancel.POST';
    }

    public function getDescription(): string
    {
        return 'POST /reservation/orders/cancel - Storniert eine Bestellung (per uuid) und löst die Mollie-'
            . 'Rückerstattung aus. Gibt Plätze wieder frei. Auch zur Freigabe eines angefragten Stornos.';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'uuid' => ['type' => 'string', 'description' => 'UUID der Bestellung.'],
            ],
            'required'   => ['uuid'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $context->team?->id;

            if (!$teamId) {
                return ToolResult::error('Kein Team-Kontext vorhanden.', 'MISSING_TEAM');
            }

            $order = Order::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->where('uuid', (string) ($arguments['uuid'] ?? ''))
                ->with(['event', 'bookings' => fn ($q) => $q->withoutGlobalScope('team')])
                ->first();

            if (!$order) {
                return ToolResult::error('Bestellung nicht gefunden.', 'NOT_FOUND');
            }

            $result = app(OrderCancellationService::class)->approveAndCancel($order);

            return ToolResult::success([
                'uuid'   => $order->uuid,
                'status' => $order->status,
                'result' => $result,
            ], ['updated' => true]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Stornieren: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'             => 'action',
            'tags'                 => ['reservation', 'orders', 'cancel', 'refund'],
            'requires_team'        => true,
            'read_only'            => false,
            'side_effects'         => ['updates', 'refund'],
            'risk_level'           => 'destructive',
            'confirmation_required'=> true,
        ];
    }
}
