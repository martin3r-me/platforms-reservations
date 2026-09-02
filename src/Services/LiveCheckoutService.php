<?php

namespace Platform\Reservation\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Platform\Reservation\Models\CheckoutSession;
use Platform\Reservation\Models\CheckoutStat;
use Platform\Reservation\Models\Event;

/**
 * Laufende Bestellwege: melden, beenden, abfragen, aufräumen.
 *
 * Die einzige Stelle, die reservation_checkout_sessions schreibt. Alle Abfragen
 * laufen mit ausdrücklichem Team statt über den globalen Scope – auf dem
 * API-Weg ist ein User eingeloggt (Passport-Token), dessen aktives Team nicht
 * das des Termins sein muss. Gleiches Muster wie im EventController.
 */
class LiveCheckoutService
{
    /** So viele verschiedene Positionen merkt sich eine Zeile hoechstens. */
    public const GRENZE_POSITIONEN = 100;

    /**
     * Stand eines Bestellwegs melden. Legt an oder überschreibt.
     *
     * Das Team kommt aus dem TERMIN, nie aus der Anfrage: Wer den Endpunkt
     * aufruft, bestimmt damit nicht, in wessen Haus die Zeile landet.
     *
     * @param  array<string, mixed>  $daten
     */
    public function merken(Event $event, string $ref, array $daten): CheckoutSession
    {
        $this->vielleichtAufraeumen();

        return CheckoutSession::withoutGlobalScope('team')->updateOrCreate(
            [
                'team_id'      => (int) $event->team_id,
                'checkout_ref' => $ref,
            ],
            [
                'event_id'      => $event->id,
                'event_slot_id' => $this->eigenePause($event, $daten['event_slot_id'] ?? null),
                'step'          => (string) $daten['step'],
                // Beide Zahlen unveraendert vom Shop. Sie hier nachzurechnen
                // waere eine zweite Fassung der Schritt-Liste - siehe Migration.
                'step_no'       => $this->zahlOderNull($daten['step_no'] ?? null),
                'step_count'    => $this->zahlOderNull($daten['step_count'] ?? null),
                'party_size'    => isset($daten['party_size']) ? max(0, (int) $daten['party_size']) : null,
                'items_count'   => max(0, (int) ($daten['items_count'] ?? 0)),
                'items'         => $this->warenkorb($event, $daten['items'] ?? null),
                'tables'        => $this->tische($event, $daten['tables'] ?? null),
                'cart_total'    => round(max(0, (float) ($daten['cart_total'] ?? 0)), 2),
                'last_seen_at'  => now(),
            ],
        );
    }

    /**
     * Bestellweg abgeschlossen – Zeile weg, Statistikzeile bleibt.
     *
     * Aufgerufen vom Shop nach erfolgreicher Bestellung. Der Ausgang ist
     * deshalb „bestellt": Nur dieser eine Weg fuehrt hierher, alles andere
     * laeuft irgendwann im Aufraeumen aus.
     */
    public function beenden(Event $event, string $ref): void
    {
        $zeile = CheckoutSession::withoutGlobalScope('team')
            ->where('team_id', (int) $event->team_id)
            ->where('checkout_ref', $ref)
            ->first();

        // Der Termin steht schon hier - kein zweiter Zugriff darauf.
        $zeile?->setRelation('event', $event);

        if (! $zeile) {
            return;
        }

        $this->verbuchen([$zeile], CheckoutStat::AUSGANG_BESTELLT);

        $zeile->delete();
    }

    /**
     * Was gerade läuft, neueste zuerst.
     *
     * @return Collection<int, CheckoutSession>
     */
    public function laufende(Event $event): Collection
    {
        return CheckoutSession::withoutGlobalScope('team')
            ->where('team_id', (int) $event->team_id)
            ->forEvent($event->id)
            ->lebendig()
            ->with(['slot' => fn ($q) => $q->withoutGlobalScope('team')])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Die drei Zahlen über der Liste.
     *
     * `cart_total` ist ausdrücklich KEIN erwarteter Umsatz – in jedem dieser
     * Warenkörbe kann noch alles passieren. Die Zahl beantwortet nur, ob dort
     * gerade etwas Nennenswertes hängt.
     *
     * @return array{anzahl: int, gaeste: int, warenkorb: float}
     */
    public function zusammenfassung(Collection $laufende): array
    {
        return [
            'anzahl'    => $laufende->count(),
            'gaeste'    => (int) $laufende->sum('party_size'),
            'warenkorb' => round((float) $laufende->sum(fn (CheckoutSession $s) => (float) $s->cart_total), 2),
        ];
    }

    /**
     * Alles, was lange nichts mehr von sich hören ließ.
     *
     * Hier entsteht die Zahl, die spaeter jemanden interessiert: Wer bis zum
     * Ende nichts mehr gemeldet hat, hat abgebrochen. Ein anderer Weg, das
     * festzustellen, waere schwer zu finden - der Gast schliesst den Tab, er
     * sagt nicht Bescheid.
     *
     * Erst schreiben, dann loeschen, und beides in einer Transaktion: Sonst
     * kann genau hier ein Vorgang spurlos verschwinden.
     */
    public function aufraeumen(): int
    {
        // Mit dem Termin, sonst holt verbuchen() ihn je Zeile einzeln.
        $tot = CheckoutSession::withoutGlobalScope('team')
            ->with(['event' => fn ($q) => $q->withoutGlobalScope('team')])
            ->where('last_seen_at', '<', now()->subMinutes(CheckoutSession::AUFRAEUMEN_NACH_MINUTEN))
            ->get();

        if ($tot->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($tot) {
            $this->verbuchen($tot, CheckoutStat::AUSGANG_ABGEBROCHEN);

            return CheckoutSession::withoutGlobalScope('team')
                ->whereIn('id', $tot->pluck('id'))
                ->delete();
        });
    }

    /**
     * Aus beendeten Bestellwegen Statistikzeilen machen.
     *
     * Was hier NICHT mitkommt, ist der Punkt: keine Kennung, kein
     * Warenkorb-Inhalt, kein Tisch. Damit ist die Zeile auf keinen Besuch mehr
     * beziehbar und darf bleiben, waehrend die Live-Zeile geht.
     *
     * Die Dauer ab dem ERSTEN Lebenszeichen: „wie lange hat der gebraucht,
     * bevor er aufgab" - nicht der Abstand zur letzten Regung.
     *
     * `ended_at` ist das LETZTE LEBENSZEICHEN, nicht der Zeitpunkt des
     * Verbuchens. Das ist kein Detail: Aufgeraeumt wird per Los beim Schreiben,
     * und auf einer ruhigen Seite kann das lange dauern. Stuende hier now(),
     * landete ein Abbruch vom Maerz in der Auswertung fuer den Juni - sichtbar
     * waere das nicht, die Zahl saehe nur falsch aus.
     *
     * @param  iterable<CheckoutSession>  $zeilen
     */
    protected function verbuchen(iterable $zeilen, string $ausgang): void
    {
        $jetzt = now();
        $reihen = [];

        foreach ($zeilen as $zeile) {
            $reihen[] = [
                'team_id'          => (int) $zeile->team_id,
                'event_id'         => $zeile->event_id,
                // Mitgeschrieben statt nachgeschlagen: Es soll den Termin
                // ueberleben, der spaeter geloescht werden darf.
                'event_date'       => $zeile->event?->date?->toDateString(),
                'last_step'        => $zeile->step,
                'step_no'          => $zeile->step_no,
                'step_count'       => $zeile->step_count,
                'outcome'          => $ausgang,
                'party_size'       => $zeile->party_size,
                'items_count'      => $zeile->items_count,
                'cart_total'       => $zeile->cart_total,
                'duration_seconds' => $zeile->created_at
                    ? max(0, $zeile->created_at->diffInSeconds($zeile->last_seen_at ?? $jetzt))
                    : 0,
                'ended_at'         => $zeile->last_seen_at ?? $jetzt,
            ];
        }

        if ($reihen !== []) {
            CheckoutStat::insert($reihen);
        }
    }

    /**
     * Den gemeldeten Warenkorb auf das reduzieren, was Hand und Fuss hat.
     *
     * Erwartet { pause_id: { artikel_id: menge } }. Alles andere fliegt raus:
     * fremde Pausen (sonst stuende in der Ansicht eines Termins der Warenkorb
     * eines anderen), krumme Mengen, und ab GRENZE_POSITIONEN auch schlicht zu
     * viel. Die Grenze ist kein Misstrauen gegen den Shop, sondern gegen die
     * Zeile: Sie soll klein bleiben, sie lebt Minuten.
     *
     * @return array<string, array<string, int>>|null
     */
    protected function warenkorb(Event $event, mixed $roh): ?array
    {
        if (! is_array($roh) || $roh === []) {
            return null;
        }

        $event->loadMissing('slots');
        $eigene = $event->slots->pluck('id')->all();

        $sauber = [];
        $anzahl = 0;

        foreach ($roh as $slotId => $positionen) {
            if (! is_array($positionen) || ! in_array((int) $slotId, $eigene, true)) {
                continue;
            }

            foreach ($positionen as $artikelId => $menge) {
                $artikelId = (int) $artikelId;
                $menge     = (int) $menge;

                if ($artikelId < 1 || $menge < 1) {
                    continue;
                }

                if (++$anzahl > self::GRENZE_POSITIONEN) {
                    break 2;
                }

                $sauber[(string) (int) $slotId][(string) $artikelId] = min($menge, 999);
            }
        }

        return $sauber === [] ? null : $sauber;
    }

    /**
     * Die angeklickten Tische, { pause_id: tisch_id }.
     *
     * Geprueft wird hier nur die Pause. OB der Tisch zu diesem Termin gehoert,
     * entscheidet die Ansicht beim Nachschlagen - ein fremder loest sich dort
     * nicht auf. Das spart auf diesem Weg eine Abfrage ueber Tischplaene und
     * Tische, und der laeuft bei JEDER Meldung.
     *
     * @return array<string, int>|null
     */
    protected function tische(Event $event, mixed $roh): ?array
    {
        if (! is_array($roh) || $roh === []) {
            return null;
        }

        $event->loadMissing('slots');
        $eigene = $event->slots->pluck('id')->all();

        $sauber = [];

        foreach ($roh as $slotId => $tischId) {
            if (! in_array((int) $slotId, $eigene, true) || (int) $tischId < 1) {
                continue;
            }

            $sauber[(string) (int) $slotId] = (int) $tischId;
        }

        return $sauber === [] ? null : $sauber;
    }

    /** Kleine positive Zahl oder null - alles andere sagt nichts. */
    protected function zahlOderNull(mixed $wert): ?int
    {
        $zahl = (int) $wert;

        return $zahl > 0 ? min($zahl, 255) : null;
    }

    /**
     * Die gemeldete Pause muss zu DIESEM Termin gehören.
     *
     * Sonst stünde in der Ansicht eines Termins die Pause eines anderen – und
     * bei fremdem Team wäre es ein Datenleck über die Beschriftung.
     */
    protected function eigenePause(Event $event, mixed $slotId): ?int
    {
        $slotId = (int) $slotId;

        if ($slotId < 1) {
            return null;
        }

        $event->loadMissing('slots');

        return $event->slots->contains('id', $slotId) ? $slotId : null;
    }

    /**
     * Aufräumen per Los, 2 von 100 Schreibvorgängen.
     *
     * Ohne Zeitplaner mit Absicht: Ein Cronjob wäre eine Abhängigkeit zur
     * Wirtsanwendung, die das Modul sonst nirgends hat – und ein nicht
     * eingerichteter Zeitplaner fiele niemandem auf, weil die Ansicht ja
     * ohnehin nach last_seen_at filtert. Dasselbe Muster benutzt Laravel für
     * seine Sessions.
     */
    protected function vielleichtAufraeumen(): void
    {
        if (random_int(1, 100) <= 2) {
            $this->aufraeumen();
        }
    }
}
