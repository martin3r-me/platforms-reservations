<?php

namespace Platform\Reservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Platform\Reservation\Models\Concerns\BelongsToTeam;
use Symfony\Component\Uid\UuidV7;

class Booking extends Model
{
    use BelongsToTeam;

    protected $table = 'reservation_bookings';

    // Mögliche Status-Werte
    const STATUS_PENDING   = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_NO_SHOW   = 'no_show';
    const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'uuid',
        'team_id',
        'order_id',
        'table_id',
        'pickup_station_id',
        'place_kind',
        'place_label',
        'event_id',
        'event_slot_id',
        'guest_name',
        'guest_email',
        'guest_phone',
        'guest_count',
        'notes',
        'date',
        'time_start',
        'time_end',
        'status',
        'age_check_confirmed_at',
        'legal_accepted_at',
        'payment_method',
        'mollie_payment_id',
    ];

    protected $casts = [
        'date'                   => 'date',
        'guest_count'            => 'integer',
        'age_check_confirmed_at' => 'datetime',
        'legal_accepted_at'      => 'datetime',
    ];

    /**
     * Schalter für Statuswechsel ohne automatischen Bon-Druck.
     *
     * Gedacht für Korrekturen: Wer eine irrtümlich als No-Show markierte
     * Buchung zurück auf "bestätigt" setzt, hat den Bon längst in der Hand.
     * Ohne diesen Schalter käme ein zweiter aus dem Drucker, den niemand
     * bestellt hat - und in der Küche stünde dieselbe Bestellung zweimal.
     */
    protected static bool $autoDruckAus = false;

    /**
     * Einen Vorgang ausführen, ohne dass ein Wechsel auf "bestätigt" druckt.
     *
     * Bewusst als Klammer um den Vorgang statt als Flag am Model: Der Zustand
     * wird danach in jedem Fall zurückgesetzt, auch wenn zwischendrin etwas
     * schiefgeht. Ein liegengebliebenes Flag würde den automatischen Druck
     * für den Rest der Anfrage still abschalten.
     */
    public static function ohneAutoDruck(callable $vorgang): mixed
    {
        $vorher = static::$autoDruckAus;
        static::$autoDruckAus = true;

        try {
            return $vorgang();
        } finally {
            static::$autoDruckAus = $vorher;
        }
    }

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) UuidV7::generate();
            }

            $model->pruefeZielort();
        });

        // Beim Aktualisieren nur „nicht beides", nicht „genau eines".
        //
        // nullOnDelete entzieht einer bestehenden Buchung ihren Tisch, wenn der
        // gelöscht wird - solche Datensätze gibt es heute schon, und sie müssen
        // speicherbar bleiben. Sie jetzt zu blockieren hieße, dass sich eine
        // alte Buchung nicht einmal mehr stornieren lässt.
        static::updating(function (self $model) {
            if ($model->table_id && $model->pickup_station_id) {
                throw new \LogicException('Eine Buchung kann nicht an einem Tisch UND an einer Abholstation liegen.');
            }
        });

        // Automatischer Bon-Druck beim Wechsel auf "bestätigt".
        //
        // Bewusst am Statuswechsel statt an einer Stelle im Ablauf: Bestätigt
        // wird nach der Zahlung, von Hand in der Buchungsliste und über die
        // Freigabe im Posteingang. wasChanged() liefert nur bei einem echten
        // Wechsel true, ein erneutes Speichern druckt also nicht noch einmal.
        static::updated(function (self $model) {
            if (static::$autoDruckAus) {
                return;
            }

            if ($model->status !== self::STATUS_CONFIRMED) {
                return;
            }

            if (! $model->wasChanged('status')) {
                return;
            }

            app(\Platform\Reservation\Services\AutoPrintService::class)->printBooking($model);
        });

        // Falls eine Buchung je direkt als bestätigt angelegt wird. Heute tut
        // das kein Weg – Gast-Checkout und Backoffice legen beide "pending" an –,
        // aber die Regel soll lauten: eine bestätigte Buchung wird einmal
        // gedruckt, nicht nur eine, die vorher pending war.
        static::created(function (self $model) {
            if (static::$autoDruckAus) {
                return;
            }

            if ($model->status !== self::STATUS_CONFIRMED) {
                return;
            }

            app(\Platform\Reservation\Services\AutoPrintService::class)->printBooking($model);
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class, 'table_id');
    }

    /** Der zweite mögliche Zielort – statt eines Tisches, nie zusätzlich. */
    public function pickupStation(): BelongsTo
    {
        return $this->belongsTo(PickupStation::class, 'pickup_station_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(EventSlot::class, 'event_slot_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class, 'booking_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /* ---------------------------------------------------------------------
     | Der Ort einer Buchung
     |
     | EINE Auskunftsstelle für alle Anzeigen – Bon, Beleg, Mails, Listen,
     | Laufzettel, Export. Ohne sie stünde dieselbe Fallunterscheidung an
     | einem Dutzend Stellen, und die erste Abweichung fiele niemandem auf.
     |
     | Gelesen wird in dieser Reihenfolge:
     |   1. der lebende Tisch – ein umbenannter Tisch heißt überall gleich,
     |      auch auf alten Listen
     |   2. der eingefrorene Stand, wenn der Tisch gelöscht wurde
     |   3. nichts, bei Altbestand ohne beides
     --------------------------------------------------------------------- */

    /** Ort der Buchung: Art, Bezeichnung, Raum und ob er noch existiert. */
    /**
     * Genau ein Zielort – beim Anlegen.
     *
     * Keiner wäre eine Buchung ins Nichts, beides eine Buchung an zwei Orte.
     * Beides ist ein Programmfehler, kein Eingabefehler: Es gibt genau einen
     * Erzeuger von Buchungen (GuestOrderService::store), und der weiß, was er
     * tut. Deshalb eine Ausnahme und keine Validierungsmeldung.
     *
     * Ausdrücklich NUR beim Anlegen. Ein Hook auf jedem Speichern liefe im
     * Livebetrieb auch beim Statuswechsel mit, an dem der automatische Bondruck
     * hängt - wirft er dort zu Unrecht, steht die Bestellstrecke.
     */
    protected function pruefeZielort(): void
    {
        $tisch   = (bool) $this->table_id;
        $station = (bool) $this->pickup_station_id;

        if ($tisch === $station) {
            throw new \LogicException($tisch
                ? 'Eine Buchung kann nicht an einem Tisch UND an einer Abholstation liegen.'
                : 'Eine Buchung braucht einen Zielort: einen Tisch oder eine Abholstation.');
        }
    }

    public function zielort(): array
    {
        // getRelationValue statt $this->table - und das ist kein Geschmack:
        // Eloquent hat eine geschuetzte Eigenschaft $table mit dem Namen der
        // Datenbanktabelle. Von aussen liefert $booking->table die Beziehung
        // (ueber __get), INNERHALB des Models aber gewinnt die Eigenschaft, und
        // $this->table ist die Zeichenkette 'reservation_bookings'.
        // getRelationValue nimmt die geladene Beziehung, wenn sie da ist, und
        // laedt sie sonst nach - Eager Loading bleibt also wirksam.
        $tisch = $this->getRelationValue('table');

        if ($tisch) {
            return [
                'art'    => 'table',
                'label'  => $tisch->label,
                'raum'   => $tisch->floorPlan?->name,
                'weg'    => false,
            ];
        }

        // Die Station wird NACH dem Tisch gefragt, obwohl beides nie zugleich
        // gesetzt ist: Der Tisch ist der haeufigere Fall, und die Reihenfolge
        // spart im Regelbetrieb eine Beziehung.
        $station = $this->getRelationValue('pickupStation');

        if ($station) {
            return [
                'art'    => 'station',
                'label'  => $station->name,
                // Der Raum, IN dem sie liegt - viele Stationen liegen in keinem.
                'raum'   => $station->floorPlan?->name,
                'weg'    => false,
            ];
        }

        if ($this->place_label) {
            return [
                'art'    => $this->place_kind ?: 'table',
                'label'  => $this->place_label,
                'raum'   => null,
                'weg'    => true,
            ];
        }

        return ['art' => null, 'label' => null, 'raum' => null, 'weg' => false];
    }

    /**
     * Bezeichnung des Orts, oder null wenn keiner bekannt ist.
     *
     * Ohne Zusatz – ob der Ort gelöscht wurde, beantwortet zielortFehlt().
     * Die Anzeigen entscheiden selbst, wie sie das kennzeichnen: Auf einem
     * 48 Zeichen breiten Bon ist ein Klammerzusatz teurer als in einer Liste.
     */
    public function zielortLabel(): ?string
    {
        return $this->zielort()['label'];
    }

    /** Ist der Ort nur noch eingefroren vorhanden, also gelöscht worden? */
    public function zielortFehlt(): bool
    {
        return $this->zielort()['weg'];
    }

    /** Gruppierungsschlüssel für Laufzettel und Küche. */
    public function zielortSchluessel(): string
    {
        $ort = $this->zielort();

        if ($this->table_id) {
            return 'table-' . $this->table_id;
        }

        if ($this->pickup_station_id) {
            return 'station-' . $this->pickup_station_id;
        }

        return $ort['label'] ? 'weg-' . $ort['label'] : 'ohne';
    }

    /**
     * Zahlung der Buchung – hängt seit der Order-Klammer an der Order.
     * Accessor, damit bestehende Lesestellen ($booking->payment) weiter
     * funktionieren; für Eager-Loading 'order.payment' verwenden.
     */
    public function getPaymentAttribute(): ?Payment
    {
        return $this->order?->payment;
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeForDate($query, string $date)
    {
        return $query->whereDate('date', $date);
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [self::STATUS_CANCELLED, self::STATUS_NO_SHOW]);
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function getTotalAmountAttribute(): float
    {
        return $this->items->sum(fn ($item) => $item->unit_price * $item->quantity);
    }

    /** Enthält die Buchung alkoholische Artikel (→ 18+-Check erforderlich)? */
    public function getRequiresAgeCheckAttribute(): bool
    {
        return $this->items()
            ->whereHas('menuItem', fn ($q) => $q->where('is_alcoholic', true))
            ->exists();
    }
}
