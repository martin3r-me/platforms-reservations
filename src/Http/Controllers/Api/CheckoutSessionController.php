<?php

namespace Platform\Reservation\Http\Controllers\Api;

use Illuminate\Http\Request;
use Platform\Core\Http\Controllers\ApiController;
use Platform\Reservation\Models\Event;
use Platform\Reservation\Services\LiveCheckoutService;

/**
 * Meldungen des Shops über laufende Bestellwege.
 *
 * Zwei Endpunkte, beide schreibend, beide ohne Antwortinhalt von Belang: Der
 * Shop meldet „hier steht jemand" und später „fertig". Lesen kann diese Daten
 * nur das Office – der Shop hat für seine eigene Meldung keine Verwendung, und
 * eine Leseroute wäre eine Auskunft über fremde Gäste an eine öffentliche
 * Anwendung.
 *
 * Team-Scoping wie im EventController: ausdrücklich über den Termin, nicht über
 * das aktive Team des Token-Users.
 */
class CheckoutSessionController extends ApiController
{
    /** POST /events/{event}/checkout-sessions – Stand melden. */
    public function store(Request $request, string $event, LiveCheckoutService $service)
    {
        $model = $this->resolveEvent($event);

        if (! $model) {
            return $this->notFound('Termin nicht gefunden.');
        }

        $data = $request->validate([
            // uuid, damit hier nichts landet, was einer Sitzungs- oder
            // Bestellkennung ähnelt. Der Shop erzeugt sie je Bestellweg neu.
            'ref'           => ['required', 'uuid'],
            'step'          => ['required', 'string', 'max:20', 'regex:/^[a-z_]+$/'],
            // Der wievielte Schritt von wie vielen - beides so, wie der Shop
            // es zaehlt. Das Office rechnet es nicht nach: Welche Schritte es
            // gibt, entscheidet der Bestellweg zur Laufzeit.
            'step_no'       => ['nullable', 'integer', 'min:1', 'max:255'],
            'step_count'    => ['nullable', 'integer', 'min:1', 'max:255'],
            'event_slot_id' => ['nullable', 'integer'],
            'party_size'    => ['nullable', 'integer', 'min:0', 'max:9999'],
            'items_count'   => ['nullable', 'integer', 'min:0', 'max:9999'],
            'cart_total'    => ['nullable', 'numeric', 'min:0'],
            // { pause_id: { artikel_id: menge } }. Was darin nicht stimmt,
            // sortiert der Dienst aus - hier steht nur, dass es eine Liste ist.
            'items'         => ['nullable', 'array'],
        ]);

        $service->merken($model, $data['ref'], $data);

        return $this->success(null, 'Stand gemerkt.');
    }

    /** DELETE /events/{event}/checkout-sessions/{ref} – Bestellweg beendet. */
    public function destroy(string $event, string $ref, LiveCheckoutService $service)
    {
        $model = $this->resolveEvent($event);

        if (! $model) {
            return $this->notFound('Termin nicht gefunden.');
        }

        $service->beenden($model, $ref);

        // Auch dann 204, wenn nichts zu löschen war. Der Shop ruft das nach der
        // Bestellung auf und soll daran nicht scheitern, wenn der Eintrag schon
        // ausgelaufen ist.
        return $this->noContent();
    }

    /** Termin per UUID oder numerischer Id, ohne Team-Scope (wie EventController). */
    protected function resolveEvent(string $key): ?Event
    {
        $query = Event::withoutGlobalScope('team');

        return ctype_digit($key)
            ? $query->find((int) $key)
            : $query->where('uuid', $key)->first();
    }
}
