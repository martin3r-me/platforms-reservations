<?php

namespace Platform\Reservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Protokoll der Zugriffe auf den Freigabe-Link einer Veranstaltung.
 *
 * Ein Link lässt sich nicht einer Person zuordnen – wer ihn hat, kommt rein.
 * Das Protokoll ist der Ausgleich dafür: Es zeigt, wie oft und von wo zugegriffen
 * wurde, und vor allem gehäufte Fehlversuche bei der PIN.
 *
 * IP-Adressen werden GEKÜRZT abgelegt (letztes Oktett bzw. die hinteren
 * IPv6-Blöcke entfernt). Für die Frage "wird der Link missbraucht?" genügt das;
 * eine vollständige IP wäre personenbezogen, ohne mehr zu verraten.
 */
class EventShareAccess extends Model
{
    protected $table = 'reservation_event_share_accesses';

    /** Nur created_at – ein Zugriff wird nie geändert. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'event_id',
        'ip',
        'user_agent',
        'successful',
    ];

    protected $casts = [
        'successful' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    /**
     * IP so kürzen, dass sie einen Ort grob eingrenzt, aber kein Gerät.
     * IPv4: letztes Oktett weg. IPv6: nur das Präfix behalten.
     */
    public static function truncateIp(?string $ip): ?string
    {
        if (! $ip) {
            return null;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $teile = explode('.', $ip);
            $teile[3] = '0';

            return implode('.', $teile);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $bloecke = explode(':', $ip);

            return implode(':', array_slice($bloecke, 0, 4)) . '::';
        }

        return null;
    }
}
