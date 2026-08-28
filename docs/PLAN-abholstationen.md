# Architektur-Plan: Abholstationen

**Entscheidung (26.08.2026):** Gäste bestellen künftig wahlweise **an einen Tisch**
(wie bisher) oder **an eine Abholstation** – z. B. „Foyer links", „Rang 1 Bar" – und
holen dort in der Pause selbst ab. Küche, Laufzettel und Bon gruppieren dann nach
Station statt nach Tisch.

> Ersetzt den früheren Anlauf „Drop-off Slots" (Tabelle `reservation_dropoff_slots`,
> `DropoffSlot`, `DropoffManager`). Deren Reste werden erst entfernt, wenn der Ersatz
> steht – siehe Etappe 6.

---

## Der Kern

Eine Buchung zeigt heute auf einen **Tisch**. Künftig zeigt sie auf einen **Ort**:
entweder auf einen Tisch oder auf eine Abholstation. Genau eines von beidem.

Alles Weitere folgt daraus. Damit die Fallunterscheidung nicht an dreißig Stellen
einzeln auftaucht – Bon, Beleg-PDF, zwei Mails, Buchungsliste, Dashboard, Export,
Laufzettel, Küche, API –, bekommt `Booking` **eine** Auskunftsstelle. Dieselbe Regel,
nach der `RoomOutline` und `CartCalculator` gebaut sind: eine Rechnung, einmal.

---

## A. Datenmodell

### `reservation_pickup_stations` (neu)

Die Station gehört zum **Venue**, so wie ein Raum.

| Spalte | | |
|---|---|---|
| `team_id` | FK teams | `BelongsToTeam` |
| `venue_id` | FK reservation_venues | cascade |
| `name` | string | übersetzbar |
| `description` | text, nullable | übersetzbar, für den Shop („neben der Garderobe") |
| `capacity_per_slot` | smallint, nullable | Gäste **je Pause**; null = unbegrenzt |
| `sort_order` | smallint | Reihenfolge im Shop |
| `is_active` | bool | |
| `sales_list_id` | FK, nullable | leer = Sortiment des Termins – **erst Etappe 7** |
| `floor_plan_id` | FK, nullable | `nullOnDelete` – Lage im Plan, optional |
| `x_pct` `y_pct` `w_pct` `h_pct` | float, nullable | normalisiert, wie beim Tisch |
| `shape` `rotation` | | Form und Ausrichtung der Fläche |

Name und Beschreibung laufen über das vorhandene `Translation`-Modell (`HasTranslations`),
weil der Shop mehrsprachig ist und Artikelnamen es genauso halten.

### Lage im Saalplan – optional

Eine Station **kann** im Tischplan liegen („Rang 1 Bar"), sie **muss** es nicht
(„Foyer links" liegt gar nicht im Saal). Deshalb sind alle Lage-Spalten nullable:
Ohne Position ist die Station eine Karte in der Liste, mit Position zusätzlich eine
anklickbare Fläche im Plan.

Die Koordinaten sind normalisiert (0…1) wie bei den Tischen – dieselbe Konvention,
damit Editor und Shop dieselbe Rechnung benutzen. `Table::surfaceStyle()` wandert dafür
in einen gemeinsamen Zug (`HasPlanPosition`), den Tisch und Station teilen. Zwei
Fassungen derselben Positionsrechnung wären die Sorte Fehler, die erst auffällt, wenn
im Shop etwas zwei Pixel danebenliegt.

Eine Station liegt in **höchstens einem** Plan – sie ist ein physischer Ort. Wird ein
Plan gelöscht, verliert die Station ihre Position, nicht ihre Existenz (`nullOnDelete`);
sie gehört dem Venue, nicht dem Raum. Beim **Duplizieren** eines Tischplans wandern
Stationen deshalb nicht mit: Die Kopie ist ein anderer Raum, die Bar steht aber weiter
im Original.

### Warum kein Tisch mit Schalter „ist Abholstation"

Naheliegend wäre, `reservation_tables` um ein Kennzeichen zu erweitern – dann läge alles
schon im Plan. Drei Gründe sprechen dagegen, und der dritte wiegt schwer:

1. `reservation_tables.floor_plan_id` ist **nicht** nullable und `cascadeOnDelete`.
   „Foyer links" bräuchte also einen erfundenen Tischplan, und das Löschen eines Raums
   würde eine Abholstelle mitreißen, die gar nicht in diesem Raum steht.
2. `capacity` heißt am Tisch „Plätze". An einer Station heißt dieselbe Zahl „Gäste je
   Pause". Gleiche Spalte, andere Bedeutung – das liest irgendwann jemand falsch.
3. **Die Platzrechnung.** `SeatAvailabilityService` und die Verfügbarkeit im Shop laufen
   über `floorPlan->tables()`. Eine Station in dieser Menge zählte überall als Tisch mit
   Plätzen, solange nicht jede einzelne Schleife nachgebessert ist – und eine übersehene
   liefert keine Fehlermeldung, sondern eine falsche Zahl.

Die Station ist deshalb eine eigene Entität, die im Plan **platziert werden kann**. Im
Editor fühlt es sich an wie ein weiteres Element; in den Daten bleibt der Tisch ein Tisch.

### `reservation_event_stations` (neu)

Zuordnung zum Termin – der Zwilling von `reservation_event_rooms`.

`event_id`, `pickup_station_id`, `sort_order`, `capacity_override` (nullable),
`unique(event_id, pickup_station_id)`.

### `reservation_event_station_slots` (neu)

Je Pause an oder aus. Zwei Pausen, Station nur in der ersten – genau dieser Fall.

`event_station_id`, `event_slot_id`, `unique` über beide.

Wird eine Pause am Termin gelöscht, räumt die Kaskade die Zuordnung mit ab – eine Station
kann so bei **null** Pausen landen und verschwindet lautlos aus dem Shop. Deshalb: Beim
Speichern des Termins ist eine Station ohne Pause ein Validierungsfehler, kein stiller
Zustand.

**Explizite Zeilen, kein „leer heißt alle".** Beim Speichern werden die gewählten
Pausen immer geschrieben, mit der Regel: mindestens eine. Sonst hätte „keine Zeile"
zwei Bedeutungen – alle Pausen oder gar keine –, und diese Sorte Zweideutigkeit fällt
genau dann auf, wenn abends eine Station stumm verschwindet.

### `reservation_bookings` (Erweiterung)

Drei Spalten:

| Spalte | | |
|---|---|---|
| `pickup_station_id` | FK, nullable | `nullOnDelete` |
| `place_kind` | string, nullable | `table` \| `station` – was es **war** |
| `place_label` | string, nullable | wie der Ort **hieß** |

Dazu Index `(event_slot_id, pickup_station_id, status)` für die Kapazitätsabfrage –
dasselbe Muster wie der Platz-Index von `2026_07_18_000004`.

`table_id` ist bereits nullable; dort ändert sich nichts.

`place_kind`/`place_label` sind der eingefrorene Ort – die Begründung steht in Abschnitt S.
Englische Spaltennamen, weil das Schema durchgängig englisch ist (`guest_name`,
`payment_method`), auch wenn die Methoden deutsch heißen.

### Kein Modus-Flag am Termin

Ob ein Termin Tischservice, Abholung oder beides anbietet, ergibt sich aus dem, was
zugeordnet ist: Räume, Stationen oder beides. Das trifft den realen Fall – Saal mit
Tischen, Rang mit Abholung – ohne eine dritte Einstellung, die man falsch setzen kann.

---

## B. Der Zielort: eine Auskunftsstelle

Auf `Booking`:

```php
$booking->zielort()          // ['art' => 'station'|'table'|null, 'id', 'label', 'raum']
$booking->zielortLabel()     // "Tisch 12"  bzw.  "Foyer links"
$booking->zielortSchluessel()// Gruppierungsschlüssel für Laufzettel und Küche
```

Jede Anzeige fragt dort und nirgends sonst.

**Der Guard ist bewusst zweistufig:**

- **Beim Anlegen:** genau eines von `table_id` / `pickup_station_id`. Keines wäre eine
  Buchung ins Nichts, beides eine Buchung an zwei Orte.
- **Beim Aktualisieren:** nur „nicht beides". Denn `nullOnDelete` entzieht einer
  bestehenden Buchung den Tisch, wenn dieser gelöscht wird – solche Datensätze gibt es
  heute schon, und sie müssen speicherbar bleiben.

**Werfen ausschließlich bei `creating`.** Ein `saving`-Hook läuft bei jedem Speichern
einer Buchung im Livebetrieb – auch beim Statuswechsel, an dem der automatische Bondruck
hängt. Wirft er dort zu Unrecht, steht die Bestellstrecke. Das Risiko ist klein, weil es
genau **einen** Erzeuger von Buchungen gibt (`GuestOrderService:177`), aber die
Einschränkung kostet nichts.

Die eigentliche Prüfung bleibt ohnehin in `store()`: Die Station muss zum Termin **und**
zur gewählten Pause gehören – dieselbe Strenge, mit der heute der Tisch gegen
`allowedFloorPlanIds` geprüft wird. Ohne diese Prüfung wäre die Stations-ID aus dem
Request ein IDOR.

**`zielortLabel()` liest in dieser Reihenfolge:**

1. Die lebende Beziehung – Tisch oder Station. Ein umbenannter Tisch heißt damit überall
   gleich, auch auf alten Listen.
2. `place_kind` + `place_label` – der eingefrorene Stand, wenn der Ort gelöscht wurde.
3. Ein neutraler Strich, wenn beides fehlt (Buchungen von vor dieser Änderung, deren Tisch
   schon weg ist).

Die Wortwahl („Tisch 12", „Foyer links") entsteht dabei **in dieser Methode**, nicht in der
Datenbank. Deshalb zwei Spalten statt einer fertigen Zeichenkette: Ändert sich später die
Schreibweise, ändern sich alte Buchungen mit, statt in ihrer alten Fassung zu erstarren.

---

## C. Kapazität

Eigener `PickupCapacityService`, analog zu `SeatAvailabilityService`, aber deutlich
einfacher: Gäste je Station **und Pause** gegen eine optionale Obergrenze
(`capacity_override` schlägt `capacity_per_slot`). Ohne gesetzte Grenze wird nichts
geprüft.

150 in Pause 1 und 150 in Pause 2 – nicht 150 zusammen.

**Der Wettlauf bleibt, wie er beim Tisch ist – Entscheidung vom 28.08.2026.** Kein
`lockForUpdate`. Zwei gleichzeitige Bestellungen können dieselbe letzte Kapazität nehmen;
in seltenen Fällen steht am Ende eine Portion mehr auf der Liste als die Obergrenze sagt.

Der Grund, warum das hier trägt: Der Bestellschluss liegt Stunden vor der Pause. Produziert
wird **danach**, und zwar das, was bestellt ist. Die Obergrenze ist damit eine Bremse gegen
Überlast, keine Zusage über vorhandene Ware. Bei einer Just-in-time-Ausgabe wäre die
Antwort eine andere.

Weiche Kapazität, Großgruppen-Regel, gesperrte Tische: nichts davon gilt hier.
`SeatAvailabilityService` bleibt unberührt. **Eine Station darf in der Platzrechnung
nie auftauchen.**

Die Personenzahl wird bei reinen Abhol-Terminen **weiter abgefragt**. Sie begrenzt dort
nichts mehr, aber Küche und Laufzettel lesen sie, und auf dem Bewirtungsbeleg ist sie
steuerliche Pflichtangabe.

---

## D. Backoffice: Pflege

**Stationen** – die vorhandene Seite unter `/dropoff` wird die echte Verwaltung, nach
Venue gruppiert. Route `reservation.stations.index` auf `/stations`, alter Pfad als
Weiterleitung (Lesezeichen, Sidebar).

Löschschutz analog zu `FloorPlan`/`Venue`: Eine Station, die in einem **anstehenden**
Termin hängt, ist nicht löschbar – mit derselben Fehlermeldung samt Terminliste
(`FloorPlanInUseException` als Vorbild).

**Tischplan-Editor** (`FloorPlanEditor`) – ein drittes Werkzeug neben „Tisch" und den
Beschriftungen: **Abholstation**. Platzieren, verschieben, in der Größe ziehen und drehen
wie ein Tisch, aber mit dem Formular der Station: Name, Beschreibung, Obergrenze je Pause
– **kein** Platz-Feld. Auswählbar sind die Stationen des Venues; wer eine neue anlegt, legt
sie damit auch für das Venue an.

Optisch klar als Station erkennbar (eigene Farbe und Form), damit im Plan niemand einen
Tisch sieht, an dem man sitzen kann. Die dekorativen Beschriftungen „Theke"/„Bar" aus
`RoomLayout` bleiben, was sie sind: Zeichnung ohne Funktion. Wo eine echte Station steht,
ersetzt sie den Marker – zwei Theken übereinander wären nur verwirrend.

Nicht vorgesehen: einen bestehenden Tisch per Schalter in eine Station verwandeln. An
einem Tisch hängen Buchungen über `table_id`; die Umwandlung müsste sie mitnehmen oder
verwaisen lassen. Wer das will, löscht den Tisch und setzt eine Station.

**Termin** (`EventManager`) – ein zweiter Block neben den Räumen: Station wählen,
Reihenfolge, optionale Obergrenze, Häkchen je Pause (beim Anlegen alle gesetzt).

Die Veröffentlichungs-Regel (`EventManager.php:438`) fordert heute mindestens einen
Raum – künftig mindestens einen Raum **oder** eine Station.

Das Duplizieren eines Termins nimmt Stationen samt Pausen-Zuordnung mit.

**Manuelle Buchung** (`BookingCreate`) – der Tisch-Schritt wird zum Ort-Schritt.
Bietet der Termin nur Stationen, entfällt der Saalplan.

---

## E. Betrieb

**Laufzettel** (`FunctionSheetService`) – gruppiert nach `zielortSchluessel()` statt
nach `table_id`. Innerhalb einer Station **alphabetisch nach Gastnamen** bzw. nach
Abholcode (siehe F): Am Tresen sucht man nach Namen, nicht nach Nummern. Genau das ist
die Abholliste, ein zusätzlicher Ausdruck ist nicht nötig.

**Küche** (`EventOrders`, `KitchenPrepService`) – je Pause eine Aufteilung nach Station,
sobald welche im Spiel sind. Ohne Stationen bleibt die Ansicht Zeile für Zeile die alte.

**Bon, Beleg-PDF, beide Bestätigungsmails, Buchungsliste, Dashboard, VA-Dashboard,
Statuswechsel-Dialog (`partials/booking-status-modal.blade.php`), `ListBookingsTool`** –
alle über `zielortLabel()`.

**Nur eine Bon-Vorlage.** Der Sammel-Bon (`printing/reservation-event-bons.blade.php`)
bindet für jede Buchung dieselbe Einzelvorlage ein; geändert wird also einmal. Aber
`Event::bonBookings()` lädt heute `table.floorPlan` vor – die Station muss mit, sonst
holt der Ausdruck sie zeilenweise nach.

Und die **Sortierung** dort gehört überdacht: Sie läuft nach Pause, darin nach Gastname,
damit der Papierstapel zur Ansicht auf dem Schirm passt. Sind Stationen im Spiel, gehört
die Station dazwischen – nach Pause, dann Station, dann Name. Sonst sortiert jemand einen
Stapel von Hand, den die Maschine schon richtig hätte legen können.

**Export mit einer Ausnahme: keine Spalte umbenennen.** Der Kunde arbeitet mit dem CSV
(die Juni-Datei lag uns vor). „Tisch" bleibt „Tisch" und ist bei Stationsbuchungen leer;
„Abholstation" kommt als **neue** Spalte dazu. Wer die Datei automatisiert einliest, merkt
davon nichts.

Ein Feld dort ist heute schon abgeleitet und falsch abgeleitet: `venue` kommt über
`table.floorPlan.venue`. Bei einer Stationsbuchung wäre es leer – und bei einer
Tischbuchung, deren Tisch inzwischen gelöscht wurde, ist es das heute schon.

Die richtige Quelle ist der **Termin**: `Event.venue_id` steht direkt an der Buchung und
überlebt jedes Löschen im Saalplan. Ein Umweg über zwei Beziehungen weniger, und er ist
obendrein haltbar.

---

## F. Abholcode

**`pickup_identification`: `name` | `code`** – die Einstellung steht an **zwei** Stellen:

- `reservation_checkout_settings.default_pickup_identification` – die Vorgabe des Teams.
  Die Einstellungen sind seit `84c288c` nach Kategorien geteilt (zweite Sidebar,
  `partials/settings-nav.blade.php`, je Thema ein Partial). Der Schalter gehört unter
  **„Veranstaltung & Plätze"** in `partials/settings/termine.blade.php`, wo schon die
  weiche Tisch-Kapazität steht: Es geht um die Form der Bewirtung, nicht um den Beleg.
- `reservation_events.pickup_identification` – der Wert, der wirklich gilt. Beim Anlegen
  eines Termins aus der Vorgabe gefüllt, danach am Termin änderbar.

**Fest, sobald die erste Bestellung für den Termin liegt.** Nicht wegen der Daten – Codes
werden ohnehin immer vergeben (siehe unten) –, sondern wegen der schon verschickten
Bestätigungen: Wer früh bestellt hat, hält eine Mail ohne Nummer in der Hand und stünde
abends vor einem Tresen, der nach Nummern arbeitet. Vor der ersten Bestellung ist das
Umstellen folgenlos und deshalb frei.

Beim Duplizieren eines Termins wird der Modus mitkopiert und ist wieder offen – die Kopie
hat noch keine Bestellungen.

Bei `name` bleibt alles wie oben: Der Gast nennt seinen Namen, die Liste ist alphabetisch.

Bei `code` bekommt **die Bestellung** (`Order`, nicht die einzelne Pausen-Buchung) eine
vierstellige Nummer, eindeutig je Termin. Sonst müsste der Gast sich zwei Nummern merken.
Der Code steht auf der Bestätigungsseite, in der Mail, auf dem Beleg und auf dem Bon;
die Abholliste ist dann nach Code sortiert.

Vierstellig und je Termin eindeutig, weil zwei „Müller" an einem Abend wahrscheinlicher
sind als eine Kollision unter zehntausend Nummern – und weil eine über alle Termine
fortlaufende Nummer irgendwann fünfstellig wird und am Tresen niemand mehr vorlesen mag.

Spalte: `reservation_orders.pickup_code`, nullable, `unique(event_id, pickup_code)`.

**Der Code wird immer vergeben, angezeigt wird er nur bei `code`.** Sonst entsteht ein
halber Zustand: Wer die Einstellung mitten in einem Verkauf umstellt, hätte fünfzig
Bestellungen ohne Nummer und fünfzig mit – und die Abholliste wüsste nicht, wonach sie
sortieren soll. Eine ungenutzte Zahl in einer Spalte kostet nichts, ein halber Zustand
kostet einen Abend.

Vergabe mit Wiederholung bei Kollision: Vier Stellen je Termin sind reichlich, aber zwei
gleichzeitige Bestellungen können dieselbe Zahl ziehen. Der eindeutige Index fängt das
ab, die Vergabe versucht es erneut – nicht die Zahl vorher „freihalten".

---

## G. Gast-API (`EventController`)

**`GET /events/{event}/floor-plan`** liefert zusätzlich `stations`: Name und
Beschreibung (übersetzt), Reihenfolge und je Pause die Restkapazität bzw. „unbegrenzt".
Eine Station erscheint nur bei den Pausen, für die sie freigegeben ist.

**`withoutGlobalScope('team')` ist hier Pflicht, nicht Geschmack.** In der API ist ein
Service-User angemeldet; `Auth::check()` ist also true, und `currentTeam` kann ein anderes
Team sein als das des Termins. Genau deshalb steht der Aufruf in `EventController` heute
an jeder Stelle. Fehlt er bei den Stationen, liefert die API keinen Fehler, sondern **null
Stationen** – und niemand merkt es, bis abends jemand vor einem leeren Shop steht.
Dasselbe gilt für `OrderReceiptController` und den Freigabe-Link ohne Login.

Liegt sie in dem Plan, der gerade abgefragt wird, kommen `x_pct`/`y_pct`/`w_pct`/`h_pct`,
`shape` und `rotation` mit – sonst stehen sie auf `null`. Der Shop entscheidet daran, ob
er die Station auch in den Plan zeichnet; die Liste zeigt er in jedem Fall. Die Tische
liefern dieselben Felder bereits, die Rechnung im Shop bleibt also eine.

**`POST /events/{event}/orders`** – je Pause darf `table_id` **oder** `station_id`
kommen, genau eines. Bestehende Aufrufe mit `table_id` funktionieren unverändert weiter.
Die Prüfung liegt in `GuestOrderService::store()`, also auf demselben Weg wie die
Backoffice-Buchung – zwei Fassungen der Validierung wären die Art Fehler, die man erst
bemerkt, wenn die Zahlen auseinanderlaufen.

**`GET /orders/{order}`** und der Beleg zeigen den Zielort und ggf. den Abholcode.

---

## H. Shop (`pauseplus-culinaria`)

Durchgesehen am 28.08.2026. Laravel 13 / Livewire 4, eigenes Repo, eigener Deploy.
Der Bestellweg hat sechs Zustände: 0 Personen, 1 Warenkorb, 2 Sitzplatz, 3 Gastdaten,
4 Zahlung, 5 Bestätigung.

### Was sich nicht ändert

`GuestApiClient` braucht **keine neue Methode**: Die Stationen reiten in der
Tischplan-Antwort mit. `floorPlan(string $event, ?int $party, ?int $pause)` nimmt sogar
schon eine Pause entgegen – der Wizard übergibt sie nur nie.

### 1. Der Zustand: ein Ort statt eines Tisches

`public ?int $selectedTableId` wird zu einem Paar – `placeKind` (`table` | `station`) und
`placeId`. Ein Paar statt zweier Felder, weil „beides gesetzt" im Shop genauso wenig
vorkommen darf wie in der Datenbank; mit zwei Feldern wäre dieser Zustand darstellbar.

Betroffen sind rund zehn Stellen: `selectTable()`, `selectedTable()`,
`selectedTableLabel()`, die Prüfung in `next()`, `stepComplete()`, `handleOrderError()`,
der Aufbau der Nutzlast und die Vorlage.

### 2. `FloorPlanMapper`

Bekommt `stations` dazu: Name, Beschreibung, Rest je Pause, Lage im Plan wenn vorhanden.

Und einen ehrlichen Leerzustand: Heute steht dort `$data['rooms'][0] ?? []`. Bei einem
reinen Abholtermin ist das ein leeres Feld – kein Absturz, aber die Vorlage zeichnet einen
leeren Planrahmen. Der Mapper muss „kein Raum" sagen können, statt „ein Raum ohne Tische".

### 3. Fehlercodes sind Vertrag

`handleOrderError()` bildet heute `SLOT_NOT_FOUND`, `TABLE_NOT_IN_EVENT` und `TABLE_FULL`
auf Schritt 2 ab. Dazu kommen die Stations-Gegenstücke, und beide Seiten müssen dieselben
Namen benutzen:

- `STATION_NOT_IN_EVENT` – Station gehört nicht zum Termin
- `STATION_NOT_IN_SLOT` – Station ist in dieser Pause nicht offen
- `STATION_FULL` – Obergrenze erreicht

Beim Konflikt lädt der Shop den Plan neu und leert die Auswahl – das gilt für Stationen
genauso.

### 4. Tracking: den Zielnamen NICHT umbenennen

Der Trichter meldet `Table Selected`. Wird daraus `Place Selected`, bricht der Vergleich
mit allen bisherigen Veranstaltungen ab – Plausible kennt das alte Ziel dann nicht mehr.
Der Name bleibt, die Art des Ortes kommt als Merkmal dazu.

### 5. Texte: zwei sind nicht nur ungenau, sondern falsch

`lang/de` und `lang/en`, je 156 Zeilen. Anzupassen sind `steps.seat`, `seat_title`,
`seat_intro`, `seat_label`, `plan_orientation`, `err_no_table`, `err_table_unavailable`.

Zwei Sätze führen einen Abholgast aber aktiv in die Irre:

- `done_text` – „In der Pause steht alles an Ihrem Tisch bereit."
- `status_success_text` – „Vielen Dank! In der Pause steht alles an Ihrem Tisch bereit."

Wer an einer Station bestellt hat, wartet dann an einem Tisch, an den nichts kommt. Beide
Sätze müssen den Ort kennen.

### 6. Statusseite

`order-status.blade.php` liest `$booking['table']`. Die Antwort des Order-Endpunkts muss
den Ort liefern (Abschnitt G), die Seite ihn anzeigen – und bei Bedarf den Abholcode.

### 7. Mock-Daten

`PausePlusMockData::floorPlan()` liefert Tische für den Betrieb ohne API. Ohne Stationen
zeigt der Shop dort nichts – kein Fehler, aber die Vorschau taugt dann nicht mehr zum
Prüfen.

### 8. Der Schritt „Wann?"

`FloorPlanMapper` nimmt `$slots[0]`, der Wizard ruft ohne Pausen-Filter. Für die
pausenweise Freigabe muss der Gast die Pause wählen können (Abschnitt L). Die Leitung dafür
liegt schon: `GuestApiClient::floorPlan()` kennt den Parameter.

### 9. Außerhalb des Bestellwegs: die FAQ verspricht Tischservice

`lang/de/pausen.php` sagt zweimal etwas, das an einer Abholstation nicht stimmt:

> „…suchen sich einen Tisch aus und bezahlen online. In der Pause steht alles an Ihrem
> Tisch bereit."

> „An dem Tisch, den Sie bei der Bestellung gewählt haben, direkt zu Beginn der Pause.
> Ganz ohne Anstehen."

„Ganz ohne Anstehen" ist an einer Ausgabestelle das Gegenteil dessen, was passiert. Diese
Seite gehört mit angefasst, sobald der erste Termin eine Station anbietet – sonst
widerspricht die Website dem Bestellweg.

## I. Was dieser Plan NICHT vorsieht

- **Keine Umwandlung Tisch → Station.** Löschen und neu setzen; Begründung in D.
- **Keine Station in mehreren Plänen.** Sie ist ein Ort, nicht ein Symbol.
- **Keine eigenen Öffnungszeiten.** Die Steuerung läuft über die Pausen, nicht über Uhrzeiten.
- **Kein Durchsatz je Minute.** Die Obergrenze gilt je Pause, sonst nichts.
- **Keine Änderung an der Platzrechnung.** Tische bleiben Tische.

---

## J. Etappen

0. **Maximale Gruppengröße** (Abschnitt Q) – unabhängig von den Stationen und vorweg,
   weil der Shop heute die falsche Einstellung als Obergrenze liest.
1. **Fundament** – Migrationen, Modelle (`PickupStation`, `EventStation`),
   `zielort()` samt eingefrorenem Ort und Nachfüllung (Abschnitt S), Guard,
   `PickupCapacityService`, dazu die eingecheckte Testbasis (Abschnitt O).
2. **Pflege** – Stationen-CRUD, Werkzeug im Tischplan-Editor, Zuordnung am Termin samt
   Pausen-Häkchen, Veröffentlichungs-Regel, Löschschutz, Duplizieren,
   Einstellung `pickup_identification`.
3. **Betrieb** – Laufzettel, Küche, Bon, Beleg-PDF, Mails, Listen, Export über `zielortLabel()`;
   Codevergabe und -anzeige.
4. **Gast-API + manuelle Buchung** – `floor-plan` um `stations` erweitert,
   `station_id` beim Anlegen, `BookingCreate` auf den Ort-Schritt.
5. **Shop** – Ort-Schritt mit Liste und Flächen im Saalplan, Schritt „Wann?" ab zwei
   Pausen (Abschnitt L), Status, Beleg, Sprachen. Lokal testen, dann Deploy.
6. **Aufräumen** – `reservation_dropoff_slots`, `DropoffSlot`, `DropoffManager` entfernen.
   Vorher nachsehen, ob auf demo oder bei Culinaria noch Zeilen drinstehen: eine leere
   Tabelle löscht man ohne Nachdenken, eine gefüllte will man erst angesehen haben.
7. **Sortiment je Station** (Abschnitt K) – Spalte `sales_list_id` **erst hier**, benannte
   Schritte im Wizard, Reihenfolge nur dort gekippt, wo eine Station ein eigenes Angebot
   führt. Bewusst nach Etappe 6: Bis dahin ändert sich am Bestellweg nichts.

Bestehende Termine merken von alldem nichts: Ohne zugeordnete Station läuft jeder
Ablauf wie bisher.

---

## K. Sortiment je Station

Die Verkaufsliste hängt heute am Termin. Eine Station darf sie **überschreiben**:
`sales_list_id` leer = Sortiment des Termins (der Normalfall), gesetzt = eigenes Angebot
(„im Foyer nur Getränke"). Der Schalter sitzt damit an der Station selbst, nicht als
globaler Modus – ein Modus und eine Liste können auseinanderlaufen, ein einzelnes Feld
nicht.

**Der Preis steckt nicht im Datenmodell, sondern in der Reihenfolge im Shop.** Heute wird
erst der Warenkorb gefüllt (Schritt 1) und dann der Ort gewählt (Schritt 2). Das geht nur,
solange überall dasselbe angeboten wird. Führt eine Station ein eigenes Sortiment, muss
der Ort vor den Warenkorb.

Deshalb: **Die Reihenfolge kippt nur dort, wo sie muss.** Die API meldet je Termin, ob eine
beteiligte Station ein eigenes Sortiment führt; nur dann fragt der Shop zuerst nach dem Ort.
Für Culinaria ändert sich damit nichts – ein laufender Bestellweg mit gemessenem Trichter
wird nicht ohne Not umgebaut.

Dafür müssen die Schritte im Wizard **benannt** statt durchnummeriert werden. `next()`,
`stepComplete()`, die Sprungprüfung und das Tracking rechnen heute mit festen Zahlen
(0…4); bei wechselnder Reihenfolge ist das nicht zu halten. Das ist der eigentliche
Aufwand an dieser Stelle und der Grund, warum es eine eigene Etappe ist.

## L. Pausenwahl im Shop – Voraussetzung für die pausenweise Freigabe

`FloorPlanMapper::map()` nimmt die **erste** Pause (`$slots[0]`), und der Wizard ruft die
Tischplan-Abfrage ohne Pausen-Filter auf. Der Gast wählt heute also keine Pause – er
bestellt immer für die erste.

Solange ein Termin nur eine Pause hat, fällt das nicht auf. Mit zwei Pausen ist es
unabhängig von diesem Feature ein Loch, und für „Station nur in Pause 1" ist es ein
Ausschlusskriterium: Eine pausenweise Freigabe ist unsichtbar, wenn der Gast die Pause
nicht wählen kann.

Also: **ein Schritt „Wann?" im Shop**, eingeblendet ab zwei Pausen. Er gehört ohnehin
dorthin und ist die Voraussetzung für Abschnitt A (`reservation_event_station_slots`).

**Geprüft am 28.08.2026:** Alle 54 Termine bei Culinaria haben genau eine Pause
(`slots_count: 1`, ohne Ausnahme). Es ist also **kein** stiller Fehler im Livebetrieb,
sondern eine echte Erweiterung – nötig in dem Moment, in dem der erste Termin eine zweite
Pause bekommt, und Voraussetzung für die pausenweise Freigabe einer Station.

Beim selben Blick: über alle 54 Termine liegen sechs Buchungen. Der Betrieb läuft, aber
das Mengengerüst ist klein – ein Fehler beim Ausrollen träfe wenige Gäste. Das ändert
nichts an der Sorgfalt, aber es relativiert die Panik.

## M. Offen – bewusst vertagt

**Zwei Gast-APIs.** Bleiben wie sie sind (Entscheidung 26.08.2026). Die Stationen kommen
nur in `events-api.php`; `guest-api.php` bleibt auf dem Stand von heute.

**Sequentielle Raumfreigabe.** Vertagt (Entscheidung 26.08.2026) – wird angesehen, wenn
der erste Raum tatsächlich ausgebucht ist. Siehe Abschnitt N.

**Historie beim Löschen.** Nicht mehr offen – entschieden am 28.08.2026, siehe Abschnitt S:
Der Ort wird an der Buchung eingefroren.

**Drucker je Station.** Der Bon geht an den Team-Drucker. Ob eine Station einen eigenen
bekommt, entscheidet der Betrieb nach dem ersten Einsatz – technisch wäre es inzwischen
klein: Die Wahl des Ziels sitzt seit `5641861` an einer Stelle
(`PrintsBookingReceipt::resetPrintSelection()`).

## N. Fund außerhalb dieses Features

`RoomReleaseService::openRooms()` **hat keinen Aufrufer.** Der Modus ist am Termin, in den
Team-Vorgaben und über drei MCP-Tools einstellbar und wird in der Terminliste als
„sequentiell" angezeigt – angewendet wird er nirgends. Die Gast-API liefert immer alle
Räume.

Für die Stationen heißt das vor allem: **keine Freigabe-Logik nachbauen**, die es beim
Vorbild gar nicht gibt.

**Nachgesehen am 28.08.2026:** Culinaria hat `default_room_release_mode` auf `sequential`
stehen. Die Einstellung ist dort also nicht nur vorhanden, sondern bewusst gesetzt – und
wirkt trotzdem nirgends. Wer davon ausgeht, dass der zweite Raum erst öffnet, wenn der
erste voll ist, irrt sich heute.

---

## O. Livebetrieb: Risiken und Reihenfolge

Culinaria läuft, es kommen Bestellungen herein. Deshalb hier explizit, was beim
Ausrollen passiert und was nicht.

### Der Deploy selbst ist wirkungslos

Es gibt kein Modus-Flag und keine geänderte Vorgabe. Solange keinem Termin eine Station
zugeordnet ist, ändert sich kein einziger Ablauf: dieselben Buchungen, dieselben Bons,
derselbe Shop. Das ist die stärkste Eigenschaft dieses Entwurfs und der Grund für den
Schnitt – nicht ein glücklicher Nebeneffekt.

### Der Punkt ohne Rückkehr ist nicht der Deploy

Er ist die **erste echte Stationsbestellung**. Davor lässt sich der Code zurückrollen; die
neuen Spalten sind nullable und stören niemanden. Danach existieren Buchungen mit
`pickup_station_id` und ohne `table_id` – die alte Fassung zeigt auf dem Bon keine
Ortszeile (der Block hängt an `@if($printable->table)`) und gruppiert im Laufzettel unter
„kein Tisch". Kein Absturz, aber Datenmüll im laufenden Betrieb.

Praktisch heißt das: erst demo, dort eine Station anlegen und einen Termin komplett
durchspielen – Bestellung, Zahlung, Bon, Laufzettel, Beleg, Storno. Erst danach Culinaria.

### Reihenfolge der beiden Repos

Office zuerst, Shop danach. Jede Seite muss mit der **alten** Fassung der anderen laufen:
Die neuen API-Felder sind additiv, `station_id` im Auftrag ist optional. Ein Shop, der
`stations` noch nicht kennt, ignoriert sie; ein Backend, das keinen Auftrag mit
`station_id` bekommt, verhält sich wie heute.

### Migrationen

Alle additiv und nullable. Der eindeutige Index auf `(event_id, pickup_code)` ist
unkritisch – NULL kollidiert in MySQL nicht mit NULL. Läuft im Betrieb, kein Fenster nötig.

Etappe 6 ist die Ausnahme: Sie **löscht** eine Tabelle. Eigener Deploy, nach mindestens
einer durchgeführten Veranstaltung mit Stationen, und vorher `SELECT COUNT(*)`.

### Die eigentliche Schwachstelle: es gibt keine Tests

Kein `tests/`, kein PHPUnit in der `composer.json`. Was bisher „Test" hieß, war ein
Wegwerf-Skript gegen eine temporäre SQLite – es lief und ist danach verschwunden.

Für ein Feature, das die Bestellstrecke anfasst, während Bestellungen hereinkommen, ist
das die größte strukturelle Lücke im ganzen Vorhaben.

**Entschieden am 28.08.2026: Tests kommen dazu.** Mit Etappe 1 entsteht eine kleine,
eingecheckte Basis – `tests/`, PHPUnit in der `composer.json`, Sqlite gegen die echten
Migrationen des Moduls. Bewacht werden die vier Stellen, an denen ein Fehler Geld kostet:

1. der Guard (genau eines beim Anlegen, nicht beides beim Ändern, weder-noch bleibt speicherbar),
2. die Kapazität je Station **und Pause**,
3. `store()` – die Station gehört zum Termin **und** zur gewählten Pause (IDOR),
4. die API-Regel „`table_id` oder `station_id`, genau eines",
5. der eingefrorene Ort – Tisch löschen, Label bleibt.

Bewusst kein Anspruch auf Vollständigkeit: Diese vier tragen das Feature. Ein Testgerüst,
das alles abdecken will, wird nicht geschrieben.

### Zahlung: unverändert Vorkasse (Entscheidung 28.08.2026)

Der Gast zahlt **immer vorab über Mollie**, auch an einer Abholstation. Damit bleibt es bei
den drei Bestätigungswegen von heute, und dieses Feature fasst die Zahlung nicht an – kein
vierter Weg, keine Kasse an der Station.

Eine Buchung aus dem Backoffice ist der Sonderfall: ein eingeladener Gast. Sie wird sofort
bestätigt und erwartet keine Zahlung. Siehe dazu Abschnitt R – das hat eine Nebenwirkung,
die nichts mit Stationen zu tun hat.

## P. Drei Leichen im Modul

Beim Prüfen gefunden, alle ohne Bezug zu diesem Feature:

1. `RoomReleaseService::openRooms()` – kein Aufrufer (Abschnitt N).
2. `BookingConfirmationMailer` – kein Aufrufer. Verschickt wird ausschließlich der
   `OrderConfirmationMailer` aus dem Mollie-Webhook. Sein Template enthält als **einzige**
   Stelle im ganzen Modul einen ungeschützten `$booking->table->label`
   (`emails/booking-confirmation.blade.php:47`) – tot und damit harmlos, aber genau die
   Zeile, die eine Stationsbuchung zerlegen würde, falls jemand den Mailer wiederbelebt.
3. `DropoffSlot` samt Tabelle und Maske – geht in Etappe 6.

Alle übrigen Stellen mit `->table->` ohne `?->` sind durch ein `@if` gedeckt – geprüft
gegen den Stand nach `d1e9d4b`, also einschließlich des neuen Statuswechsel-Dialogs. Die
Vorlagen für Bon, Beleg, Buchungsliste, Dashboard und Statuswechsel laufen mit einer
Stationsbuchung fehlerfrei; sie zeigen nur nichts an, bis Etappe 3 sie umstellt.

Ein Anzeigeort ist seit `5d186eb` **weggefallen**: Die eigenständige Laufzettel-Druckseite
(`function-sheet.blade.php`, `FunctionSheetController`) gibt es nicht mehr, gedruckt wird
aus der Ansicht heraus. Eine Stelle weniger, die Etappe 3 anfassen muss.

---

## Q. Maximale Gruppengröße (Entscheidung 28.08.2026)

Unabhängig von den Stationen, aber hier entschieden und deshalb hier notiert.

Die größte buchbare Personenzahl wird **einstellbar**: eine Vorgabe fürs Team, je Termin
überschreibbar, und der Shop zeigt genau diesen Wert in seiner Personen-Auswahl.

- `reservation_checkout_settings.max_guest_count` – Vorgabe des Teams, Kategorie
  „Veranstaltung & Plätze" (`partials/settings/termine.blade.php`).
- `reservation_events.max_guest_count` – nullable, leer = Vorgabe des Teams.

Der wirksame Wert geht über die API mit und ersetzt **drei** heutige Behelfe:

1. `guest.count` wird in `EventController::createOrder` und im `GuestBookingController`
   hart gegen `max:20` geprüft – eine Zahl im Code, die niemand ändern kann.
2. `BookingCreate::maxGuests()` im Backoffice rechnet sich seinen eigenen Wert.
3. **Der Shop leitet die Obergrenze aus der weichen Kapazität ab.** `CheckoutWizard`
   setzt `maxGuests` aus `max_group_empty` – also aus `max_group_empty_table`. Das ist
   nicht willkürlich: Ist die weiche Kapazität an, kann eine Gruppe entweder in die freien
   Plätze eines Tisches passen oder über die Sonderregel auf einen leeren – die größte
   überhaupt platzierbare Gruppe ist also das Maximum aus beidem.

   Genau daran hakt es: Der Shop nimmt nur den zweiten Teil. Ist ein Tisch größer als die
   weiche Grenze, deckelt er zu tief; ist die weiche Kapazität aus, hat die Zahl gar keine
   Bedeutung mehr, wird aber weiter gelesen.

**Nachgemessen am 28.08.2026 bei Culinaria:** `max_group_empty_table` = 8, weiche
Kapazität an, größter Tisch in allen drei Plänen (Rossini, Gartenhalle, Offenbach Saal)
= 6. Die Rechnung des Shops liefert dort also **derzeit das richtige Ergebnis** – acht.
Falsch wird sie in dem Moment, in dem ein Tisch mit mehr als acht Plätzen entsteht oder
jemand die weiche Kapazität abschaltet.

Also kein Feuer, sondern eine Zahl, die aus Versehen stimmt. Die eigene Einstellung sagt,
was gemeint ist, statt es aus einer verwandten Größe zu erraten.

Fällig **vor** den Stationen, weil unabhängig und klein. Der Bestellweg im Shop ändert sich
dabei nicht, nur woher die Obergrenze kommt.

## R. Fund: eingeladene Gäste erzeugen Umsatz, der nie eingeht

Aus der Antwort „Backoffice-Buchung = eingeladener Gast" folgt etwas, das vorher niemand
so betrachtet hat.

`placeForStaff()` legt die Buchung **sofort bestätigt** an, mit `payment_method = 'onsite'`.
Preise kommen wie überall aus der Datenbank – eine Einladung hat also einen Betrag.
„Bestätigt" liegt zugleich innerhalb der Grenze, an der gezählt wird – seit `9615c48`
steht die an einer Stelle (`CheckoutSetting::umsatzStatus()`) und gilt für Finanzen,
Artikel-Auswertung, Export und DATEV gemeinsam.

Damit gilt heute: Ein eingeladener Gast erhöht den ausgewiesenen Umsatz, und im
Buchungsstapel entsteht ein Satz „Geldkonto an Erlöskonto" über Geld, das nie eingegangen
ist. Die Finanzen weisen den Anteil zwar getrennt aus (`onsite`), ziehen ihn aber nicht ab.

Solange „vor Ort bezahlt" wörtlich gemeint war, stimmte das. Für eine Einladung stimmt es
nicht.

Denkbare Antwort, **nicht Teil dieses Plans**: ein eigener Zahlungsweg „eingeladen" neben
`onsite`, der in Küche, Laufzettel und Bon voll zählt, in Umsatz und DATEV aber nicht.
Seit die Abgrenzung an einer Stelle steht, wäre das auch nur noch ein Eingriff statt vier –
allerdings kein Status, sondern ein Zahlungsweg, die Abgrenzung müsste also beides ansehen.
Ob das drängt, hängt daran, wie oft eingeladen wird – und ob der Steuerberater die Zahlen
schon so bekommen hat.

---

## S. Was ein gelöschter Tisch heute mitnimmt

Nachgeprüft am 28.08.2026, weil die Frage aufkam, ob dabei Verkäufe verlorengehen.

### Die Verkäufe sind sicher

Sie hängen nicht am Tisch:

- `bookings.table_id` ist `nullOnDelete` – die **Buchung bleibt**, nur der Verweis wird leer.
- `booking_items.menu_item_id` und `payments.booking_id` sind seit `2026_07_18_000002`
  `restrictOnDelete`. Positionen mit eingefrorenem Preis und der Zahlungsnachweis lassen
  sich gar nicht wegräumen.
- Die **Artikel-Auswertung** rechnet über `reservation_booking_items` verbunden mit
  `reservation_bookings` – kein Tisch beteiligt.
- Die **Finanzen** summieren Positionen. Ebenfalls unberührt.

Umsatz, Mengen, Steuer, Beleg: alles bleibt. Diese Sorge ist unbegründet.

### Verloren gehen zwei Spalten im Export

`table` wird leer – erwartbar. Und `venue` wird **mit** leer, weil es heute über
`table.floorPlan.venue` abgeleitet wird; ohne Tisch kein Venue. Das ist die eigentliche
Überraschung, und sie ist mit dem Wechsel auf `Event.venue_id` (Abschnitt E) erledigt.

### Der schärfere Punkt: das Löschen ist nicht bewacht

`FloorPlanEditor::deleteTableAndCloseModal()` ruft `Table::findOrFail(...)->delete()` – ohne
jede Prüfung, und `Table` hat keinen `deleting`-Haken. Wer im Editor eine Bestuhlung
umbaut, löscht damit den Tisch aus **allen** alten Buchungen mit, auch aus vergangenen
Veranstaltungen. Ohne Rückfrage, ohne Hinweis.

Der Tischplan ist gegen Löschen geschützt (`anstehendeTermine()`), der einzelne Tisch
darin nicht. Und das Umstellen einzelner Tische ist der häufigere Vorgang.

### Entschieden am 28.08.2026: der Ort wird eingefroren

`place_kind` und `place_label` an der Buchung, beim Anlegen in `store()` gefüllt – dort,
wo Preise und Steuersätze schon eingefroren werden. Ein Beleg soll zeigen, was galt.

Gelesen wird **nachrangig** (Abschnitt B): Solange Tisch oder Station existieren, gilt der
aktuelle Name; ist der Ort gelöscht, steht dort weiter „Tisch 12" statt einem Strich.
Wirkt für Tisch und Station gleichermaßen und macht die Frage „darf ich den löschen?"
harmlos.

**Einmalige Nachfüllung in der Migration.** Alle vorhandenen Buchungen, deren Tisch noch
existiert, bekommen Art und Namen aus dem heutigen Stand. Damit greift der Schutz auch
rückwirkend, statt erst ab der nächsten Bestellung. In Laravel geschrieben, nicht als SQL
mit JOIN – die Testbasis läuft auf SQLite, der Betrieb auf MySQL.

**Was NICHT eingefroren wird: der Raumname.** Der Bon zeigt heute „Tisch 5 – Großer Saal";
nach dem Löschen des Tisches bleibt „Tisch 5". Der Raum ist Beiwerk, nicht die Kennung des
Ortes, und drei eingefrorene Spalten für einen Zusatz wären zu viel. Bewusst so, nicht
übersehen.

Ein Test hält es fest: Tisch löschen, Label bleibt.

Damit ist der Löschschutz für die Station auch nicht mehr die einzige Verteidigung – er
verhindert weiterhin das Löschen bei anstehenden Terminen, aber die Vergangenheit hängt
nicht mehr daran.
