<?php

namespace Platform\Reservation\Tools;

use Illuminate\Support\Facades\Validator;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Exceptions\FloorPlanInUseException;
use Platform\Reservation\Models\PickupStation;

/**
 * Löscht eine Abholstation – solange sie in keinem anstehenden Termin hängt.
 */
class PickupStationDeleteTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.pickup-stations.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /reservation/pickup-stations - Loescht eine Abholstation. REST-Parameter: id (Pflicht). '
            . 'Eine Station, die in einem ANSTEHENDEN Termin eingeplant ist, wird NICHT geloescht - sonst '
            . 'verloere der Termin lautlos einen Ort, an dem Gaeste bestellt haben. Die Antwort nennt dann die '
            . 'betroffenen Termine. Wer sie nur aus dem Angebot nehmen will, setzt is_active=false '
            . '(reservation.pickup-stations.PATCH).';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => ['id' => ['type' => 'integer']],
            'required'   => ['id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $context->team?->id;

            if (! $teamId) {
                return ToolResult::error('Kein Team-Kontext vorhanden.', 'MISSING_TEAM');
            }

            $validator = Validator::make($arguments, ['id' => 'required|integer']);

            if ($validator->fails()) {
                return ToolResult::error($validator->errors()->first(), 'VALIDATION_ERROR');
            }

            $station = PickupStation::withoutGlobalScope('team')
                ->where('team_id', $teamId)
                ->find((int) $arguments['id']);

            if (! $station) {
                return ToolResult::error('Abholstation nicht gefunden.', 'NOT_FOUND');
            }

            $name = $station->name;

            try {
                $station->delete();
            } catch (FloorPlanInUseException $e) {
                return ToolResult::error($e->getMessage(), 'STATION_IN_USE');
            }

            return ToolResult::success(['id' => (int) $arguments['id'], 'name' => $name], ['deleted' => true]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Loeschen der Abholstation: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'              => 'action',
            'tags'                  => ['reservation', 'stations', 'delete'],
            'requires_team'         => true,
            'read_only'             => false,
            'side_effects'          => ['deletes'],
            'risk_level'            => 'destructive',
            'confirmation_required' => true,
        ];
    }
}
