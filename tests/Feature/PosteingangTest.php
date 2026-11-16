<?php

namespace Platform\Reservation\Tests\Feature;

use Platform\Reservation\Models\Order;
use Platform\Reservation\Models\Payment;
use Platform\Reservation\Tests\Concerns\ErzeugtTermine;
use Platform\Reservation\Tests\TestCase;

/**
 * Der Posteingang trennt Nachrichten von Aufgaben.
 *
 * Bei Culinaria stand die Zahl 126 in der Seitenleiste: 102 neue
 * Bestellungen, 24 gescheiterte Zahlungen, NULL Entscheidungen. Eine Zahl,
 * die seit Wochen dreistellig ist, liest niemand mehr - und eine
 * Stornierungsanfrage waere darin untergegangen.
 *
 * Zwei Regeln sichert dieser Lauf:
 * 1. "Zu entscheiden" haengt am STATUS, nicht an seen_at. Sonst liesse
 *    "Alle als gesehen" eine offene Entscheidung verschwinden.
 * 2. "Zahlung gescheitert" ist mehr als storniert - erst die Zahlung sagt,
 *    ob der Gast nicht durchkam oder das Haus abgesagt hat.
 */
class PosteingangTest extends TestCase
{
    use ErzeugtTermine;

    private function bestellungMitZahlung(string $orderStatus, ?string $zahlStatus, ?string $methode = null): Order
    {
        $termin     = $this->termin(1);
        $bestellung = $this->bestellung($termin);

        $bestellung->update(['status' => $orderStatus]);

        if ($zahlStatus) {
            Payment::create([
                'order_id'  => $bestellung->id,
                'mollie_id' => 'tr_' . uniqid(),
                'amount'    => 27.00,
                'currency'  => 'EUR',
                'status'    => $zahlStatus,
                'method'    => $methode,
            ]);
        }

        return $bestellung->fresh();
    }

    public function test_zu_entscheiden_zaehlt_auch_gesehene(): void
    {
        $offen = $this->bestellungMitZahlung(Order::STATUS_CANCELLATION_REQUESTED, 'paid', 'creditcard');

        $this->assertSame(1, Order::query()->zuEntscheiden()->count());

        // Jemand klickt "Alle als gesehen". Entschieden ist damit nichts.
        $offen->update(['seen_at' => now()]);

        $this->assertSame(
            1,
            Order::query()->zuEntscheiden()->count(),
            'Eine offene Entscheidung darf nicht verschwinden, nur weil jemand hingesehen hat.',
        );
    }

    public function test_entschieden_faellt_heraus(): void
    {
        $offen = $this->bestellungMitZahlung(Order::STATUS_CANCELLATION_REQUESTED, 'paid');

        // Abgelehnt: zurueck auf bestaetigt.
        $offen->update(['status' => Order::STATUS_CONFIRMED, 'cancellation_requested_at' => null]);

        $this->assertSame(0, Order::query()->zuEntscheiden()->count());
    }

    public function test_eine_neue_bestellung_ist_keine_aufgabe(): void
    {
        $this->bestellungMitZahlung(Order::STATUS_CONFIRMED, 'paid', 'paypal');

        $this->assertSame(0, Order::query()->zuEntscheiden()->count());
    }

    public function test_gescheiterte_zahlung_haengt_an_der_zahlung_nicht_am_status(): void
    {
        $this->bestellungMitZahlung(Order::STATUS_CANCELLED, 'expired', 'creditcard');

        // Vom Haus storniert, Geld war da - das ist etwas anderes.
        $this->bestellungMitZahlung(Order::STATUS_CANCELLED, 'paid', 'creditcard');

        // Storniert ganz ohne Zahlungssatz (Backoffice-Weg).
        $this->bestellungMitZahlung(Order::STATUS_CANCELLED, null);

        $this->assertSame(
            1,
            Order::query()->zahlungGescheitert()->count(),
            'Nur die Bestellung, bei der die Zahlung selbst nicht zustande kam.',
        );
    }

    public function test_der_satz_zur_zahlung_nennt_grund_und_zahlart(): void
    {
        $abgelaufen = $this->bestellungMitZahlung(Order::STATUS_CANCELLED, 'expired', 'creditcard');
        $zustand    = $abgelaufen->payment->zustandText();

        $this->assertSame('fehlgeschlagen', $zustand['art']);
        $this->assertStringContainsString('abgelaufen', $zustand['text']);
        $this->assertStringContainsString('Kreditkarte', $zustand['text']);

        // Abgelehnt ist nicht dasselbe wie abgelaufen - fuer eine Rueckfrage
        // beim Gast ist das der Unterschied.
        $abgelehnt = $this->bestellungMitZahlung(Order::STATUS_CANCELLED, 'failed', 'paypal');

        $this->assertStringContainsString('abgelehnt', $abgelehnt->payment->zustandText()['text']);
        $this->assertStringContainsString('PayPal', $abgelehnt->payment->zustandText()['text']);
    }

    public function test_ohne_gewaehlte_zahlart_steht_das_auch_da(): void
    {
        $ohne = $this->bestellungMitZahlung(Order::STATUS_CANCELLED, 'expired', null);

        $this->assertStringContainsString(
            'keine Zahlart gewählt',
            $ohne->payment->zustandText()['text'],
            'Ob der Gast ueberhaupt eine Zahlart gewaehlt hat, ist die entscheidende Spur.',
        );
    }

    public function test_bezahlt_nennt_zeitpunkt_und_zahlart(): void
    {
        $bezahlt = $this->bestellungMitZahlung(Order::STATUS_CONFIRMED, 'paid', 'creditcard');
        $bezahlt->payment->update(['paid_at' => '2026-09-01 10:15:00']);

        $zustand = $bezahlt->payment->fresh()->zustandText();

        $this->assertSame('ok', $zustand['art']);
        $this->assertStringContainsString('01.09.2026 10:15', $zustand['text']);
        $this->assertStringContainsString('Kreditkarte', $zustand['text']);
    }
}
