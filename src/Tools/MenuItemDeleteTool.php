<?php

namespace Platform\Reservation\Tools;

use Illuminate\Database\QueryException;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\MenuItem;

/**
 * Löscht einen Artikel des aktiven Teams (sofern nicht bereits bestellt).
 */
class MenuItemDeleteTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.menu-items.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /reservation/menu-items - Löscht einen Artikel. REST-Parameter: id (Pflicht). '
            . 'Bereits bestellte Artikel können nicht gelöscht werden – dann besser available=false setzen. '
            . 'Ebenso blockiert, solange der Artikel Bestandteil eines Bundles ist (Fehlercode IN_BUNDLE, '
            . 'die betroffenen Bundles stehen in der Meldung).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'ID des zu löschenden Artikels.'],
            ],
            'required'   => ['id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $context->team?->id;

            if (!$teamId) {
                return ToolResult::error('Kein Team-Kontext vorhanden.', 'MISSING_TEAM');
            }

            $item = MenuItem::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->find((int) ($arguments['id'] ?? 0));

            if (!$item) {
                return ToolResult::error('Artikel nicht gefunden.', 'NOT_FOUND');
            }

            // Steckt der Artikel in einem Bundle? Die Fremdschlüssel-Regel würde
            // das Löschen ohnehin verhindern, aber die QueryException unten
            // meldet dann "bereits bestellt" – und schickt in die falsche Richtung.
            $bundles = $item->partOfBundles()->pluck('name');

            if ($bundles->isNotEmpty()) {
                return ToolResult::error(
                    'Der Artikel ist Bestandteil von: ' . $bundles->implode(', ')
                        . '. Bitte zuerst dort entfernen oder das Bundle löschen.',
                    'IN_BUNDLE',
                );
            }

            try {
                $item->delete();
            } catch (QueryException $e) {
                return ToolResult::error(
                    'Der Artikel wurde bereits bestellt und kann nicht gelöscht werden. Bitte stattdessen deaktivieren (available=false).',
                    'HAS_ORDERS',
                );
            }

            return ToolResult::success(['deleted' => true, 'id' => (int) $arguments['id']]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Löschen des Artikels: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'             => 'action',
            'tags'                 => ['reservation', 'menu', 'items', 'delete'],
            'requires_team'        => true,
            'read_only'            => false,
            'side_effects'         => ['deletes'],
            'risk_level'           => 'destructive',
            'confirmation_required'=> true,
        ];
    }
}
