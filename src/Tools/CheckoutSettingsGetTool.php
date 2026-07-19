<?php

namespace Platform\Reservation\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\CheckoutSetting;

/**
 * Liest die Checkout-/Buchungs-Einstellungen des aktiven Teams.
 */
class CheckoutSettingsGetTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.checkout-settings.GET';
    }

    public function getDescription(): string
    {
        return 'GET /reservation/checkout-settings - Liest die Checkout-/Buchungs-Einstellungen des aktiven Teams '
            . '(Anmeldefelder, Checkout-Texte, Standard-Raumfreigabe, weiche Tisch-Kapazität). REST-Parameter: keine.';
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

            $s = CheckoutSetting::forTeam($teamId);

            return ToolResult::success([
                'field_email'               => $s->fieldMode('email'),
                'field_phone'               => $s->fieldMode('phone'),
                'field_notes'               => $s->fieldMode('notes'),
                'default_room_release_mode' => $s->defaultRoomReleaseMode(),
                'soft_table_capacity'       => $s->softTableCapacity(),
                'max_group_empty_table'     => $s->maxGroupEmptyTable(),
                'languages'                 => $s->languages(),
                'guest_frontend_url'        => $s->guestFrontendUrl(),
                'confirmation_channel_id'   => $s->confirmationChannelId(),
                'issuer'                    => $s->issuer(),
                'cancellation_enabled'           => $s->cancellationEnabled(),
                'cancellation_deadline_hours'    => $s->cancellationDeadlineHours(),
                'cancellation_requires_approval' => $s->cancellationRequiresApproval(),
                'age_check_text'            => $s->age_check_text,
                'legal_text'                => $s->legal_text,
                'privacy_url'               => $s->privacy_url,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Laden der Einstellungen: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['reservation', 'settings', 'checkout'],
            'requires_team' => true,
            'read_only'     => true,
            'idempotent'    => true,
            'risk_level'    => 'safe',
        ];
    }
}
