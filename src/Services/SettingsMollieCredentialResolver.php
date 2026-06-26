<?php

namespace Platform\Reservation\Services;

use Platform\Reservation\Contracts\MollieCredentialResolver;
use Platform\Reservation\Models\PaymentSetting;
use Platform\Reservation\Support\MollieCredentials;

/**
 * Standard-Resolver: liest die Team-Einstellung (verschlüsselt) und fällt
 * andernfalls auf die globale ENV-Konfiguration zurück (Single-Tenant-Demo).
 */
class SettingsMollieCredentialResolver implements MollieCredentialResolver
{
    public function forTeam(int $teamId): ?MollieCredentials
    {
        $setting = PaymentSetting::where('team_id', $teamId)
            ->where('provider', 'mollie')
            ->first();

        if ($setting && $setting->isReady()) {
            return new MollieCredentials($setting->activeApiKey(), $setting->mode);
        }

        // ENV-Fallback (nur wenn aktiviert und Key vorhanden)
        if (config('reservation.mollie.enabled') && config('reservation.mollie.api_key')) {
            return new MollieCredentials(
                (string) config('reservation.mollie.api_key'),
                (string) config('reservation.mollie.mode', 'test'),
            );
        }

        return null;
    }
}
