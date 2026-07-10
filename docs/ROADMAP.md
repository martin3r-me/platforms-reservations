# PausePlus – Roadmap / offene Umsetzungspunkte

Stand: 12.06.2026 · Go-live-Ziel: **01.08.2026**, erste Veranstaltung 29.08.2026 (Bodo Wartke).

Meilenstein 1 (Produktmodul + Klick-Dummy bis Mock-Checkout) ist umgesetzt.
Die folgenden Punkte sind **vereinbart und noch umzusetzen**. Referenz für viele
Punkte ist das Altsystem des Kunden (Guestofy, WordPress/WooCommerce/Stripe):
https://historische-stadthalle-wuppertal-culinaria.guestofy.events/#/

## M2 – Zahlung & Härtung (Kern, vor Go-live zwingend)

- [x] **Mollie-Integration (Fundament)**: Hosted-Redirect-Checkout, Webhook
      (`/api/reservation/payment/webhook`), Statusübergänge `pending → confirmed`
      bzw. `→ cancelled`. Key als verschlüsselte Team-Einstellung hinter
      Resolver-Seam (`MollieCredentialResolver`, später auf platforms-integrations
      umstellbar). Bleibt inert ohne Key (Checkout läuft dann als Demo-Mock).
      **Offen:** echter Mollie-(Test-)Key + End-to-End-Test auf öffentlichem Host;
      SDK via `composer update` ziehen (`mollie/mollie-api-php`).
- [~] **E-Mail-Bestätigungen** an Gäste — vorbereitet: `BookingConfirmationMailer`
      versendet über den CRM-Comms-Dienst (`PostmarkEmailService` + team-scoped
      `CommsChannel`, wie das Events-Modul) mit eigenem HTML-Template; ausgelöst beim
      Mollie-„bezahlt"-Übergang. Inert ohne aktiven Postmark-Email-Channel.
      **Offen:** CRM-Email-Channel je Team einrichten; optionale weitere Trigger
      (Admin-Bestätigung, Mock-Flow) sind Einzeiler über `BookingConfirmationMailer::send()`.
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

- [ ] **Raum-Hintergrundbild** (Grundriss) im Tischplan-Editor und -Viewer.
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
