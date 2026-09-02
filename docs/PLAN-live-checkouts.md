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

Alle erledigt am 02.09.2026.

1. ~~**Server im Modul.**~~ Migration, Modell, Dienst, zwei API-Routen.
2. ~~**Ansicht im Office.**~~ Karte im VA-Dashboard.
3. ~~**Shop.**~~ UUID in der Sitzung, Meldung huckepack, Herzschlag-Komponente,
   Löschen nach der Bestellung.
4. ~~**Abschluss.**~~ Datenschutz-Absatz im Shop, Roadmap, Tests.
5. ~~**Auswertung.**~~ Siehe Abschnitt G.

Auf Wunsch nachgezogen, nachdem die Grundfassung stand: der wievielte Schritt
von wie vielen (`step_no`/`step_count`), der Warenkorb im Fenster (`items`) und
der angeklickte Tisch (`tables`, im Fenster UND in der Liste, dort als „sieht
sich Tisch 3 an").

**Nicht gebaut, bewusst:** ein Löschweg für die Statistikzeilen. Sie tragen
keinen Personenbezug, es gibt also nichts zu löschen – und ein Knopf, der
Auswertungsdaten wegwirft, wäre gefährlicher als nützlich. Die Testdaten auf
demo bleiben deshalb stehen.

## H. Was beim Bauen schiefging

Zwei Fehler, die es wert sind, aufgeschrieben zu werden, weil beide dieselbe
Form hatten: eine Zahl, die falsch war, ohne falsch auszusehen.

*Aus einem Gast wurden fünf.* Die Kennung lag in der Sitzung unter
`nutzlast.ref`, gelesen wurde sie als `ref` – eine Ebene höher. Sie fehlte also
immer, das `?? Str::uuid()` griff bei jedem Klick, und jede Anfrage begann einen
neuen Bestellweg. In der Liste sah das aus wie fünf Gäste. Lehre: Die Kennung
gehört auf die oberste Ebene, nicht in die Nutzlast – sie beschreibt den
Vorgang, nicht die Meldung.

*Ein Abbruch wäre im falschen Monat gelandet.* `ended_at` war der Zeitpunkt des
Verbuchens statt des Abbruchs. Zusammen damit, dass per Los aufgeräumt wird,
hätte ein Abbruch vom März in der Auswertung für den Juni gestanden. Gefunden
hat es der Kunde beim Testen – nicht, weil die Zahl falsch aussah, sondern weil
sie gar nicht da war (auf einer ruhigen Seite löst niemand das Los aus).

## G. Etappe 5 (vorgeschlagen): Wo wird abgebrochen?

Die Live-Sicht beantwortet „was passiert gerade", nicht „was passiert
üblicherweise". Für die zweite Frage braucht es Zahlen, die den Abend
überleben – und die gibt es hier bewusst nicht: `reservation_checkout_sessions`
ist nach 30 Minuten leer.

### Der Trick: das Ende einer Sitzung ist das Ereignis

Statt einer zweiten Meldekette vom Shop wird die vorhandene Zeile beim
**Verschwinden** zu einer Statistikzeile. Das sind genau zwei Stellen, und
beide gibt es schon:

- `beenden()` – der Gast hat bestellt. Ausgang `ordered`.
- `aufraeumen()` – 30 Minuten nichts mehr gehört. Ausgang `abandoned`.

Es kommt also kein einziger Aufruf hinzu, und die Zahlen können nicht von der
Live-Sicht abweichen: Sie sind dieselbe Zeile, einen Moment später.

### Was übrig bleibt

Eine Zeile je beendetem Bestellweg in `reservation_checkout_stats`:
`team_id`, `event_id`, `date`, `last_step`, `step_no`, `step_count`,
`outcome`, `party_size`, `items_count`, `cart_total`, `duration_seconds`,
`ended_at`.

Ohne `checkout_ref`, ohne Warenkorb-Inhalt, ohne Tisch – das ist eine
Statistik, keine Vorgangsakte. Damit ist sie auch nicht mehr auf eine Person
beziehbar und darf bleiben; die 30-Minuten-Frist der Live-Tabelle gilt für sie
nicht.

### Der Nenner

„40 % brechen beim Sitzplatz ab" braucht alle, die den Schritt erreicht haben.
Eine Zeile je Sitzung mit ihrem LETZTEN Schritt genügt dafür: Wer bei Schritt 5
aufhörte, hat 1 bis 4 durchlaufen. Der Trichter ist eine Summe über
`step_no >= n`.

Der Trichter beginnt bei „Personenzahl gewählt", nicht beim Seitenaufruf – vor
dieser Wahl meldet der Shop nichts (siehe Abschnitt C). Wie viele die Seite nur
angeschaut haben, weiß Plausible, nicht das Office.

### Verhältnis zu Plausible

`docs/tracking-pauseplus.md` beschreibt denselben Trichter als Ziele im Browser.
Das ist keine Doppelung, sondern eine andere Frage:

- **Plausible** zählt Browser. Es kennt Herkunft und Gerät, verliert aber alles,
  was einen Blocker hat, und weiß nichts über den Termin.
- **Das Office** zählt Vorgänge. Es kennt Termin, Datum, Gruppengröße und
  Warenkorbwert – und wird nicht geblockt.

Wo beide dieselbe Stufe zählen, werden sie verschiedene Zahlen nennen. Das ist
kein Fehler, und es gehört in die Beschriftung der Auswertung.

### Ansicht

Eine eigene Seite, terminübergreifend, mit Zeitraum-Filter: der Trichter als
Stufen mit Abbruchquote, darunter die Termine mit den meisten Abbrüchen. Nicht
im VA-Dashboard – dort geht es um einen Abend, hier um ein Muster.
