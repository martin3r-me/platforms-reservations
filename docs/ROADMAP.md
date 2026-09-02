# PausePlus – Roadmap / offene Umsetzungspunkte

Stand: 12.06.2026, Reihenfolge der nächsten Vorhaben ergänzt am 31.08.2026, Pausenfrage beantwortet am 01.09.2026, Live-Sicht und Auswertung gebaut und ausgerollt am 02.09.2026 · Go-live-Ziel: **01.08.2026**, erste Veranstaltung 29.08.2026 (Bodo Wartke).

Meilenstein 1 (Produktmodul + Klick-Dummy bis Mock-Checkout) ist umgesetzt.
Die folgenden Punkte sind **vereinbart und noch umzusetzen**. Referenz für viele
Punkte ist das Altsystem des Kunden (Guestofy, WordPress/WooCommerce/Stripe):
https://historische-stadthalle-wuppertal-culinaria.guestofy.events/#/

## Was ist offen? (nachgeprüft am 02.09.2026)

Die Liste unten war an mehreren Stellen veraltet – Erledigtes stand als offen,
und ein Punkt beschrieb sogar einen Import, den es seit Juli nicht mehr gibt.
Alles hier ist gegen den Code geprüft, nicht gegen die eigene Erinnerung.

**Vor Go-live zwingend (M2): nichts mehr offen** außer einer Klärung –
was mit der sequenziellen Raumfreigabe passieren soll, wenn jemand storniert.
Das ist eine Frage fürs Haus, keine Programmieraufgabe.

**Beim Kunden hängend, nicht bei uns:**
- Plausible-Ziel `Pause Selected` anlegen (beim Kollegen)
- Bleibt Culinarias Tischbindung auf „jede Pause einzeln"?
- Finaler Wortlaut Altersnachweis/Datenschutz
- Die echten 37 Artikel (Vorlage steht bereit)
- Reihenfolge im Bestellweg und Platz- statt Tischwahl – beides Fragen ans Haus

**Zu bauen, nach Größe:**
1. **Abholstationen** (`PLAN-abholstationen.md`) – das große Stück, in sieben
   Etappen. Hängt an keiner offenen Frage mehr.
2. **Upselling/Cross-Selling** inkl. A/B-Tests. Bundles selbst sind fertig.
3. **Datums-/Vorstellungssuche** in der Gäste-Terminübersicht.
4. **Servicegebühr** (optional, kennt das Altsystem).
5. **Tarifgrenzen zählen** – heute begrenzt nichts, und niemand merkt, wenn ein
   Basic-Kunde 80 Artikel führt.

**Nicht mehr auf der Liste, weil nachgeprüft erledigt:** Bestellschluss-
Enforcement, Concurrency-Härtung der Platzvergabe, Mollie-SDK, Bundles, der tote
`BookingConfirmationMailer` und die doppelte `CheckoutSetting`-Abfrage.

**Fraglich, ob überhaupt noch gewollt:** der Guestofy-Import (siehe M3).

---

## Nächste Vorhaben – Reihenfolge (Stand 02.09.2026)

Von den ursprünglich vier Vorhaben sind drei erledigt und ausgerollt: die
sequenzielle Raumfreigabe (1), die mehreren Pausen (4) und der Wegfall des
Schritt-Index (5). Dazu die nachgeschobene Live-Sicht samt Auswertung (6).

**Übrig in dieser Liste sind nur noch die Abholstationen** – die Punkte 2, 3
und 7. Sie sind zurückgestellt, nicht blockiert: An ihnen hängt keine offene
Frage mehr, seit der Schritt „Wo?" sich ohne Umnummerierung einhängen lässt.

Das heißt aber nicht, dass sonst nichts offen wäre. Was außerhalb dieser Liste
noch aussteht, steht weiter unten und ist teils dringender als die Stationen –
und ist teils dringender als die Stationen.

*(Nachtrag 02.09.2026: An dieser Stelle standen die Concurrency-Härtung und das
Bestellschluss-Enforcement als „beide noch offen". Die Härtung ist inzwischen
gebaut; das Enforcement war beim Nachsehen längst vollständig — die Zeile unter
M2 war veraltet. Übrig aus diesem Punkt ist nur der Storno-Fall der
sequenziellen Raumfreigabe.)*

Die Reihenfolge der Vorhaben ergibt sich aus ihren Abhängigkeiten, nicht aus
ihrer Größe.

**1. ~~Sequenzielle Raumfreigabe fertigstellen.~~ Erledigt am 01.09.2026.**
Sie wurde nur im VA-Dashboard angezeigt, im Bestellweg aber nicht gefragt – zwei
Bildschirme widersprachen sich. Jetzt liefert die Gast-API je Raum und Pause
offen/zu samt Begründung, der Shop zeigt gesperrte Räume mit dem Grund statt sie
wegzulassen, und `store()` weist mit `ROOM_CLOSED` ab (nur auf dem Gast-Weg).
Dabei mitgenommen: Ein von Hand geschlossener Raum hielt die Kette an; er wird
jetzt übersprungen. Der Shop kannte zuvor ohnehin nur `rooms[0]` – bei zwei
Räumen sah der Gast den zweiten nie. Dazu eine Einstellung, ob gesperrte Räume
überhaupt sichtbar sind, und eine Rückfrage vor dem Entfernen eines Raums mit
Buchungen.

Die Frage „heißt voll wirklich 100 %?" hat sich damit erledigt: Der Wert steht
je Raum im Terminformular, ist beschriftet („Freigabe ab %") und erklärt sich
darunter im Satz („Ab 100 % von 16 Plätzen öffnet ROSSINI"). Das Haus entscheidet
es selbst und sieht dabei, was es entscheidet.

Der Vorgabewert bleibt bewusst 100 – die strengste Auslegung. Sie öffnet einen
Raum nie zu früh; wer die letzten Restplätze nicht abwarten will, setzt 85 oder
90. Ein niedrigerer Vorgabewert würde für alle raten, was nur das Haus weiß.

**2. Abholstationen, Etappe 1** *(auf Wunsch zurückgestellt – großes Feature, 01.09.2026)* (`PLAN-abholstationen.md`). Migrationen, Modelle,
Zielort, Guard, Kapazitätsdienst – und die erste **eingecheckte Testbasis** des
Moduls. Ohne Oberfläche: deploybar, ohne dass sich etwas ändert. Hängt an keiner
offenen Frage.

**3. Abholstationen, Etappen 2–4.** Pflege samt MCP-Tools, Betrieb (Laufzettel,
Küche, Bon, Belege), Gast-API und manuelle Buchung. Danach sind Abholstationen im
Backoffice vollständig benutzbar; nur der Shop fehlt.

**4. ~~Mehrere Pausen.~~ Erledigt am 01.09.2026** (im Shop; noch nicht ausgerollt). Der Kunde hat am selben Tag geantwortet: Mehrere Pausen
bleiben die Ausnahme (letzte Saison: eine Veranstaltung mit zwei Pausen), aber es
soll sie geben; der Gast soll die Pause wählen und in beiden reservieren können.
Backoffice und Server sind fertig – Pausen lassen sich im Terminformular schon
beliebig anlegen, `slots` ist eine Liste, je Pause entsteht eine Buchung unter
einer gemeinsamen Bestellung und einer Zahlung. Es fehlt allein der Shop, der bis
heute genau eine Pause verschickt (`$slots = [[…]]`).

Zwei Punkte deckt die Antwort nicht ab; sie werden hier entschieden:

*Warenkorb je Pause.* Gefragt war „unterschiedliche Artikel je Pause?", geantwortet
wurde nur zur Reservierung. Wir bauen es je Pause: Der Server kann es ohnehin
(`items` hängen am Slot), das Altsystem macht es so, und alles andere wäre eine
erfundene Einschränkung – Sekt zur ersten Pause und Kaffee zur zweiten ist der
Normalfall, nicht der Sonderfall.

*Die Tischbindung wird eingestellt, nicht festgelegt.* Hier lag ich zuerst falsch:
„einmal wählen" ist keine Regel, sondern eine von zwei Betriebsarten. Entweder
gehört der Tisch dem Gast den ganzen Abend, oder jede Pause wird einzeln vergeben
und der Saal zwischen den Pausen neu verkauft. Beides ist vertretbar, nur das Haus
weiß welches – also je Termin einstellbar mit Team-Standard, Vorgabe „ganzer
Abend" (die strengere Lesart, sie verkauft nie einen Platz zu viel).

Der Modus entscheidet zugleich, wie sich **mehrere Räume** verhalten: bei Bindung
an den Termin öffnen die Räume für den Abend, bei Bindung an die Pause je Pause –
dann kann derselbe Raum in Pause 1 offen und in Pause 2 zu sein. Ausgearbeitet in
**`PLAN-mehrere-pausen.md`** (dort auch die Zählfalle: über Pausen hinweg wird
nach Parteien gezählt, nicht summiert, sonst macht die eigene zweite Bestellung
den eigenen Tisch voll).

**5. ~~Der Schritt-Index verschwindet.~~ Erledigt am 01.09.2026.** Dieser Punkt hieß
vorher „Abholstationen, Etappe 5 – zusammen mit Punkt 4", damit der Wizard nur ein
einziges Mal umnummeriert wird. Die Stationen sind zurückgestellt; warten hieße
inzwischen unbegrenzt warten. Also fällt nicht der Umbau, sondern seine Ursache:
Statt `match ($this->step)` und `min(4, …)` baut der Wizard seine Schritte zur
Laufzeit als Liste, in der Schritte fehlen dürfen.

Damit ist „Wann?" bei einer einzigen Pause gar nicht vorhanden – 53 von 54
Terminen sehen weiter genau vier Schritte, niemand klickt durch einen Schritt ohne
Auswahl –, und „Wo?" lässt sich später einhängen, ohne dass sich eine Nummer
verschiebt. Die Plausible-Ziele hängen danach an Namen statt an Zahlen, der
Trichter bleibt über beide Umbauten hinweg vergleichbar. Das ist mehr Arbeit als
ein weiterer Zweig im `match`, aber die einzige Fassung, die den zweiten Umbau
nicht ein zweites Mal bezahlt.

**Stand der Umsetzung (02.09.2026).** Alle fünf Etappen aus
`PLAN-mehrere-pausen.md` sind gebaut, an zwei Testterminen auf demo geprüft –
einer je Betriebsart – und bei Culinaria ausgerollt: Modul und Shop sind live.

**Offen dazu:** Das Plausible-Ziel `Pause Selected` muss im Plausible-Konto
einmal von Hand angelegt werden (an einen Kollegen gegeben am 02.09.2026);
solange es fehlt, feuert das Ereignis ins Leere. Und die Tischbindung bei
Culinaria steht derzeit auf „jede Pause einzeln" – entgegen dem ausgelieferten
Vorgabewert „ganzer Abend". Ob das so bleiben soll, ist beim Kunden noch nicht
bestätigt.

**6. ~~Live-Sicht auf laufende Checkouts.~~ Erledigt und ausgerollt am 02.09.2026**
(`PLAN-live-checkouts.md`). Modul, Culinaria und Shop sind live.

Gebaut in fünf Etappen: Server (`reservation_checkout_sessions`), Karte „Gerade
im Bestellweg" im VA-Dashboard, Meldung aus dem Shop, Datenschutz, und die
Auswertung „Bestellwege" (`reservation_checkout_stats`).

Drei Entscheidungen, die man kennen sollte, bevor man daran weiterbaut:

*Der Shop meldet an einer einzigen Stelle* – `dehydrate()`, am Ende jeder
Anfrage. Ein Aufruf je Aktion wäre früher oder später an einem neuen Weg durch
den Wizard vorbeigelaufen. Der HTTP-Aufruf läuft über `app()->terminating()`,
also nach der Antwort an den Gast; ein langsames Office darf den Shop nicht
langsam machen.

*Schritt-NAME plus Position vom Shop, nicht im Office gerechnet.* Wie viele
Schritte ein Bestellweg hat, entscheidet der Shop zur Laufzeit („Wann?" fehlt bei
einer Pause, „Wo?" kommt mit den Abholstationen). Eine zweite Fassung dieser
Regel im Office liefe beim ersten Umbau lautlos auseinander.

*Die Live-Zeile wird beim Verschwinden zur Statistikzeile* – in `beenden()`
(bestellt) und `aufraeumen()` (abgebrochen). Keine zweite Meldekette, und die
Auswertung kann nicht von der Live-Sicht abweichen.

Was die Auswertung bewusst NICHT zeigt: einen Trichter mit Prozent je Stufe. Ein
Bestellweg mit einer Pause hat vier Schritte, einer mit zweien fünf, und „Pause"
gibt es im ersten Fall gar nicht – ein gemeinsamer Nenner sähe nur so aus, als
wäre er einer. Gezeigt wird die Verteilung der tatsächlichen Endpunkte.

**Zwei Dinge, die später jemanden irritieren werden.** Die Auswertung zählt
**ab dem Deploy-Tag (02.09.2026), nicht rückwirkend**: Eine Statistikzeile
entsteht in dem Moment, in dem ein Bestellweg endet, und aus Bestellungen allein
lässt sie sich nicht rekonstruieren. Und ein Abbruch wird per Los beim Schreiben
oder beim Öffnen der Auswertung verbucht – auf einer ruhigen Seite hinkt die Zahl
bis zum nächsten Blick hinterher, nicht länger.

**7. Abholstationen, Etappen 5–7** *(zurückgestellt)*. Der Schritt „Wo?" hängt
sich nach Punkt 5 ohne Umnummerierung ein. Danach Drop-off-Reste entfernen;
Sortiment je Station zuletzt, weil es die Reihenfolge im Bestellweg kippt.

**Nebenbei, jederzeit:** klären, ob eingeladene Gäste weiter als Umsatz zählen
(`PLAN-abholstationen.md`, Abschnitt R).

*(Am 02.09.2026 aus dieser Liste gestrichen, weil beim Nachsehen bereits erledigt:
die doppelte `CheckoutSetting::forTeam()`-Abfrage in `checkoutFields` – dort steht
nur noch eine – und der tote `BookingConfirmationMailer`, den M2 unten schon als
entfernt führt. Eine Liste, die Erledigtes mitschleppt, wird irgendwann nicht mehr
gelesen.)*

---

## Produkt & Vertrieb – offener Punkt (31.08.2026)

**Die Tarifgrenzen sind nirgends hinterlegt.** Die Produkt-Landingpage
(`pauseplus-produkt-landingpage.dev.bhgdigital.de`) staffelt nach Räumen und
Artikeln – Basic bis 2 Räume und 25 Artikel, Standard bis 5/100, Premium bis
15/300. Im Modul gibt es dazu nichts: keine Mengenbegrenzung, keine
Tarif-Zuordnung, keinen Zähler, nichts, was beim 26. Artikel bremst oder auch nur
warnt. Jedes Team kann beliebig viele Räume und Artikel anlegen.

Solange von Hand verkauft wird, ist das Vertrauenssache und in Ordnung. Zwei
Folgen sollte man aber bewusst tragen:

- Beim **Onboarding** muss jemand von Hand nachhalten, welcher Tarif gilt.
- Beim **Upselling** merkt niemand, wenn ein Basic-Kunde längst 80 Artikel führt –
  weder der Kunde noch wir.

Drei Wege, in aufsteigender Härte:

1. **Nur zählen.** Eine Übersicht je Team (Räume, Artikel, Termine) im
   Betreiber-Backoffice. Kein Eingriff, aber man sieht es.
2. **Weich begrenzen.** Beim Überschreiten ein Hinweis in den Einstellungen
   („Ihr Tarif sieht 25 Artikel vor, angelegt sind 31") – ohne zu blockieren.
3. **Hart begrenzen.** Anlegen wird verweigert. Braucht eine Tarif-Zuordnung je
   Team und einen Weg, sie zu ändern; in `platforms-core` gibt es mit den
   `billables` des Planner-Moduls bereits ein Muster für nutzungsabhängige Kosten.

Empfehlung: erst (1), weil es nichts kaputtmachen kann und die Frage beantwortet,
ob (2) oder (3) überhaupt gebraucht wird.

---

## M2 – Zahlung & Härtung (Kern, vor Go-live zwingend)

- [x] **Mollie-Integration (Fundament)**: Hosted-Redirect-Checkout, Webhook
      (`/api/reservation/payment/webhook`), Statusübergänge `pending → confirmed`
      bzw. `→ cancelled`. Key als verschlüsselte Team-Einstellung hinter
      Resolver-Seam (`MollieCredentialResolver`, später auf platforms-integrations
      umstellbar). Bleibt inert ohne Key (Checkout läuft dann als Demo-Mock).
      *(02.09.2026 nachgeprüft: Das SDK `mollie/mollie-api-php` steht in der
      composer.json – der Punkt war erledigt. Bei Culinaria sind seit August 20
      Buchungen mit 393,50 € verbucht; auf dem Gast-Weg wird „bestätigt" erst
      durch den Mollie-Webhook gesetzt, die Zahlung läuft also produktiv. Ein
      ausdrücklicher, protokollierter Test-Durchlauf mit Testkey ist nirgends
      dokumentiert – wer einen braucht, muss ihn noch machen.)*
- [x] **E-Mail-Bestätigungen** an Gäste — `OrderConfirmationMailer` versendet über den
      CRM-Comms-Dienst (`PostmarkEmailService` + team-scoped `CommsChannel`, wie das
      Events-Modul) mit eigenem HTML-Template; ausgelöst beim Mollie-„bezahlt"-Übergang
      (`MolliePaymentService`). Enthält Bestellnummer, Positionen je Pause und die
      Beleg-Links. Inert ohne aktiven Postmark-Email-Channel.
      *(Der ursprünglich hier genannte `BookingConfirmationMailer` – eine Bestätigung je
      Buchung statt je Bestellung – ist am 01.09.2026 entfernt worden: Seit der
      Order-Klammer hatte er keinen Aufrufer mehr.)*
- [x] **Bestellschluss-Enforcement** — beim Nachsehen am 02.09.2026 bereits
      vollständig, die Zeile war veraltet. `Event::isOrderable()` prüft Status UND
      `order_deadline_at`; `GuestOrderService::place()` wirft `ORDER_CLOSED`, und
      zwar an der einzigen Stelle, die Gast-Buchungen anlegt. Beide APIs melden
      `orderable` mit, der Shop lässt danach gar nicht erst in den Bestellweg –
      und wenn die Frist mitten im Bestellweg abläuft, fängt er den Fehlercode ab.
      Termine ohne Frist (Altbestand) enden mit dem Veranstaltungstag statt ewig
      offen zu bleiben.
      *Bewusst ausgenommen:* der Backoffice-Weg (`placeForStaff`). Genau deshalb
      ruft jemand an – der Shop ist dann schon zu.
- [x] **Concurrency-Härtung Platzvergabe** — erledigt am 02.09.2026. Die Prüfung
      „ist an diesem Tisch noch Platz?" stand vor der Transaktion, das Schreiben
      darin; zwei gleichzeitige Bestellungen konnten denselben letzten Platz
      bekommen. Jetzt sperrt die Transaktion zuerst die betroffenen Tische
      (nach Id sortiert, als erste Anweisung) und prüft danach.
- [ ] **Sequential-Release bei Stornos.** Stand mit im Punkt darüber, ist aber
      eine andere Frage und noch offen: Storniert jemand im ersten Raum, ist der
      nicht mehr voll – der zweite müsste dann eigentlich wieder zufallen. Was
      passiert mit Buchungen, die dort schon liegen? Vermutlich: nichts, und der
      Raum bleibt offen. Aber entschieden ist es nicht.
- [x] **Termin duplizieren** im Admin (Saisonpflege: dutzende ähnliche Konzerte).
- [x] **Tische pro Termin sperren** (Altsystem: `disabled_table_ids`).
- [~] Import der echten **37 Artikel**: CSV-Beispielvorlage im Import-Dialog
      herunterladbar (`resources/samples/artikel-import-vorlage.csv`), damit der
      Kunde die Liste vorbereiten kann. **Offen:** echte Liste + Freigabe-Durchlauf.

## Produktentscheidungen – beim nächsten Kundentermin klären

- [x] **Mehrere Pausen pro Bestellung?** **Am 01.09.2026 beantwortet:** Termine mit
      mehreren Pausen sind selten (letzte Saison eine), soll es aber geben; der Gast
      wählt die Pause und darf in beiden reservieren. Umsetzung siehe Punkte 4–5 oben.
- [ ] **Flow-Reihenfolge**: Altsystem Datum/Vorstellung → Personen → Pause → Sitzplatz
      → Produkte; unser Meeting-Flow Gastdaten → Produkte → Sitzplatz. Gäste kennen
      das Altsystem → abnehmen lassen.
- [ ] **Platz- vs. Tischwahl**: Altsystem markiert n einzelne Plätze; wir wählen einen
      Tisch mit Restplatz-Prüfung. Reicht Tischwahl?
- [x] Wortlaut **Altersnachweis/Datenschutz** im Checkout pflegbar (Einstellungen →
      Checkout-Texte, `reservation_checkout_settings`, mit Defaults). **Offen:** finaler
      Wortlaut vom Kunden + Kaiserwagen-Klärung mit Herrn von Bauer (out of scope bestätigt).

## M3 – Komfort, Sortiment, Migration

- [x] **Bundles** – gebaut. Ein Artikel kann Bestandteile haben (`is_bundle`,
      `components`), zerfällt beim Bestellen in sie, und die MwSt-Mischung wird
      cent-genau je Bestandteil verteilt (`CartCalculator`). Auswertung und Beleg
      rechnen mit den Bestandteilen, nicht mit dem Paket.
- [ ] **Upselling/Cross-Selling inkl. A/B-Tests** – nicht gebaut. Hing in derselben
      Zeile wie die Bundles und wäre beim Abhaken lautlos verschwunden.
- [ ] **Datums-/Vorstellungssuche** in der Gast-Terminübersicht (viele Termine pro
      Saison, teils mehrere pro Tag; „Keine Vorstellung für …“-Zustand).
- [ ] **Migration der Saisondaten aus Guestofy** – *diese Zeile war falsch.*
      Sie beschrieb einen Import, den es nicht mehr gibt: `GuestofyImporter`,
      die Livewire-Komponente, die Route und der Knopf „Aus Alt-System" wurden am
      **15.07.2026 entfernt** (Commit `4eb08fa`). Im Modul ist heute kein
      Alt-System-Import vorhanden – weder für Räume noch für Termine.
      Wer migrieren will, macht es von Hand oder baut den Import neu. Ob das
      überhaupt noch gebraucht wird, ist die eigentliche Frage: Die Räume stehen
      längst gepflegt im System, und Termine werden ohnehin je Saison neu angelegt.
- [x] **Konfigurierbare Checkout-Consents** (Datenschutz-/18+-Texte pflegbar statt
      hartkodiert) – siehe Einstellungen → Checkout-Texte.
- [ ] **Servicegebühr** (optional, Altsystem: `service_charge`).
- [ ] Produkt-Sortierung nach Verkaufszahlen; „Service-Runden“-Konzept des
      Altsystems (`enable_service_rounds`) verstehen und ggf. übernehmen.
- [ ] Reporting/Dashboard-Ausbau + Buchhaltungsschnittstelle (MwSt-Aufschlüsselung,
      Abstimmung mit Sabine), Team-Slug für die öffentliche Übersicht.

## M3/M4 – Tischplan & CI

- [x] **Raum-Hintergrundbild** (Grundriss) im Tischplan-Editor und -Viewer
      (`background_context_file_id`, Upload im Editor, Anzeige in Editor-Canvas +
      Gast-Tischplan).
- [ ] Tisch-**Rotation/Varianten**, konfigurierbare Tischfarben.
- [ ] **CI-/Design-Pass** (Conny). Branding-Referenz aus dem Altsystem:
      Primärfarbe `#285567` (Petrol), Fonts Cormorant Garamond + Inter, Culinaria-Logo.
- [ ] UAT mit Kunde, Lasttest Pausen-Peak, Clean Cut (Abschaltung Altsystem zur
      Sommerpause).

## Architektur (später) – Gast-Frontend unter eigener Domain

Ziel: Die Gast-/Bestellseiten unter eigener Domain je Kunde, z. B.
`culinaria.pauseplus.de`, als **eigenständiges Laravel-Projekt** (eigenes
GitHub-Repo), das die Plattform per **Bearer-Token-API** anspricht.

Ergebnis der Core-Analyse (platforms-core, read-only – NICHT ändern):
- **Auth = Laravel Passport (JWT-Bearer), pro *User*** – Middleware-Alias
  `api.auth` (`Platform\Core\Http\Middleware\ApiAuthenticate`). Token erzeugen
  via `php artisan api:token:create` oder im UI (ModalUser). **Kein** Login→Token-
  Endpoint, **kein** Per-Team-Token. Achtung Sicherheits-Fallback in `api.auth`:
  vertraut `X-User-Email`-Header (Teams-Embedding) – muss aus dem Internet
  unerreichbar/abgesichert sein.
- **Keine Per-Team-Custom-Domain im Core**: `teams` hat keine `domain`-Spalte,
  keine `domains`-Tabelle, keine Host-Auflösung. `ModuleRouter` kann nur
  `modul.basehost` (subdomain-Modus), nicht `kunde.pauseplus.de`.
- **CORS ist im Repo nicht konfiguriert** (liegt in der Host-App).

Empfohlener Weg (nutzt Bestehendes, **ohne** Core-Änderung):
- Gast-Frontend als **server-gerendertes** eigenes Laravel-Projekt. Es hält
  **serverseitig** einen Bearer-Token (Passport-PAT eines „Service-Users“, der
  dem jeweiligen Kunden-Team zugeordnet ist) und ruft die Plattform-API
  **server-zu-server** auf. Vorteile: Token bleibt geheim (nie im Browser),
  Team-Scoping kommt aus dem Token, **kein CORS nötig** (kein Cross-Origin im
  Browser), Domain→Kunde-Mapping lebt im Frontend-Projekt (Core braucht keine
  Domain-Tabelle).
- Neue, team-gescopte JSON-Endpoints im Modul (`routes/api.php`, hinter
  `api.auth`, Team aus Token) – Logik aus den vorhandenen Livewire-Komponenten/
  Services ziehen, `Platform\Core\Http\Controllers\ApiController` als Basis:
  - `GET  /api/reservation/events` (published), `GET /events/{uuid}`
    (Slots/Räume/Tische + Verfügbarkeit), `GET /events/{uuid}/products`
    (Verkaufsliste), `GET /settings/checkout` (Texte)
  - `POST /api/reservation/bookings` (Buchung anlegen),
    `POST /bookings/{uuid}/payment` (Mollie-Checkout-URL),
    `GET  /bookings/{uuid}/payment-status`
  - Mollie-Webhook bleibt serverseitig wie bisher.
- Falls später doch **Browser-Direktzugriff** aufs API gewünscht ist: dann CORS
  in der Host-App konfigurieren + Endpoints public/uuid-gescoped absichern.
