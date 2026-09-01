# PausePlus – Roadmap / offene Umsetzungspunkte

Stand: 12.06.2026, Reihenfolge der nächsten Vorhaben ergänzt am 31.08.2026 · Go-live-Ziel: **01.08.2026**, erste Veranstaltung 29.08.2026 (Bodo Wartke).

Meilenstein 1 (Produktmodul + Klick-Dummy bis Mock-Checkout) ist umgesetzt.
Die folgenden Punkte sind **vereinbart und noch umzusetzen**. Referenz für viele
Punkte ist das Altsystem des Kunden (Guestofy, WordPress/WooCommerce/Stripe):
https://historische-stadthalle-wuppertal-culinaria.guestofy.events/#/

## Nächste Vorhaben – Reihenfolge (Stand 31.08.2026)

Vier Vorhaben stehen an. Die Reihenfolge ergibt sich aus ihren Abhängigkeiten,
nicht aus ihrer Größe.

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

**4. Mehrere Pausen** – sobald der Kunde geantwortet hat (siehe Abschnitt
„Produktentscheidungen"). Schritt „Wann?" im Shop, und ob ein Gast in einem
Vorgang für mehrere Pausen bestellen kann. Serverseitig ist das Anlegen über
mehrere Pausen fertig (`slots` als Liste, je Pause eine Buchung); es fehlt nur im
Shop.

**5. Abholstationen, Etappe 5 – zusammen mit Punkt 4.** Bewusst nicht danach: „Wann?"
und „Wo?" sind dieselbe Operation am Schritt-Index des Wizards. Der ist hart
verdrahtet (`match ($this->step)`, `min(4, …)`), und die sieben Plausible-Ziele
hängen an genau diesen Nummern. Zweimal umnummeriert heißt zweimal einen
unterbrochenen Trichter.

**6. Live-Sicht auf laufende Checkouts.** Erst wenn der Schritt-Index endgültig
ist – eine gespeicherte `step`-Spalte bekäme sonst rückwirkend eine andere
Bedeutung. Entwurf steht: Tabelle `reservation_checkout_sessions`, Ping huckepack
auf den Livewire-Requests, Aufräum-Job ab ~30 Min, rollenbeschränktes Dashboard.
Ohne Personenbezug, und als Kennung eine eigene Zufalls-UUID statt der
Laravel-Session-ID (die ist bei `SESSION_DRIVER=database` ein Login-Token).

**7. Abholstationen, Etappen 6 und 7.** Drop-off-Reste entfernen; Sortiment je
Station zuletzt, weil es die Reihenfolge im Bestellweg kippt.

**Sagt der Kunde „keine mehreren Pausen"**, schrumpft Punkt 4 auf null: Punkt 6
wird sofort machbar, Punkt 5 einfacher, und die pausenweise Freigabe einer Station
bleibt ein Datenmodell ohne sichtbare Wirkung – was in Ordnung ist, sie kostet nichts.

**Nebenbei, jederzeit:** doppelte `CheckoutSetting::forTeam()`-Abfrage in
`checkoutFields` aufräumen; toten `BookingConfirmationMailer` entfernen; klären, ob
eingeladene Gäste weiter als Umsatz zählen (`PLAN-abholstationen.md`, Abschnitt R).

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
      **Offen:** echter Mollie-(Test-)Key + End-to-End-Test auf öffentlichem Host;
      SDK via `composer update` ziehen (`mollie/mollie-api-php`).
- [x] **E-Mail-Bestätigungen** an Gäste — `OrderConfirmationMailer` versendet über den
      CRM-Comms-Dienst (`PostmarkEmailService` + team-scoped `CommsChannel`, wie das
      Events-Modul) mit eigenem HTML-Template; ausgelöst beim Mollie-„bezahlt"-Übergang
      (`MolliePaymentService`). Enthält Bestellnummer, Positionen je Pause und die
      Beleg-Links. Inert ohne aktiven Postmark-Email-Channel.
      *(Der ursprünglich hier genannte `BookingConfirmationMailer` – eine Bestätigung je
      Buchung statt je Bestellung – ist am 01.09.2026 entfernt worden: Seit der
      Order-Klammer hatte er keinen Aufrufer mehr.)*
- [ ] **Bestellschluss-Enforcement** härten (Altsystem: Uhrzeit am Veranstaltungstag, 20:00).
- [ ] **Concurrency-Härtung** Platzvergabe (zwei Gäste buchen gleichzeitig die letzten Plätze)
      + Sequential-Release bei Stornos.
- [x] **Termin duplizieren** im Admin (Saisonpflege: dutzende ähnliche Konzerte).
- [x] **Tische pro Termin sperren** (Altsystem: `disabled_table_ids`).
- [~] Import der echten **37 Artikel**: CSV-Beispielvorlage im Import-Dialog
      herunterladbar (`resources/samples/artikel-import-vorlage.csv`), damit der
      Kunde die Liste vorbereiten kann. **Offen:** echte Liste + Freigabe-Durchlauf.

## Produktentscheidungen – beim nächsten Kundentermin klären

- [ ] **Mehrere Pausen pro Bestellung?** Altsystem erlaubt das („mehrere Pausen auf
      einmal buchen“, Warenkorb je Pause) – wir aktuell eine Pause pro Buchung.
      **Am 31.08.2026 an den Kunden gestellt**, zusammen mit der Frage, ob es
      überhaupt Termine mit mehreren Pausen geben wird. Die Antwort entscheidet
      die Reihenfolge oben (Punkte 4–6).
- [ ] **Flow-Reihenfolge**: Altsystem Datum/Vorstellung → Personen → Pause → Sitzplatz
      → Produkte; unser Meeting-Flow Gastdaten → Produkte → Sitzplatz. Gäste kennen
      das Altsystem → abnehmen lassen.
- [ ] **Platz- vs. Tischwahl**: Altsystem markiert n einzelne Plätze; wir wählen einen
      Tisch mit Restplatz-Prüfung. Reicht Tischwahl?
- [x] Wortlaut **Altersnachweis/Datenschutz** im Checkout pflegbar (Einstellungen →
      Checkout-Texte, `reservation_checkout_settings`, mit Defaults). **Offen:** finaler
      Wortlaut vom Kunden + Kaiserwagen-Klärung mit Herrn von Bauer (out of scope bestätigt).

## M3 – Komfort, Sortiment, Migration

- [ ] **Bundles/Upselling/Cross-Selling** inkl. A/B-Tests (MwSt-Mischsatz-Thema:
      sortenrein oder Bundle mit höherem Satz).
- [ ] **Datums-/Vorstellungssuche** in der Gast-Terminübersicht (viele Termine pro
      Saison, teils mehrere pro Tag; „Keine Vorstellung für …“-Zustand).
- [~] **Migration der Saisondaten aus Guestofy**: Räume inkl. Tischpositionen/
      -kapazitäten werden über die offene AJAX-API übernommen (Venues & Tischpläne →
      „Aus Alt-System“, `GuestofyImporter`). **Offen:** Events/Pausen-Import (saisonal;
      Pausen haben im Altsystem keine Uhrzeiten) – bewusst manuell gelassen.
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
