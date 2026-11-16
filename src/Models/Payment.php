<?php

namespace Platform\Reservation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $table = 'reservation_payments';

    protected $fillable = [
        'order_id',
        'mollie_id',
        'amount',
        'currency',
        'status',
        'method',
        'paid_at',
        'refunded_at',
        'refunded_amount',
        'metadata',
    ];

    protected $casts = [
        'amount'          => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'paid_at'         => 'datetime',
        'refunded_at'     => 'datetime',
        'metadata'        => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Zahlart in Worten.
     *
     * Mollie liefert Schluessel ("creditcard"), im Haus liest sie jemand, der
     * mit Mollie nichts zu tun hat. Unbekannte Schluessel kommen unveraendert
     * durch - besser ein fremdes Wort als gar keins, und eine neue Zahlart
     * soll hier nicht stumm verschwinden.
     */
    public function methodeLabel(): ?string
    {
        if (! $this->method) {
            return null;
        }

        return [
            'creditcard'   => 'Kreditkarte',
            'card'         => 'Karte',
            'paypal'       => 'PayPal',
            'applepay'     => 'Apple Pay',
            'ideal'        => 'iDEAL',
            'sofort'       => 'Sofortüberweisung',
            'banktransfer' => 'Überweisung',
            'giropay'      => 'Giropay',
            'eps'          => 'EPS',
            'bancontact'   => 'Bancontact',
            'onsite'       => 'Vor Ort',
        ][$this->method] ?? $this->method;
    }

    /**
     * Was mit dem Geld passiert ist – in einem Satz.
     *
     * Die wichtigste Zeile steht bei einer NICHT zustande gekommenen Zahlung.
     * Eine stornierte Buchung sah bisher aus wie jede andere stornierte: Ob
     * das Haus sie abgesagt hat oder ob der Gast an der Kasse nicht
     * durchkam, stand nirgends. Das sind sehr verschiedene Abende - beim
     * zweiten hat jemand bestellen WOLLEN.
     *
     * Die versuchte Zahlart gehört dazu: Sie ist die einzige Spur, warum es
     * nicht geklappt hat, und sie steht sonst nur in Mollie.
     *
     * @return array{text: string, art: string} art = ok|offen|fehlgeschlagen|zurueck
     */
    public function zustandText(): array
    {
        $wie = $this->methodeLabel();
        $per = $wie ? ' · ' . $wie : '';

        if ($this->status === 'charged_back') {
            return ['art' => 'zurueck', 'text' => 'Die Bank hat die Zahlung zurückgebucht' . $per];
        }

        if ($this->refunded_at) {
            return ['art' => 'zurueck', 'text' => 'Erstattet' . $per];
        }

        if ($this->isPaid()) {
            $wann = $this->paid_at?->format('d.m.Y H:i');

            return [
                'art'  => 'ok',
                'text' => 'Bezahlt' . ($wann ? ' am ' . $wann . ' Uhr' : '') . $per,
            ];
        }

        if (in_array($this->status, Order::ZAHLUNG_GESCHEITERT, true)) {
            // Abgelaufen heisst: Der Gast war bei der Zahlung und hat nicht
            // abgeschlossen. Fehlgeschlagen heisst: Es wurde abgelehnt. Das
            // ist fuer eine Rueckfrage beim Gast ein Unterschied.
            $grund = match ($this->status) {
                'expired'  => 'Die Zahlung wurde nicht abgeschlossen und ist abgelaufen',
                'failed'   => 'Die Zahlung wurde abgelehnt',
                'canceled' => 'Die Zahlung wurde abgebrochen',
                default    => 'Die Zahlung kam nicht zustande',
            };

            return [
                'art'  => 'fehlgeschlagen',
                'text' => $grund . ($wie ? ' · versucht mit ' . $wie : ' · es wurde keine Zahlart gewählt'),
            ];
        }

        return ['art' => 'offen', 'text' => 'Zahlung läuft noch' . $per];
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isRefunded(): bool
    {
        return $this->refunded_at !== null;
    }
}
