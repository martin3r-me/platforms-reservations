# Plan: Mehrere Pausen, Tischbindung und mehrere Räume

Stand 01.09.2026. Grundlage ist die Kundenantwort vom selben Tag: Termine mit
mehreren Pausen bleiben die Ausnahme (letzte Saison eine von 54), soll es aber
geben; der Gast wählt die Pause und darf in beiden reservieren.

Dieser Plan behandelt drei Dinge, die nur zusammen Sinn ergeben: die Auswahl der
Pause, den Warenkorb je Pause und die Frage, **wem ein Tisch wie lange gehört** –
letztere entscheidet auch, wie sich mehrere Räume verhalten.

---

## A. Was heute schon steht

Mehr als erwartet. Geprüft, nicht angenommen:

- **Backoffice:** Pausen lassen sich im Terminformular beliebig anlegen, umbenennen
  und entfernen (`EventManager::slots`, Validierung inkl. „Ende nach Beginn").
- **Server:** `GuestOrderService::store()` nimmt `slots` als **Liste** entgegen. Je
  Pause entsteht eine Buchung mit eigenen Positionen, alle unter **einer**
  Bestellung mit **einer** Zahlung. Tisch, Raumfreigabe und Kapazität werden je
  Pause einzeln geprüft.
- **Gast-API:** `floorPlan` liefert Verfügbarkeit und Freigabe bereits **je Pause**
  (`availability[slot_id][table_id]`, `open[slot_id]`, `release_hint[slot_id]`).

Es fehlt allein der Shop. Der schickt eine einzige Pause los – eine hart
geschriebene Zeile in `CheckoutWizard::orderPayload()`:

```php
$slots = [[ 'slot_id' => $this->floorPlan['slot']['id'] ?? null, … ]];
```

Und es fehlt die Tischbindung, die es bisher nicht als Entscheidung gab.

---

## B. Die stille Vorentscheidung im Bestand

`SeatAvailabilityService` zählt ausnahmslos **je Pause**:

```php
Booking::…->where('event_slot_id', $slot->id)…
```

Damit ist ein Tisch in Pause 2 wieder frei, egal wer in Pause 1 daran saß. Das war
nie entschieden – es ist schlicht das, was herauskommt, wenn es nur eine Pause
gibt. Bei zwei Pausen wird daraus eine Aussage über den Betrieb: *Der Saal wird
zwischen den Pausen geräumt und neu verkauft.*

Das kann ein Haus so wollen. Es ist aber nicht das, was ein Konzertpublikum in
einer 20-Minuten-Pause erlebt.

---

## C. Die Entscheidung: Tischbindung als Termin-Einstellung

Zwei Betriebsarten, je Termin einstellbar, mit Team-Standard.

### Modus `event` – „Der Tisch gehört dem Gast den ganzen Abend"

Der Ort ist eine Eigenschaft der **Bestellung**, nicht der Pause. Ein Raum, ein
Tisch, alle Pausen.

- Der Gast wählt den Platz **einmal**.
- Ein Tisch, der in irgendeiner Pause belegt ist, ist in **allen** Pausen belegt.
- Ein Gast, der nur in Pause 1 bestellt, hält den Tisch trotzdem den ganzen Abend.

### Modus `slot` – „Jede Pause wird einzeln vergeben" (heutiges Verhalten)

Der Ort ist eine Eigenschaft der **Buchung**.

- Der Gast wählt je Pause einen Platz – möglicherweise einen anderen, und in einem
  anderen Raum.
- Ein Tisch kann zweimal verkauft werden, einmal je Pause.

### Vorgabe: `event`

Die strengere Auslegung – sie verkauft nie einen Platz zu viel. Und sie ist die
realistische: In einer kurzen Pause setzt niemand einen Saal um. Wer es anders
hält, stellt es um.

**Sichtbar nur ab zwei Pausen.** Bei einer Pause sind beide Modi rechnerisch
identisch; das Feld wäre eine Frage ohne Wirkung. Dieselbe Disziplin wie beim
Freigabe-Schwellwert, der nur erscheint, wenn ein Raum folgt.

---

## D. Die Falle beim Zählen (der eigentliche Kern)

Modus `event` ist **nicht** „über alle Pausen summieren".

Wer in beiden Pausen bestellt, hat zwei Buchungen an demselben Tisch. Summiert
würden aus 2 Personen 4 belegte Plätze – der Gast wäre nach seiner eigenen
Bestellung selbst schuld am vollen Tisch.

Gezählt werden muss nach **Parteien**, jede einmal:

- Partei = `order_id`, wenn vorhanden.
- `bookings.order_id` ist **nullable** (Altbestand vor der Order-Klammer). Eine
  Buchung ohne Bestellung ist ihre eigene Partei.
- Je Partei zählt die **größte** `guest_count` über alle Pausen. Eine Partei, die
  zu viert in Pause 1 und zu zweit in Pause 2 sitzt, braucht vier Plätze – der
  Tisch muss die größte Sitzung tragen. (Über den Shop kann das nicht entstehen,
  über die manuelle Buchung schon.)

**Eine einzige Stelle:** `SeatAvailabilityService`. `RoomReleaseService` rechnet
über `bookedSeatsInRoom()`, das VA-Dashboard und die Gast-API über dieselben
Methoden. Wird die Regel dort eingesetzt, erbt die Raumfreigabe sie automatisch.
Zwei Fassungen wären die Art Fehler, die man erst bemerkt, wenn die Zahlen
auseinanderlaufen.

### Was die Prüfung beim Speichern nicht kaputtmachen darf

`store()` validiert **alle** Pausen, bevor es die erste Buchung schreibt. Genau
deshalb funktioniert Modus `event`: Die Prüfung für Pause 2 sieht die eigene, noch
nicht geschriebene Buchung aus Pause 1 nicht und kollidiert nicht mit sich selbst.

Zöge jemand die Prüfung später in die Schreibschleife, würde der Gast in Modus
`event` seine eigene zweite Pause abgelehnt bekommen – mit `TABLE_FULL`, obwohl
der Tisch ihm gehört. Das gehört als Kommentar an die Schleife.

---

## E. Mehrere Räume – die Logik dahinter

Die Raumfreigabe („Raum 2 öffnet, sobald Raum 1 zu X % gefüllt ist") trifft hier
auf die Pausen. Der Modus entscheidet, wie.

### Modus `event`: Räume öffnen für den Abend

Die Belegung eines Raums ist in jeder Pause dieselbe Zahl – ein gehaltener Tisch
ist in allen Pausen gehalten. Also liefert `openRooms($event, $slot)` für **jede**
Pause dieselbe Menge.

Das ist die angenehme Eigenschaft dieses Modus: Der Gast wählt einen Raum, nicht
einen Raum je Pause, und die Raumliste ändert sich zwischen den Pausen nicht.
Sequenziell bleibt sequenziell: Terrasse füllt sich → ROSSINI öffnet → neue Gäste
landen in ROSSINI. Wer schon sitzt, bleibt sitzen.

*Technisch:* Die API-Antwort behält ihre Form (`open[slot_id]` je Pause), die
Werte sind nur identisch. Der Shop braucht dafür keine Fallunterscheidung, und die
Antwort bleibt über beide Modi gleich geformt. Die Berechnung je Plan wird
zwischengespeichert, statt sie je Pause zu wiederholen.

### Modus `slot`: Räume öffnen je Pause

Hier wird es das, was der Gast wirklich sehen muss:

- Die Terrasse kann in Pause 1 voll sein (ROSSINI offen) und in Pause 2 halbleer
  (ROSSINI zu). **Dieselbe Veranstaltung, zwei verschiedene Raumlisten.**
- Ein Gast, der beide Pausen bucht, kann deshalb gezwungen sein, den Raum zu
  wechseln: Pause 1 Terrasse, Pause 2 ROSSINI.
- Umgekehrt kann ein Raum, der in Pause 2 offen ist, in Pause 1 zu sein. Es gibt
  dann schlicht **keinen** Raum, der in beiden Pausen wählbar ist.

Daraus folgen zwei Pflichten für den Shop:

1. Er darf nie so tun, als gäbe es eine Raumwahl „für den Abend". Je Pause eigene
   Liste, eigener Grund für gesperrte Räume.
2. Die Übersicht muss den Ort **je Pause** nennen – steht dort nur ein Tisch,
   steht der Gast in der zweiten Pause im falschen Saal. Aber **ohne Aufzählung
   und ohne Fallunterscheidung**, siehe die Regel gleich darunter.

### Die Regel für alle Ausgaben: keine Unterscheidung, die nichts unterscheidet

Eine Schreibweise wie „1. Pause: Terrasse, Tisch 5 · 2. Pause: ROSSINI, Tisch 12"
wäre der Fehler. Sie führt eine Nummerierung ein, und wer bei einem einpausigen
Termin „1. Pause" liest, sucht nach der zweiten. Die Pause **benannt** werden darf
(sie sagt dem Gast, wann er kommen soll) – **aufgezählt** wird sie nicht.

Nachgesehen, statt es anzunehmen: Die vorhandenen Ausgaben machen es bereits
richtig, weil sie je **Buchung** gliedern und eine Buchung genau eine Pause ist.

| Ausgabe | heute | bei zwei Pausen |
|---|---|---|
| Bestätigungsmail | Abschnittskopf „Pause · 20:15–20:35 Uhr · Tisch 5", dann die Positionen | zwei Abschnitte, jeder mit eigenem Ort |
| Beleg-PDF | dieselbe Zeile über der Positionsgruppe | zwei Gruppen |
| Bon | einer je Buchung, mit Pause und Ort im Kopf | zwei Bons |
| Statusseite im Shop | Zeile je Buchung | zwei Zeilen |

Sie fallen bei einer Pause auf genau den heutigen Wortlaut zusammen, ohne dass
irgendwo ein `if` steht. Das ist die bessere Form: Nicht „bei mehreren Pausen
anders schreiben", sondern eine Form, die bei einer Pause von selbst still ist.

Zu ändern bleibt damit **eine** Stelle: die Übersicht im Wizard
(`checkout-wizard.blade.php` zeigt `selectedTableLabel()` als eine einzelne Zeile).
Sie wird ein Abschnitt je Pause – und ist bei einer Pause eine Zeile wie heute.

### Was das VA-Dashboard dazulernen muss

In Modus `event` ist **belegt ≠ bestellt**. Ein Gast, der nur in Pause 1
bestellt, blockiert Plätze in Pause 2, ohne dort etwas zu kaufen. Die Auslastung
für Pause 2 zeigt ihn – zu Recht –, aber die Küche darf daraus keine Menge
ableiten.

Also zwei Zahlen statt einer, sobald ein Termin mehrere Pausen hat: **belegte
Plätze** (Kapazität, steuert die Freigabe) und **bestellte Positionen** (Küche).
Laufzettel, Bon und Küchenliste hängen bereits an den Buchungen mit Positionen und
sind damit von Haus aus richtig – es ist allein die Auslastungsanzeige, die sonst
in die Irre führt.

Und eine Eigenschaft, die dabei erhalten bleibt: Für die blockierte Pause 2
entsteht **keine** Buchung. Die Sperre kommt aus der Berechnung, nicht aus einem
Phantom-Datensatz. Nichts im Bestand muss aufgeräumt werden, wenn der Modus
wechselt.

---

## F. Umstellen, wenn schon gebucht wurde

Die beiden Richtungen sind nicht gleich gefährlich:

- **`event` → `slot`** ist harmlos. Es gibt nur Kapazität frei.
- **`slot` → `event`** kann Tische überbelegt zurücklassen: Partei A saß in
  Pause 1, Partei B in Pause 2 am selben Tisch – zusammen passen sie nicht.

Deshalb: **Rückfrage vor dem Umstellen**, mit der Zahl der betroffenen Tische
(„3 Tische wären danach überbelegt"). Nichts wird still gelöscht, nichts still
verschoben – die Buchungen bleiben, das Haus entscheidet. Gleiche Bauart wie die
Rückfrage vor dem Entfernen eines Raums mit Buchungen.

**Nicht einfrieren.** Anders als Preise oder der Zielort gehört der Modus nicht an
die einzelne Buchung: Die Kapazität eines Tisches muss für alle Buchungen nach
derselben Regel gerechnet werden. Zwei Buchungen mit verschiedenen eingefrorenen
Modi am selben Tisch ergäben keine Zahl, sondern einen Streit.

---

## G. Der Shop

### Der Schritt-Index verschwindet

Heute steht die Reihenfolge hart im Code (`match ($this->step)`, `min(4, …)`), und
die Plausible-Ziele hängen an genau diesen Zahlen. Mit Pausen und zwei
Tischbindungs-Modi gäbe es davon drei Varianten. Also baut der Wizard seine
Schritte zur Laufzeit als **Liste mit Namen**, in der Schritte fehlen dürfen.

**Invariante, die das Ganze trägt:** *Die Zahl der Schritte hängt nie von der Zahl
der Pausen ab.* Zwei Pausen bedeuten zwei Abschnitte **innerhalb** eines Schritts,
nicht zwei Schritte. Sonst wäre der Trichter zwischen Terminen nicht mehr
vergleichbar, und die Zahlen der letzten Saison wären wertlos.

Die Schrittfolgen:

| | 1 Pause (Normalfall) | ≥2 Pausen, `event` | ≥2 Pausen, `slot` |
|---|---|---|---|
| 1 | Personen | Personen | Personen |
| 2 | – | **Wann?** | **Wann?** |
| 3 | Warenkorb | Warenkorb je Pause | Warenkorb je Pause |
| 4 | Tisch | Tisch (einmal) | Tisch **je Pause** |
| 5 | Gastdaten | Gastdaten | Gastdaten |
| 6 | Übersicht | Übersicht | Übersicht |

53 von 54 Terminen sehen weiter genau die vier Schritte von heute. Niemand klickt
durch eine Auswahl ohne Auswahl.

### Schritt „Wann?"

- Nur bei zwei oder mehr Pausen.
- Mehrfachauswahl – der Kunde will ausdrücklich, dass man in beiden reservieren
  kann.
- Zeigt je Pause, ob überhaupt noch Platz für die gewählte Personenzahl ist
  („ausgebucht" statt einer Sackgasse drei Schritte später). Dafür wird der
  Grundriss **vor** diesem Schritt geladen, nicht erst beim Tisch-Schritt.

### Warenkorb je Pause

Der Warenkorb wird von `[item_id => menge]` zu `[slot_id => [item_id => menge]]`.
Ein Abschnitt je gewählter Pause, sichtbare Zwischensumme je Pause, eine
Gesamtsumme. Begründung des Kunden, wörtlich: *„vielleicht will man in der ersten
Pause was essen und in der zweiten dann nur was trinken."*

Serverseitig ist das bereits die erwartete Form – `items` hängen am Slot.

### Tisch-Schritt

- Modus `event`: ein Grundriss, eine Wahl, gilt für alle Pausen.
- Modus `slot`: ein Abschnitt je Pause, jeder mit eigener Raumliste und eigenen
  Sperrgründen. Weiter erst, wenn jede gewählte Pause einen Tisch hat.

### Was die API dafür braucht

Genau ein neues Feld: die Tischbindung im `event`-Teil der Antwort. Alles Übrige
(Pausenliste, Verfügbarkeit je Pause, Freigabe je Pause) liefert sie schon.

---

## H. Backoffice

- **Terminformular:** Auswahl der Tischbindung, nur ab zwei Pausen, mit erklärendem
  Satz darunter – wie beim Freigabe-Schwellwert. Rückfrage beim Umstellen (F).
- **Checkout-Einstellungen:** Team-Standard, Muster wie `max_guest_count`
  (`Event::maxGuestCount(?CheckoutSetting)` als Vorlage: eigener Wert schlägt
  Standard, `null` heißt „Standard").
- **VA-Dashboard:** zwei Zahlen bei mehreren Pausen (E).
- **Manuelle Buchung** (`BookingCreate`): bleibt zunächst einpausig – `slotId` ist
  ein einzelnes Feld, das Personal legt bei Bedarf zwei Buchungen an. Die
  Erweiterung auf mehrere Pausen ist danach klein, weil `store()` es kann; sie
  gehört aber nicht in denselben Umbau.

---

## I. Datenmodell

Zwei Spalten, kein neues Modell:

- `reservation_events.table_binding` – `string(10)`, **nullable**. `null` = dem
  Team-Standard folgen. Werte `event` | `slot`.
- `reservation_checkout_settings.table_binding` – `string(10)`, Vorgabe `event`.

Keine Spalte an `reservation_bookings` (siehe F, „nicht einfrieren").

**Warum die Vorgabe live nichts tut:** Alle 54 Termine bei Culinaria haben genau
eine Pause. Bei einer Pause liefern beide Modi dieselben Zahlen. Die Migration ist
im Betrieb wirkungslos – sie legt nur die Regel fest, nach der der erste Termin
mit zwei Pausen gerechnet wird.

---

## J. Testbasis

Die Parteien-Zählung (D) ist die Stelle, an der ein Denkfehler unsichtbar bliebe:
Er zeigt sich nicht als Fehlermeldung, sondern als ein Tisch, der zu früh voll
ist. Das gehört geprüft, und zwar eingecheckt:

- Eine Partei, beide Pausen, derselbe Tisch → zählt **einmal**.
- Zwei Parteien, je eine Pause, derselbe Tisch → zählen **beide** (Modus `event`).
- Dieselbe Lage in Modus `slot` → je Pause getrennt, Tisch in beiden frei genug.
- Verschiedene `guest_count` je Pause → die **größere** gewinnt.
- Buchung ohne `order_id` → eigene Partei.
- `openRooms()` liefert in Modus `event` für alle Pausen dieselbe Menge.

Das wäre die erste eingecheckte Testbasis des Moduls (vgl.
`PLAN-abholstationen.md`, Etappe 1).

---

## K. Etappen

**1 – Rechnen.** *(erledigt 01.09.2026)* Spalten, Modelle (`Event::tableBinding()`), Parteien-Zählung im
`SeatAvailabilityService`, Testbasis. Ohne Oberfläche. Bei einer Pause ändert sich
nichts – deploybar, ohne dass irgendwer etwas merkt.

**2 – Einstellen.** *(erledigt 01.09.2026)* Terminformular, Team-Standard, Umstell-Rückfrage, zweite Zahl
im VA-Dashboard. Ab hier kann das Haus mehrpausige Termine korrekt anlegen; der
Shop kann sie noch nicht verkaufen.

**3 – Schritt-Liste.** *(erledigt 01.09.2026, im Shop – noch nicht ausgerollt)* Der Wizard bekommt benannte Schritte statt Nummern, die
Plausible-Ziele hängen an Namen. **Ohne sichtbare Änderung** – derselbe Ablauf,
andere Mechanik. Eigener Schritt, damit ein Fehler hier nicht mit einem Fehler in
der Pausenlogik zusammenfällt.

Zu Etappe 3 nachgetragen: Die Schritte heißen `party`, `products`, `seat`,
`guest`, `pay` – dieselben Werte wie die Übersetzungsschlüssel
(`checkout.steps.*`), damit es keine zweite Liste gibt. Beim Benennen fielen ein
toter Bestätigungs-Block („Step 5") und die unbenutzte Eigenschaft
`bookingReference` auf; beide sind weg. **Beim Ausrollen zu beachten:** `$step`
wechselt von int auf string, laufende Bestellwege verlieren dabei ihre Sitzung –
also außerhalb der Bestellzeiten.

**4 – Wann und Warenkorb.** Pausen-Schritt, Warenkorb je Pause, Übersicht und
Bestätigung je Pause. Danach ist Modus `event` vollständig verkaufbar.

**5 – Tisch je Pause.** Modus `slot` im Shop, Raumliste je Pause, Ortsangabe je
Pause in Übersicht, Mail und Bon.

**6 – Live-Sicht auf laufende Checkouts.** Erst jetzt: Ein gespeicherter Schritt
hat ab Etappe 3 einen Namen, den ein späterer Umbau (Abholstationen, Schritt
„Wo?") nicht rückwirkend verschiebt.

Der Schritt „Wo?" der Abholstationen hängt sich nach Etappe 3 ohne Umnummerierung
ein. Das war der Grund, warum beide Vorhaben bisher aneinander gekettet waren –
die Kette ist damit aufgelöst.
