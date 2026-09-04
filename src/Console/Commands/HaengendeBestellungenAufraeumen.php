<?php

namespace Platform\Reservation\Console\Commands;

use Illuminate\Console\Command;
use Platform\Reservation\Models\Order;
use Platform\Reservation\Models\Payment;
use Platform\Reservation\Services\MolliePaymentService;

/**
 * Storniert Bestellungen, die im Bezahlfenster hängen geblieben sind.
 *
 * Bricht ein Gast bei Mollie ab, bleibt seine Buchung „ausstehend" – und der
 * Platz damit belegt. Normalerweise heilt sich das: Mollie meldet die Zahlung
 * nach ihrer Frist als verfallen, der Webhook storniert. Bleibt der Webhook
 * aber aus – App-Ausfall, 500er bis die Wiederholungen erschöpft sind, eine
 * Überweisung mit Tagen Frist –, ist der Platz dauerhaft weg, und niemand
 * merkt es. Vor einem ausverkauften Abend ist das der Unterschied zwischen
 * „voll" und „scheinbar voll".
 *
 * Dies ist der Boden darunter, nicht der Regelweg. Deshalb großzügig
 * eingestellt: Was länger als die angegebene Frist wartet, war kein zögernder
 * Gast mehr.
 *
 * Angefasst wird NUR, was auch wirklich an einer Zahlung hängt: Bestellungen
 * ohne Zahlungssatz (Barzahlung, Backoffice) bleiben unberührt – die sind
 * nicht abgebrochen, sondern werden vor Ort abgerechnet.
 */
class HaengendeBestellungenAufraeumen extends Command
{
    protected $signature = 'reservation:bestellungen-aufraeumen
                            {--stunden=24 : Ab welchem Alter eine wartende Bestellung als abgebrochen gilt}
                            {--dry-run : Nur zeigen, was passieren würde}';

    protected $description = 'Storniert Bestellungen, die seit Stunden auf eine Zahlung warten, und gibt die Plätze frei.';

    public function handle(MolliePaymentService $zahlungen): int
    {
        $stunden = max(1, (int) $this->option('stunden'));
        $probe   = (bool) $this->option('dry-run');
        $grenze  = now()->subHours($stunden);

        // Ohne Team-Scope: Das Kommando läuft ohne angemeldeten Menschen und
        // gilt für alle Mandanten.
        $haengende = Order::withoutGlobalScope('team')
            ->where('status', Order::STATUS_PENDING)
            ->where('created_at', '<', $grenze)
            ->whereHas('payment', fn ($q) => $q->where('status', '!=', 'paid'))
            ->with('payment')
            ->get();

        if ($haengende->isEmpty()) {
            $this->info('Nichts zu tun – keine hängenden Bestellungen älter als ' . $stunden . ' h.');

            return self::SUCCESS;
        }

        $storniert = 0;

        foreach ($haengende as $order) {
            $zeile = sprintf(
                '#%d · %s · Zahlung %s · seit %s',
                $order->id,
                $order->created_at?->format('d.m.Y H:i') ?? '?',
                $order->payment?->status ?? '—',
                $order->created_at?->diffForHumans() ?? '?',
            );

            if ($probe) {
                $this->line('  würde stornieren: ' . $zeile);

                continue;
            }

            // Über denselben gesperrten Weg wie der Webhook. Nicht, weil es
            // bequem ist, sondern weil sonst zwei Stellen entscheiden, was
            // „stornieren" heißt – und eine gerade eintreffende späte
            // Zahlungsmeldung würde mit diesem Lauf kollidieren.
            $zahlungen->zustandUebernehmen($order, bezahlt: false, fehlgeschlagen: true);

            $storniert++;
            $this->line('  storniert: ' . $zeile);
        }

        $this->info($probe
            ? $haengende->count() . ' Bestellung(en) würden storniert.'
            : $storniert . ' Bestellung(en) storniert, Plätze wieder frei.');

        return self::SUCCESS;
    }
}
