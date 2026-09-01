<?php

namespace Platform\Reservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Platform\Reservation\Enums\EventStatus;
use Platform\Reservation\Models\Concerns\BelongsToTeam;
use Platform\Reservation\Models\Concerns\HasContextImage;
use Symfony\Component\Uid\UuidV7;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

/**
 * Termin/Veranstaltung in PausePlus (z.B. "Bodo Wartke, 29.08.").
 *
 * Optional lose mit dem platforms-events-Modul verknüpfbar
 * (events_event_id/-uuid ohne FK) – standalone voll nutzbar.
 */
class Event extends Model
{
    use BelongsToTeam;
    use HasContextImage;

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_ANNOUNCED = 'announced';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_CLOSED    = 'closed';
    public const STATUS_CANCELLED = 'cancelled';

    public const RELEASE_PARALLEL   = 'parallel';
    public const RELEASE_SEQUENTIAL = 'sequential';

    /** Tischbindung: Der Tisch gehört dem Gast für den ganzen Termin. */
    public const BINDING_EVENT = 'event';

    /** Tischbindung: Jede Pause wird einzeln vergeben. */
    public const BINDING_SLOT = 'slot';

    protected $table = 'reservation_events';

    protected $fillable = [
        'uuid',
        'team_id',
        'name',
        'description',
        'date',
        'order_deadline_at',
        'status',
        'venue_id',
        'sales_list_id',
        'room_release_mode',
        'table_binding',
        'max_guest_count',
        'disabled_table_ids',
        'image_context_file_id',
        'events_event_id',
        'events_event_uuid',
        // Freigabe-Link fuer Veranstaltungsleiter (Kueche + Laufzettel)
        'share_token',
        'share_pin_hash',
        'share_created_at',
    ];

    protected $casts = [
        'date'               => 'date',
        'order_deadline_at'  => 'datetime',
        'disabled_table_ids' => 'array',
        'status'             => EventStatus::class,
        'share_created_at'   => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) UuidV7::generate();
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class, 'venue_id');
    }

    public function salesList(): BelongsTo
    {
        return $this->belongsTo(SalesList::class, 'sales_list_id');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(EventSlot::class, 'event_id')->orderBy('sort_order');
    }

    public function eventRooms(): HasMany
    {
        return $this->hasMany(EventRoom::class, 'event_id')->orderBy('sort_order');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'event_id');
    }

    /**
     * Liegt der Termin vor dem heutigen Tag?
     *
     * Verglichen wird gegen den TAGESBEGINN, nicht gegen die Uhrzeit. `date`
     * ist ein reines Datum und steht als Mitternacht in der Ablage; Carbons
     * `$event->date->isPast()` meldet einen heutigen Termin deshalb ab 00:01
     * als vergangen – der Abend hat da noch nicht einmal begonnen.
     *
     * Genau das stand in der Terminliste: Der Filter rechnete richtig, das
     * Abzeichen daneben falsch, und beide standen in derselben Zeile.
     *
     * SQL-Zwilling ist scopeUpcoming(); wer den einen ändert, ändert den anderen.
     */
    public function istVergangen(): bool
    {
        return $this->date !== null && $this->date->lt(now()->startOfDay());
    }

    /**
     * Der Abend ist vorbei, aber es stehen noch bestätigte Buchungen: niemand
     * hat durchgesehen, wer da war und wer nicht. Erst „Abend abschließen"
     * setzt sie auf abgeschlossen – bewusst von Hand, nicht per Uhr.
     *
     * Nutzt die mitgeladene Zählung (withCount as bestaetigte_count), wenn sie
     * da ist; sonst wird sie einzeln geholt.
     */
    public function istNachzubereiten(): bool
    {
        if (!$this->istVergangen() || !in_array($this->status?->value, [self::STATUS_PUBLISHED, self::STATUS_CLOSED], true)) {
            return false;
        }

        $offene = $this->getAttribute('bestaetigte_count');

        if ($offene === null) {
            $offene = $this->bookings()->where('status', Booking::STATUS_CONFIRMED)->count();
        }

        return (int) $offene > 0;
    }

    /**
     * Veröffentlicht, aber der Bestellschluss ist erreicht – der Termin läuft
     * noch, nur bestellen kann niemand mehr. Von Hand gesperrte Termine (Status
     * closed) tragen ihren Zustand schon im Status und sind hier nicht dabei.
     */
    public function istBestellschlussErreicht(): bool
    {
        return $this->status === EventStatus::Published
            && $this->order_deadline_at !== null
            && now()->isAfter($this->order_deadline_at);
    }

    /**
     * Aktive Buchungen in Bon-Reihenfolge: nach Pause, darin nach Gastname.
     *
     * Dieselbe Reihenfolge, in der die Buchungen im VA-Dashboard stehen –
     * damit der Papierstapel zur Ansicht auf dem Schirm passt. Sortiert wird
     * in PHP statt per JOIN, weil die Pausenzeit am Slot hängt und die Liste
     * je Termin klein ist.
     *
     * @return \Illuminate\Support\Collection<int, Booking>
     */
    public function bonBookings(): \Illuminate\Support\Collection
    {
        return $this->bookings()
            ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW])
            ->with(['items.menuItem', 'items.bundleMenuItem', 'table.floorPlan', 'event', 'slot'])
            ->get()
            ->sortBy([
                fn ($b) => (string) ($b->slot?->time_start ?? '99:99'),
                fn ($b) => (string) $b->guest_name,
            ])
            ->values();
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeUpcoming($query)
    {
        return $query->whereDate('date', '>=', now()->toDateString());
    }

    /**
     * Größte Gruppe, die für diesen Termin gebucht werden kann.
     *
     * Eigene Zahl schlägt die Vorgabe des Teams; ohne eigene gilt die Vorgabe.
     * Bewusst nicht beim Anlegen kopiert – ändert das Haus seine Vorgabe,
     * sollen die Termine folgen, die nie eine eigene bekommen haben.
     */
    public function maxGuestCount(?CheckoutSetting $einstellungen = null): int
    {
        $eigen = (int) ($this->max_guest_count ?? 0);

        if ($eigen > 0) {
            return $eigen;
        }

        // Die Einstellungen dürfen mitgegeben werden, wo der Aufrufer sie
        // ohnehin schon geladen hat. CheckoutSetting::forTeam() ist ein
        // firstOrNew ohne Zwischenspeicher - ohne diesen Weg fragt allein die
        // Checkout-Antwort zweimal dieselbe Zeile ab.
        return ($einstellungen ?? CheckoutSetting::forTeam((int) $this->team_id))->maxGuestCount();
    }

    /**
     * Wem gehört ein Tisch – dem Gast für den ganzen Termin oder nur für eine
     * Pause?
     *
     * 'event': Ein Raum, ein Tisch, alle Pausen. Wer in Pause 1 sitzt, hält den
     * Tisch auch in Pause 2 – selbst wenn er dort nichts bestellt.
     * 'slot': Jede Pause wird einzeln vergeben, der Saal zwischen den Pausen neu
     * verkauft.
     *
     * Bei einem Termin mit nur EINER Pause ist die Frage keine: Beide
     * Betriebsarten liefern dieselben Zahlen. Deshalb zeigt das Terminformular
     * das Feld auch erst ab der zweiten Pause – eine Frage ohne Wirkung ist eine
     * Frage zu viel.
     *
     * Eigener Wert schlägt die Vorgabe des Teams; null heißt „der Vorgabe
     * folgen". Wie bei maxGuestCount() bewusst nicht beim Anlegen kopiert.
     */
    public function tableBinding(?CheckoutSetting $einstellungen = null): string
    {
        if ($this->table_binding === self::BINDING_SLOT || $this->table_binding === self::BINDING_EVENT) {
            return $this->table_binding;
        }

        return ($einstellungen ?? CheckoutSetting::forTeam((int) $this->team_id))->tableBinding();
    }

    /** Gilt ein Tisch hier für den ganzen Termin statt nur für eine Pause? */
    public function tischGiltGanzenTermin(?CheckoutSetting $einstellungen = null): bool
    {
        return $this->tableBinding($einstellungen) === self::BINDING_EVENT;
    }

    /**
     * Was dem Termin zum Veröffentlichen fehlt – leer heißt: nichts.
     *
     * DIE Regel, nicht eine von mehreren. Sie stand vorher zweimal und
     * verschieden: Die Oberfläche verlangte Pause UND Raum, das MCP-Werkzeug nur
     * eine Pause. Wer über MCP veröffentlichte, umging die Bedingung also – und
     * ein Termin ohne Raum steht im Shop, ohne dass jemand etwas buchen kann.
     * Es gibt dort schlicht keinen Tisch.
     *
     * Gibt Klartext zurück statt true/false, weil beide Aufrufer dem Nutzer
     * sagen sollen, WAS fehlt – die Oberfläche in einer Meldung, das Werkzeug in
     * seiner Antwort.
     *
     * @return array<int, string>
     */
    public function fehltZumVeroeffentlichen(): array
    {
        $fehlt = [];

        if ($this->slots()->count() < 1) {
            $fehlt[] = 'ein Pausen-Slot';
        }

        if (! $this->eventRooms()->exists()) {
            $fehlt[] = 'ein Raum';
        }

        return $fehlt;
    }

    /** Bestellschluss erreicht? (kein Deadline gesetzt = offen) */
    public function isOrderable(): bool
    {
        if (!$this->status?->allowsOrders()) {
            return false;
        }

        if ($this->order_deadline_at) {
            return !now()->isAfter($this->order_deadline_at);
        }

        // Eine Frist ist Pflicht – Altbestand aus der Zeit davor hat trotzdem
        // keine. Dann endet die Vorbestellung mit dem Veranstaltungstag, statt
        // den Termin ewig offen zu lassen.
        return !$this->istVergangen();
    }

    /** Verkaufsliste für Gäste: Event-Liste, sonst Team-Default. */
    public function resolveSalesList(): ?SalesList
    {
        return $this->salesList ?? SalesList::defaultForTeam($this->team_id);
    }

    /** Ist dieser Tisch für den Termin gesperrt (nicht buchbar)? */
    public function isTableDisabled(int $tableId): bool
    {
        return in_array($tableId, $this->disabled_table_ids ?? [], true);
    }

    /**
     * Verknüpfte Veranstaltung aus dem platforms-events-Modul, sofern das
     * Modul installiert ist (lose Kopplung, kein harter Dependency).
     */
    public function linkedEventsEvent(): ?object
    {
        if (!$this->events_event_id || !class_exists(\Platform\Events\Models\Event::class)) {
            return null;
        }

        return \Platform\Events\Models\Event::find($this->events_event_id);
    }

    /* ---------------------------------------------------------------------
     | Freigabe-Link: Lesezugriff auf Küche und Laufzettel, ohne Konto
     |
     | Bewusst NUR diese beiden Ansichten. Die Buchungsliste enthält Namen und
     | E-Mail-Adressen; ein Link ist ein Schlüssel, und weitergeleitete Mails
     | verteilen ihn weiter, als man denkt.
     --------------------------------------------------------------------- */

    /**
     * Neuen Link samt PIN erzeugen. Gibt die PIN im Klartext zurück – sie wird
     * nur gehasht gespeichert und ist danach nicht mehr auslesbar.
     *
     * Ein vorhandener Link wird dabei ungültig: neuer Token, neue PIN. Das ist
     * zugleich der Weg, einen Link zurückzuziehen.
     */
    public function issueShareAccess(): string
    {
        // Sechs Ziffern sind für Menschen am Telefon zumutbar. Gegen
        // Durchprobieren schützt nicht die Länge, sondern die Drosselung der
        // Versuche (siehe EventPlanController).
        $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->forceFill([
            'share_token'      => Str::random(48),
            'share_pin_hash'   => Hash::make($pin),
            'share_created_at' => now(),
        ])->save();

        return $pin;
    }

    /** Link zurückziehen – sofort ungültig. */
    public function revokeShareAccess(): void
    {
        $this->forceFill([
            'share_token'      => null,
            'share_pin_hash'   => null,
            'share_created_at' => null,
        ])->save();
    }

    /**
     * Bis wann der Link gilt: Tag nach der Veranstaltung, 23:59 Uhr.
     *
     * Abgeleitet statt gespeichert – ein Feld würde veralten, sobald jemand
     * den Termin verschiebt.
     */
    public function shareExpiresAt(): ?Carbon
    {
        return $this->date?->copy()->addDay()->endOfDay();
    }

    public function shareIsActive(): bool
    {
        if (! $this->share_token) {
            return false;
        }

        $bis = $this->shareExpiresAt();

        return $bis === null || $bis->isFuture();
    }

    /** Vollständiger Link zum Weitergeben; null, wenn keiner ausgestellt ist. */
    public function shareUrl(): ?string
    {
        if (! $this->share_token) {
            return null;
        }

        return route('reservation.guest.event.plan', [
            'uuid'  => $this->uuid,
            'token' => $this->share_token,
        ]);
    }

    /** PIN prüfen. Zeitkonstanter Vergleich über Hash::check. */
    public function sharePinMatches(string $pin): bool
    {
        return $this->share_pin_hash !== null && Hash::check($pin, $this->share_pin_hash);
    }

    /** Zugriffe auf den Freigabe-Link, neueste zuerst. */
    public function shareAccesses(): HasMany
    {
        return $this->hasMany(EventShareAccess::class, 'event_id')->latest();
    }
}
