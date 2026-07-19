<?php

namespace Platform\Reservation\Tools;

use Illuminate\Support\Facades\Validator;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Reservation\Models\CheckoutSetting;

/**
 * Aktualisiert die Checkout-/Buchungs-Einstellungen des aktiven Teams.
 */
class CheckoutSettingsUpdateTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'reservation.checkout-settings.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /reservation/checkout-settings - Aktualisiert die Einstellungen des aktiven Teams. '
            . 'REST-Parameter (alle optional): field_email/field_phone/field_notes (required|optional|hidden), '
            . 'default_room_release_mode (parallel|sequential), soft_table_capacity (bool – Großgruppen auf leere '
            . 'Tische), max_group_empty_table (int, null = unbegrenzt), age_check_text, legal_text, privacy_url.';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'field_email'               => ['type' => 'string', 'enum' => ['required', 'optional', 'hidden']],
                'field_phone'               => ['type' => 'string', 'enum' => ['required', 'optional', 'hidden']],
                'field_notes'               => ['type' => 'string', 'enum' => ['required', 'optional', 'hidden']],
                'default_room_release_mode' => ['type' => 'string', 'enum' => ['parallel', 'sequential']],
                'soft_table_capacity'       => ['type' => 'boolean', 'description' => 'Großgruppen dürfen leere Tische überbelegen.'],
                'max_group_empty_table'     => ['type' => ['integer', 'null'], 'description' => 'Max. Gruppengröße auf leerem Tisch (null = unbegrenzt).'],
                'languages'                 => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Angebotene Sprachen (locale-Codes, z.B. ["de","en"]). DE ist immer dabei.'],
                'guest_frontend_url'        => ['type' => ['string', 'null'], 'description' => 'Basis-URL des Shop-Frontends (Allowlist-Origin für Zahlungs-Rücksprung).'],
                'confirmation_channel_id'   => ['type' => ['integer', 'null'], 'description' => 'CRM-Comms-Channel-ID (Postmark) für Bestellbestätigungen; null = kein Versand.'],
                'issuer'                    => ['type' => 'object', 'description' => 'Aussteller-/Rechnungsangaben: name, street, zip, city, country, vat_id, tax_number, email, phone, website.'],
                'cancellation_enabled'           => ['type' => 'boolean', 'description' => 'Selbst-Storno durch den Kunden erlauben.'],
                'cancellation_deadline_hours'    => ['type' => ['integer', 'null'], 'description' => 'Frist: Stunden vor Veranstaltungsdatum (null = keine Frist).'],
                'cancellation_requires_approval' => ['type' => 'boolean', 'description' => 'Storno erst nach Freigabe durch das Team (Default false).'],
                'age_check_text'            => ['type' => 'string'],
                'legal_text'                => ['type' => 'string'],
                'privacy_url'               => ['type' => 'string'],
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

            $validator = Validator::make($arguments, [
                'field_email'               => 'sometimes|in:required,optional,hidden',
                'field_phone'               => 'sometimes|in:required,optional,hidden',
                'field_notes'               => 'sometimes|in:required,optional,hidden',
                'default_room_release_mode' => 'sometimes|in:parallel,sequential',
                'soft_table_capacity'       => 'sometimes|boolean',
                'max_group_empty_table'     => 'sometimes|nullable|integer|min:1|max:200',
                'languages'                 => 'sometimes|array',
                'languages.*'               => 'string|regex:/^[a-zA-Z]{2}(_[a-zA-Z]{2})?$/',
                'guest_frontend_url'        => 'sometimes|nullable|url|max:255',
                'confirmation_channel_id'   => 'sometimes|nullable|integer',
                'issuer'                    => 'sometimes|array',
                'cancellation_enabled'           => 'sometimes|boolean',
                'cancellation_deadline_hours'    => 'sometimes|nullable|integer|min:0|max:8760',
                'cancellation_requires_approval' => 'sometimes|boolean',
                'age_check_text'            => 'sometimes|nullable|string|max:1000',
                'legal_text'                => 'sometimes|nullable|string|max:1000',
                'privacy_url'               => 'sometimes|nullable|url|max:255',
            ]);

            if ($validator->fails()) {
                return ToolResult::error($validator->errors()->first(), 'VALIDATION_ERROR');
            }

            $setting = CheckoutSetting::forTeam($teamId);
            $setting->fill(collect($validator->validated())->only([
                'field_email', 'field_phone', 'field_notes', 'default_room_release_mode',
                'soft_table_capacity', 'max_group_empty_table', 'languages', 'guest_frontend_url',
                'confirmation_channel_id', 'cancellation_enabled', 'cancellation_deadline_hours',
                'cancellation_requires_approval', 'age_check_text', 'legal_text', 'privacy_url',
            ])->all());

            // Aussteller-Daten mergen (nur bekannte Felder, leere entfernt).
            if (array_key_exists('issuer', $validator->validated())) {
                $incoming = (array) $validator->validated()['issuer'];
                $merged   = array_merge($setting->issuer(), $incoming);
                $setting->issuer = collect(CheckoutSetting::ISSUER_FIELDS)
                    ->mapWithKeys(fn ($f) => [$f => (trim((string) ($merged[$f] ?? '')) ?: null)])
                    ->filter()
                    ->all();
            }

            $setting->team_id = $teamId;
            $setting->save();

            return ToolResult::success([
                'field_email'               => $setting->fieldMode('email'),
                'field_phone'               => $setting->fieldMode('phone'),
                'field_notes'               => $setting->fieldMode('notes'),
                'default_room_release_mode' => $setting->defaultRoomReleaseMode(),
                'soft_table_capacity'       => $setting->softTableCapacity(),
                'max_group_empty_table'     => $setting->maxGroupEmptyTable(),
                'languages'                 => $setting->languages(),
                'guest_frontend_url'        => $setting->guestFrontendUrl(),
                'confirmation_channel_id'   => $setting->confirmationChannelId(),
                'cancellation_enabled'           => $setting->cancellationEnabled(),
                'cancellation_deadline_hours'    => $setting->cancellationDeadlineHours(),
                'cancellation_requires_approval' => $setting->cancellationRequiresApproval(),
                'issuer'                    => $setting->issuer(),
            ], ['updated' => true]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Speichern der Einstellungen: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['reservation', 'settings', 'checkout', 'update'],
            'requires_team' => true,
            'read_only'     => false,
            'side_effects'  => ['updates'],
            'risk_level'    => 'write',
        ];
    }
}
