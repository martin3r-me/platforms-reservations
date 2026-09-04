<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\MenuItem;

/**
 * Reicht mehrere Artikel zur Prüfung ein (Entwurf → wartet auf Freigabe).
 *
 * Das Gegenstück zur Massenfreigabe, und ohne dieses Werkzeug wäre die
 * Vier-Augen-Pflicht in der Praxis nicht durchzuhalten: Ein CSV-Import legt
 * 200 Entwürfe an, und einreichen ließ sich bisher nur einer nach dem anderen
 * in der Oberfläche. Wer 200-mal klicken müsste, schaltet die Pflicht ab –
 * eine Regel, die zum Abschalten zwingt, schützt nichts.
 *
 * Der Aufrufende wird als Einreicher vermerkt. Freigeben muss die Artikel
 * danach ein anderer Mensch (siehe MenuItem::canBeApprovedBy()).
 *
 * Nur Entwürfe. Freigegebene Artikel bleiben unangetastet: Sie einzureichen
 * hieße, sie aus dem Shop zu nehmen – das darf kein Nebeneffekt einer
 * Massenaktion sein, sondern gehört ausdrücklich getan.
 */
class MenuItemSubmitBulkTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.menu-items.submit.bulk.POST';
    }

    public function getDescription(): string
    {
        return 'POST /reservation/menu-items/submit/bulk - Reicht Artikel zur Pruefung ein (Entwurf -> '
            . 'wartet auf Freigabe). REST-Parameter: item_ids (Array von Artikel-IDs) ODER all=true (alle '
            . 'Entwuerfe des Teams). Der aufrufende Nutzer wird als Einreicher vermerkt; FREIGEBEN muss die '
            . 'Artikel danach ein ANDERER Mensch (reservation.menu-items.approve.bulk.POST). Der uebliche '
            . 'Weg nach einem CSV-Import. Nur Entwuerfe: Bereits freigegebene Artikel werden uebersprungen '
            . '(skipped_approved) - sie einzureichen hiesse, sie aus dem Shop zu nehmen; Artikel, die schon '
            . 'in Pruefung liegen, ebenso (skipped_review).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'item_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                'all'      => ['type' => 'boolean', 'description' => 'Alle Entwuerfe des Teams einreichen.'],
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

            $user = $context->user;

            if (!$user) {
                return ToolResult::error('Ohne angemeldeten Nutzer gibt es keinen Einreicher.', 'MISSING_USER');
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
                $query->where('approval_status', MenuItem::APPROVAL_DRAFT);
            }

            $artikel = $query->get(['id', 'team_id', 'approval_status']);

            // Nach Zustand trennen, damit die Antwort sagen kann, WARUM etwas
            // nicht mitgekommen ist. Ein blosses "12 von 20" laesst den Nutzer
            // raten.
            $entwuerfe    = $artikel->where('approval_status', MenuItem::APPROVAL_DRAFT);
            $inPruefung   = $artikel->where('approval_status', MenuItem::APPROVAL_REVIEW);
            $freigegebene = $artikel->where('approval_status', MenuItem::APPROVAL_APPROVED);

            // Ueber das Modell, nicht per Massen-Update: submitForReview() ist
            // die eine Stelle, die weiss, welche Felder zu einer Einreichung
            // gehoeren - auch die, die dabei geleert werden muessen.
            foreach ($entwuerfe as $artikelEntwurf) {
                $artikelEntwurf->submitForReview($user);
            }

            return ToolResult::success([
                'submitted_count'        => $entwuerfe->count(),
                'submitted'              => $entwuerfe->pluck('id')->values()->all(),
                'skipped_review'         => $inPruefung->pluck('id')->values()->all(),
                'skipped_approved'       => $freigegebene->pluck('id')->values()->all(),
                'skipped_approved_count' => $freigegebene->count(),
            ], ['updated' => $entwuerfe->count()]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Einreichen: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'menu', 'items', 'submit', 'review', 'bulk'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['updates'],
            'risk_level'    => 'write',
        ];
    }
}
