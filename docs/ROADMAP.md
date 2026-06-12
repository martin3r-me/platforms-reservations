# PausePlus – Roadmap / offene Umsetzungspunkte

Stand: 12.06.2026 · Go-live-Ziel: **01.08.2026**, erste Veranstaltung 29.08.2026 (Bodo Wartke).

Meilenstein 1 (Produktmodul + Klick-Dummy bis Mock-Checkout) ist umgesetzt.
Die folgenden Punkte sind **vereinbart und noch umzusetzen**. Referenz für viele
Punkte ist das Altsystem des Kunden (Guestofy, WordPress/WooCommerce/Stripe):
https://historische-stadthalle-wuppertal-culinaria.guestofy.events/#/

## M2 – Zahlung & Härtung (Kern, vor Go-live zwingend)

- [ ] **Mollie-Integration**: Create-Payment beim Checkout, Webhook, Statusübergänge
      `pending → confirmed` nach Zahlungseingang (Payment-Schema existiert bereits).
      Mollie-Account: martin3r stellt Link mit besseren Konditionen.
- [ ] **E-Mail-Bestätigungen** an Gäste (die Bestätigungsseite verspricht sie bereits).
- [ ] **Bestellschluss-Enforcement** härten (Altsystem: Uhrzeit am Veranstaltungstag, 20:00).
- [ ] **Concurrency-Härtung** Platzvergabe (zwei Gäste buchen gleichzeitig die letzten Plätze)
      + Sequential-Release bei Stornos.
- [ ] **Termin duplizieren** im Admin (Saisonpflege: dutzende ähnliche Konzerte).
- [ ] **Tische pro Termin sperren** (Altsystem: `disabled_table_ids`).
- [ ] Import der echten **37 Artikel** + Vier-Augen-Freigabe-Durchlauf.

## Produktentscheidungen – beim nächsten Kundentermin klären

- [ ] **Mehrere Pausen pro Bestellung?** Altsystem erlaubt das („mehrere Pausen auf
      einmal buchen“, Warenkorb je Pause) – wir aktuell eine Pause pro Buchung.
- [ ] **Flow-Reihenfolge**: Altsystem Datum/Vorstellung → Personen → Pause → Sitzplatz
      → Produkte; unser Meeting-Flow Gastdaten → Produkte → Sitzplatz. Gäste kennen
      das Altsystem → abnehmen lassen.
- [ ] **Platz- vs. Tischwahl**: Altsystem markiert n einzelne Plätze; wir wählen einen
      Tisch mit Restplatz-Prüfung. Reicht Tischwahl?
- [ ] Wortlaut **Altersnachweis/Datenschutz** im Checkout (Altsystem: gepflegte
      Consent-Texte) + Kaiserwagen-Klärung mit Herrn von Bauer (out of scope bestätigt).

## M3 – Komfort, Sortiment, Migration

- [ ] **Bundles/Upselling/Cross-Selling** inkl. A/B-Tests (MwSt-Mischsatz-Thema:
      sortenrein oder Bundle mit höherem Satz).
- [ ] **Datums-/Vorstellungssuche** in der Gast-Terminübersicht (viele Termine pro
      Saison, teils mehrere pro Tag; „Keine Vorstellung für …“-Zustand).
- [ ] **Migration der Saisondaten aus Guestofy**: Events, Räume inkl. Tischpositionen
      und Pausen sind über die offene AJAX-API exportierbar
      (`admin-ajax.php?action=reservations_get_events/_rooms/_options`) –
      automatisierte Übernahme statt Abtippen.
- [ ] **Konfigurierbare Checkout-Consents** (Datenschutz-/18+-Texte pflegbar statt
      hartkodiert).
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
