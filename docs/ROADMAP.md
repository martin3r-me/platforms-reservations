# PausePlus – Roadmap / offene Umsetzungspunkte

Stand: 12.06.2026, Reihenfolge der nächsten Vorhaben ergänzt am 31.08.2026, Pausenfrage beantwortet am 01.09.2026 · Go-live-Ziel: **01.08.2026**, erste Veranstaltung 29.08.2026 (Bodo Wartke).

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

**Stand der Umsetzung (01.09.2026).** Alle fünf Etappen aus
`PLAN-mehrere-pausen.md` sind gebaut und an zwei Testterminen auf demo geprüft –
einer je Betriebsart. Im Modul ist alles ausgerollt; **der Shop nicht**, dort
hängen vier Commits (Schritt-Namen, Pausen und Warenkorb je Pause,
Schnittmengen-Regel, Tisch je Pause). Beim Ausrollen zu beachten: `$step` wechselt
von int auf string, laufende Bestellwege verlieren ihre Sitzung – also außerhalb
der Bestellzeiten. Und das Plausible-Ziel `Pause Selected` muss dort einmal von
Hand angelegt werden.

**6. Live-Sicht auf laufende Checkouts.** Wartet ab jetzt nur noch auf Punkt 5,
nicht mehr auf eine Kundenantwort: Sobald die Schritte eine Liste mit Namen sind,
hat ein gespeicherter Schritt eine Bedeutung, die ein späterer Umbau nicht
rückwirkend verschiebt. Entwurf steht: Tabelle `reservation_checkout_sessions`,
Ping huckepack auf den Livewire-Requests, Aufräum-Job ab ~30 Min,
rollenbeschränktes Dashboard. Ohne Personenbezug, und als Kennung eine eigene
Zufalls-UUID statt der Laravel-Session-ID (die ist bei `SESSION_DRIVER=database`
ein Login-Token).

**7. Abholstationen, Etappen 5–7** *(zurückgestellt)*. Der Schritt „Wo?" hängt
sich nach Punkt 5 ohne Umnummerierung ein. Danach Drop-off-Reste entfernen;
Sortiment je Station zuletzt, weil es die Reihenfolge im Bestellweg kippt.

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
