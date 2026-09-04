<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\MenuItem;

/**
 * Gibt mehrere Artikel frei (approval_status = approved → gast-sichtbar).
 *
 * Hält sich an dieselbe Regel wie die Oberfläche: Gilt die Vier-Augen-Pflicht,
 * darf niemand die EIGENE Einreichung freigeben. Bis zum 04.09.2026 setzte
 * dieses Werkzeug die Freigabe ungeprüft – wer einen Artikel zur Prüfung
 * eingereicht hatte, konnte ihn hier selbst durchwinken. Das machte die
 * Pflicht in dem Moment wertlos, in dem jemand ein Werkzeug statt der
 * Oberfläche benutzte.
 *
 * Übersprungene Artikel kommen als skipped_own zurück, nicht als Fehler: Bei
 * einer Massenfreigabe soll der Rest durchgehen.
 */
class MenuItemApproveBulkTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.menu-items.approve.bulk.POST';
    }

    public function getDescription(): string
    {
        return 'POST /reservation/menu-items/approve/bulk - Gibt Artikel frei (gast-sichtbar). REST-Parameter: '
            . 'item_ids (Array von Artikel-IDs) ODER all=true (alle noch nicht freigegebenen Artikel des Teams). '
            . 'VIER-AUGEN: Gilt die Pflicht, werden Artikel UEBERSPRUNGEN, die der aufrufende Nutzer selbst '
            . 'zur Pruefung eingereicht hat - die muss ein anderer Mensch freigeben. Sie kommen als '
            . 'skipped_own (Ids) und skipped_own_count zurueck; das ist kein Fehler, sollte dem Nutzer aber '
            . 'gesagt werden. Ist die Pflicht abgeschaltet, wird alles freigegeben.';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'item_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                'all'      => ['type' => 'boolean', 'description' => 'Alle nicht freigegebenen Artikel des Teams freigeben.'],
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

            $ids = $arguments['item_ids'] ?? [];
            $all = (bool) ($arguments['all'] ?? false);

            if (!$all && (!is_array($ids) || $ids === [])) {
                return ToolResult::error('Entweder item_ids (Array) oder all=true angeben.', 'VALIDATION_ERROR');
            }

            $query = MenuItem::withoutGlobalScope('team')->where('team_id', $teamId);

            if (!$all) {
                $query->whereIn('id', array_map('intval', $ids));
            } else {
                $query->where('approval_status', '!=', MenuItem::APPROVAL_APPROVED);
            }

            $user = $context->user;

            // Wer nicht der Einreicher ist, darf immer - das ist der erste Satz
            // in canBeApprovedBy(). Deshalb zuerst nach dem Einreicher trennen
            // und die Regel nur fuer die EIGENEN Einreichungen befragen: Sie
            // schlaegt je Artikel in den Team-Einstellungen nach, und das waere
            // bei einer Massenfreigabe ueber hunderte Artikel eine Abfrage je
            // Artikel, fuer eine Antwort, die fuer alle dieselbe ist.
            [$fremde, $eigeneEinreichungen] = $query
                ->get(['id', 'team_id', 'approval_status', 'submitted_by', 'submitted_at'])
                ->partition(fn (MenuItem $item) => (int) $item->submitted_by !== (int) $user?->id);

            $erlaubt = $eigeneEinreichungen->filter(fn (MenuItem $item) => $item->canBeApprovedBy($user));
            $eigene  = $eigeneEinreichungen->diff($erlaubt)->pluck('id')->values()->all();

            $query = MenuItem::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->whereIn('id', $fremde->merge($erlaubt)->pluck('id')->all());

            $approved = $query->update([
                'approval_status' => MenuItem::APPROVAL_APPROVED,
                'approved_by'     => $user?->id,
                'approved_at'     => now(),
            ]);

            return ToolResult::success([
                'approved_count' => $approved,
                // Eigene Einreichungen: uebersprungen, nicht verschwiegen.
                'skipped_own'       => $eigene,
                'skipped_own_count' => count($eigene),
            ], ['updated' => $approved]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler bei der Freigabe: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'menu', 'items', 'approve', 'bulk'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['updates'],
            'risk_level'    => 'write',
        ];
    }
}
