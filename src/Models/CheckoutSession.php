<?php

namespace Platform\Reservation\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\Reservation\Models\Concerns\BelongsToTeam;
use Platform\Reservation\Support\CheckoutSteps;

/**
 * Ein laufender Bestellweg im Shop – noch keine Buchung.
 *
 * Kurzlebig mit Absicht: Der Shop meldet den Stand, die Zeile wird
 * überschrieben, nach einer halben Stunde ohne Lebenszeichen ist sie weg. Kein
 * Personenbezug (siehe Migration), keine Reservierung – was hier steht, hält
 * KEINEN Platz frei.
 */
class CheckoutSession extends Model
{
    use BelongsToTeam;

    protected $table = 'reservation_checkout_sessions';

    /**
     * Ab wann ein Eintrag als tot gilt.
     *
     * Der Shop meldet sich jede Minute (wire:poll im Bestellweg). Drei Minuten
     * verzeihen einen verlorenen Herzschlag, ohne dass jemand, der den Tab
     * geschlossen hat, lange herumsteht.
     */
    public const LEBT_MINUTEN = 3;

    /**
     * Ab wann ein Eintrag gelöscht wird.
     *
     * Deutlich später als LEBT_MINUTEN, und das ist kein Widerspruch: Zwischen
     * beiden Zahlen liegt die Zeit, in der ein Gast zurückkommen und seinen
     * Bestellweg fortsetzen kann, ohne als neuer Vorgang zu zählen.
     */
    public const AUFRAEUMEN_NACH_MINUTEN = 30;

    protected $fillable = [
        'team_id',
        'event_id',
        'event_slot_id',
        'checkout_ref',
        'step',
        'step_no',
        'step_count',
        'party_size',
        'items_count',
        'items',
        'tables',
        'cart_total',
        'last_seen_at',
    ];

    protected $casts = [
        'step_no'      => 'integer',
        'step_count'   => 'integer',
        'party_size'   => 'integer',
        'items_count'  => 'integer',
        'items'        => 'array',
        'tables'       => 'array',
        'cart_total'   => 'decimal:2',
        'last_seen_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(EventSlot::class, 'event_slot_id');
    }

    /** Nur die, die gerade wirklich jemand offen hat. */
    public function scopeLebendig(Builder $query): Builder
    {
        return $query->where('last_seen_at', '>=', now()->subMinutes(self::LEBT_MINUTEN));
    }

    public function scopeForEvent(Builder $query, int $eventId): Builder
    {
        return $query->where('event_id', $eventId);
    }

    /** Der Schritt, wie er im Shop heißt – benannt in CheckoutSteps. */
    public function schrittLabel(): string
    {
        return CheckoutSteps::label((string) $this->step);
    }

    /**
     * „Schritt 3 von 5" – oder null, wenn der Shop es nicht mitgeteilt hat.
     *
     * Beide Zahlen kommen vom Shop, weil nur er weiß, aus wie vielen Schritten
     * DIESER Bestellweg besteht (siehe Migration). Fehlen sie, steht hier
     * nichts, statt eine Zahl zu raten: Ein Termin mit einer Pause hat einen
     * Schritt weniger, und eine falsche Angabe wäre schlimmer als keine.
     *
     * Der Intro-Schritt bekommt keine Nummer, weil er beim Gast keinen
     * Stepper-Kreis hat.
     */
    public function fortschritt(): ?string
    {
        if (! $this->step_no || ! $this->step_count) {
            return null;
        }

        return 'Schritt ' . $this->step_no . ' von ' . $this->step_count;
    }

    /**
     * Wie lange der Gast schon dabei ist, in vollen Minuten.
     *
     * Ab dem ERSTEN Lebenszeichen gerechnet, nicht ab dem letzten: Die Frage
     * lautet „wie lange hängt der schon?", nicht „wann war er zuletzt da?".
     */
    public function dauerMinuten(): int
    {
        return $this->created_at ? (int) $this->created_at->diffInMinutes(now()) : 0;
    }

    /** Liegt überhaupt etwas im Korb, das man sich ansehen könnte? */
    public function hatWarenkorb(): bool
    {
        return ! empty($this->items);
    }

    /**
     * Gibt es etwas zu zeigen – Artikel ODER einen angeklickten Tisch?
     *
     * Der Tisch zählt mit: Beim Sitzplatz-Schritt kann er an einer Pause
     * hängen, in der noch nichts im Korb liegt.
     */
    public function hatDetails(): bool
    {
        return ! empty($this->items) || ! empty($this->tables);
    }

    /** Steht der Gast am letzten Schritt, also unmittelbar vor der Zahlung? */
    public function fastFertig(): bool
    {
        return in_array($this->step, CheckoutSteps::FAST_FERTIG, true);
    }
}
