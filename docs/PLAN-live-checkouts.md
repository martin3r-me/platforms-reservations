# Live-Sicht auf laufende Bestellwege

Stand 02.09.2026. Punkt 6 der Roadmap. Voraussetzung (Punkt 5, benannte
Schritte) ist seit 01.09.2026 erfüllt.

## A. Worum es geht

Das Office sieht heute erst etwas, wenn bestellt **wurde**. Was währenddessen
passiert – wie viele gerade im Bestellweg stehen, wo sie hängen, wie viel im
Warenkorb liegt – ist unsichtbar. Für einen Abend mit Andrang ist genau das die
Frage: Lohnt es, noch einen Raum zu öffnen? Bricht der Bestellweg irgendwo ab?

Gebaut wird deshalb eine kurze Sicht auf **laufende** Bestellwege, kein
Analysewerkzeug. Was fertig ist, gehört in den Posteingang; was länger her ist,
in die Auswertung. Hier steht nur, was in den letzten Minuten lebt.

## B. Was gespeichert wird – und was ausdrücklich nicht

Gespeichert wird je laufendem Bestellweg:

| Feld | warum |
|---|---|
| `checkout_ref` | Kennung des Bestellwegs, eine Zufalls-UUID des Shops |
| `event_id` | welcher Termin |
| `event_slot_id` | an welcher Pause der Gast gerade arbeitet (kann fehlen) |
| `step` | Schritt als **Name** (`party`, `when`, `products`, `seat`, `guest`, `pay`) |
| `party_size` | für wie viele |
| `items_count`, `cart_total` | wie voll der Warenkorb ist |
| `last_seen_at` | wann zuletzt etwas passiert ist |

**Nicht** gespeichert: Name, E-Mail, Telefon, IP, User-Agent, gewählter Tisch.
Die ersten vier sind Personenbezug, den diese Sicht nicht braucht – gezählt
werden Vorgänge, nicht Menschen. Der Tisch fehlt aus einem anderen Grund: Er
wäre die interessanteste Zahl und zugleich die gefährlichste. Ein Tisch im
Bestellweg ist **nicht reserviert**; stünde er hier, würde jemand ihn für belegt
halten und danach disponieren.

Die Kennung ist bewusst eine **eigene Zufalls-UUID** und nicht die
Laravel-Session-ID: Letztere ist bei `SESSION_DRIVER=database` ein aktives
Login-Token. Eine Kopie davon in einer zweiten Tabelle wäre ein zweiter Ort, an
dem ein Diebstahl reicht.

## C. Wie die Daten ins Office kommen

Huckepack auf den Livewire-Anfragen, die der Bestellweg ohnehin auslöst. Jede
Auswahl im Shop ist bereits eine Anfrage an den Shop-Server; der meldet daraus
den Stand ans Office weiter.

Zwei Dinge daran sind nicht selbstverständlich:

*Der Gast wartet nicht darauf.* Der Aufruf läuft über
`dispatch(…)->afterResponse()`, also erst, nachdem die Antwort beim Gast ist.
Sonst hinge an jedem Klick im Bestellweg ein zweiter HTTP-Aufruf mit fremdem
Timeout – ein Ausfall des Office würde den Shop lähmen.

*Stillstand ist auch ein Zustand.* Wer drei Minuten die Artikelliste liest,
löst keine Anfrage aus und verschwände aus der Sicht. Deshalb sitzt im
Bestellweg zusätzlich eine winzige eigene Livewire-Komponente mit
`wire:poll.60s`, die nichts tut außer melden. Eigene Komponente, damit das
Nachladen nicht den ganzen Wizard neu rendert (der zieht sonst den Tischplan
mit).

Fertig ist fertig: Nach erfolgreicher Bestellung löscht der Shop den Eintrag
sofort, statt ihn auslaufen zu lassen.

## D. Wann ein Eintrag „lebt"

- **Lebendig**, wenn `last_seen_at` jünger als 3 Minuten ist. Der Herzschlag
  kommt jede Minute; drei Minuten verzeihen einen verlorenen.
- **Weg**, wenn älter als 30 Minuten. Gelöscht wird ohne Zeitplaner, per Los
  beim Schreiben (2 von 100 Anfragen räumen auf) – dasselbe Muster, mit dem
  Laravel seine Sessions aufräumt. Ein Cronjob im Modul wäre eine Abhängigkeit
  zur Wirtsanwendung, die es hier sonst nirgends gibt.

Beide Zahlen stehen als Konstanten am Modell, nicht verstreut in Abfragen.

## E. Wo es im Office steht

Im **VA-Dashboard** des Termins, weil ein Bestellweg immer zu genau einem Termin
gehört und die Auslastungszahlen daneben stehen. Die Karte zeigt sich nur, wenn
etwas läuft – ein dauerhaft leerer Kasten „0 Gäste im Bestellweg" kostet Platz
und sagt nichts.

Keine eigene Seite: Die Sicht ist nur zusammen mit der Auslastung nützlich.

## F. Etappen

1. **Server im Modul.** Migration, Modell, Dienst, zwei API-Routen. Deploybar,
   ohne dass sich etwas ändert – es ruft sie noch niemand auf.
2. **Ansicht im Office.** Karte im VA-Dashboard. Bleibt leer, bis Etappe 3 steht.
3. **Shop.** UUID in der Sitzung, Meldung huckepack, Herzschlag-Komponente,
   Löschen nach der Bestellung.
4. **Abschluss.** Datenschutz-Absatz im Shop, Roadmap, Tests.
