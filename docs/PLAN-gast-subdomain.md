# Architektur-Plan: Gast-Frontend als eigenes Projekt (culinaria.pauseplus.de)

**Entscheidung (16.07.2026):** Die Gast-Ansicht wird ein **eigenständiges Projekt**:
eigenes Git-Repo, eigenes Deployment via **Laravel Forge**, erreichbar unter
`culinaria.pauseplus.de`. Es greift **nur über eine API** auf das Backoffice
`office.culinaria-wuppertal.de` zu – **kein direkter DB-/Backoffice-Zugriff**.
Skalierbar: weiterer Kunde = weitere Subdomain → anderes Office.

> Ersetzt den früheren „Subdomain in derselben App"-Ansatz. Grund: stärkere Isolation
> (nichts manipulierbar) + saubere Mehrkunden-Skalierung.

---

## Zielbild (Topologie)

```
  Gast (Handy/Browser)
        │  HTTPS
        ▼
  culinaria.pauseplus.de           ← eigenes Repo, eigene Forge-Site
  (Gast-Frontend, KEIN DB-Zugriff)
        │  HTTPS + Bearer-Token (team-gescopt)
        ▼
  office.culinaria-wuppertal.de    ← bestehende Office-App
  /api/guest/*  (Gast-API)         ← Daten, Logik, DB, Mollie
```

- **Office** bleibt die einzige Quelle der Wahrheit (Preise, Verfügbarkeit, Buchung, Team-Scope).
- **Gast-Frontend** rendert nur; alle Daten/Schreibvorgänge laufen über die Office-Gast-API.
- **Manipulationsschutz per Design:** Die Gast-App exponiert weder DB noch Backoffice-Code.
  Alles Sicherheitskritische validiert/rechnet das Office.

---

## A. Office: Gast-API (im Modul `platforms-reservations`, auf dem Office-Host)

Neue **öffentlich erreichbare, aber token-gesicherte** API-Gruppe (`/api/guest/*`),
registriert über `ModuleRouter::apiGroup(...)`. Auth per **Bearer-Token** (pro Gast-App/Team),
Rate-Limit, CORS nur für die Gast-Origin.

**Read-Endpoints**
- `GET /api/guest/events` – kommende, veröffentlichte Termine (team-gescopt via Token)
- `GET /api/guest/events/{uuid}` – Termin + Slots + Räume
- `GET /api/guest/events/{uuid}/products` – freigegebene Verkaufsliste: Artikel, Preise,
  Allergene/Zusatzstoffe, 18+, Portionsgröße, Bild-URLs
- `GET /api/guest/events/{uuid}/floor-plan?room=…` – Tische (normalisierte pct-Koordinaten),
  Grundriss-URL, Rotation, Seitenverhältnis, Verfügbarkeit je Slot

**Write-Endpoints**
- `POST /api/guest/bookings` – Buchung anlegen; **Server validiert + friert Preise ein**;
  Rückgabe: Buchungs-UUID (+ ggf. Mollie-Redirect-URL)
- `GET /api/guest/bookings/{uuid}` – Status (für Payment-Return-Seite)
- **Mollie-Webhook bleibt am Office** (Gast-App nicht beteiligt)

**Auth/Scope – vorhandene Core-Infrastruktur wiederverwenden (KEIN Eigenbau nötig)**
platforms-core bringt bereits ein Bearer-Token-System mit (nur nutzen, Core nicht ändern):
- **Laravel Passport** ist aktiv: dynamischer `api`-Guard (`driver=passport`), `Token`-Model,
  `HasApiTokens`-Trait auf `User` (`createToken($name, $scopes, $expiresAt)`).
- Middleware-Alias **`api.auth`** (`Platform\Core\Http\Middleware\ApiAuthenticate`) prüft den
  Bearer-JWT und setzt den User. `ModuleRouter::apiGroup(..., requireAuth: true)` hängt genau
  `['api','api.auth']` an → **unsere Gast-API ist damit token-gesichert, ohne Eigenbau**.
- **Token-Provisionierung** fix und fertig per Artisan:
  `php artisan api:token:create-endpoint --name="Culinaria Gast-Frontend" --expires=1_year --show`
  (`CreateEndpointApiTokenCommand` legt bei Bedarf einen Service-User an und gibt den Bearer aus).
  Der Token kommt in die `.env` des Gast-Frontends und wird als `Authorization: Bearer …` gesendet.
- **Team-Scope:** Das Office ist eine Ein-Kunden-Instanz → die Gast-API leitet das Team aus der
  **Office-Config** ab (`RESERVATION_GUEST_TEAM_ID`), NICHT aus dem Token. Der Token authentifiziert
  nur „das ist das berechtigte Gast-Frontend"; jeder Endpoint filtert hart auf das Config-Team.
  Skaliert sauber: jedes weitere Office kennt sein eigenes Team. (Optional zusätzlich per
  Passport-Scope `tokenCan(...)` absichern.)
- Widerruf/Ablauf: über Passport (`revoked`, `expires_at`) – Command unterstützt `--expires`.

**Serialisierung:** schlanke API-Resources (nur was der Gast braucht; keine internen Felder,
keine anderen Teams, keine Roh-IDs, die Rückschlüsse erlauben – UUIDs bevorzugen).

---

## B. Gast-Frontend (neues Repo)

- Schlanke Laravel-App mit **nur** den Gast-Seiten: Terminübersicht, Checkout-Wizard
  (Gastdaten → Produkte → Sitzplatz → Bezahlung), Payment-Return.
- **Kein DB-Zugriff.** Datenquelle = HTTP-Client gegen die Office-Gast-API.
- `.env`: `OFFICE_API_BASE=https://office.culinaria-wuppertal.de`,
  `GUEST_API_TOKEN=…`, Branding (`GUEST_LOGO`, `GUEST_ACCENT`, `GUEST_INTRO`, …).
- Behält die bereits gebauten UX-Verbesserungen: optimistischer Warenkorb (Alpine),
  normalisierter Sitzplan (pct-Koordinaten, Pinch/Zoom, mobil).
- Deployment: eigenes Repo → Forge-Site `culinaria.pauseplus.de`.

**Offene Entscheidung – UI-Wiederverwendung:**
- *Option 1:* Gast-UI in ein eigenes Composer-Package (`…-guest`) auslagern und die
  Datenschicht abstrahieren (Eloquent im Office vs. API-Client im Gast). DRY, mehr Abstraktion.
- *Option 2 (Empfehlung zum Start):* Gast-Blades/Livewire-Komponenten ins Gast-Repo
  übernehmen + API-Client als Datenquelle. Klarere Trennung, weniger Kopplung.

---

## C. Skalierung / Mehrkunden

- Gleiche Gast-Codebase, **pro Kunde eigene Forge-Site + eigene `.env`**
  (Token + Office-URL + Branding) → andere Subdomain, anderes Office.
- Office-seitig: **Gast-Token pro Team** (mehrere möglich).
- Stark individualisierte Kunden könnten später ein eigenes Repo bekommen (Fork).

---

## D. Sicherheit / Manipulationsschutz (Kern)

**Autoritative Buchungs-Validierung in `POST /api/guest/bookings`** – schließt die heute im
In-App-Wizard vorhandenen Lücken:
- **Artikel:** nur IDs aus der **freigegebenen Verkaufsliste des Events** (Schnittmenge);
  fremde/unbekannte IDs → `422`. *(Heute: `MenuItem::whereIn('id', …)` ohne Scope.)*
- **Preise/Steuer:** immer aus der DB, nie aus dem Request (bereits so beim Einfrieren).
- **Tisch:** muss zu einem **Raum/Grundriss des Events** gehören; **Slot** muss zum Event
  gehören. *(Heute: `Table::findOrFail($id)` ohne Event-Bezug.)*
- **Mengen:** int, 1..Limit; `guest_count` 1..Max und ≤ Restplätze (`SeatAvailabilityService`).
- **Betrag für Mollie** serverseitig aus den eingefrorenen Positionen.
- **Idempotenz** (bestehende pending-Buchung wiederverwenden) beibehalten; echtes
  Platz-Locking = M2.

**Perimeter**
- Token-Auth + harte Team-Filter auf jedem Endpoint.
- **Rate-Limiting/Throttling** auf allen Gast-Endpoints (v.a. `POST /bookings`);
  Bot-/Spam-Schutz (Honeypot bzw. Cloudflare Turnstile).
- **CORS:** Office-API akzeptiert nur die Gast-Origin(s).
- Keine Backoffice-Routen/Session auf der Gast-Subdomain – durch die physische App-Trennung
  automatisch erfüllt (kein gemeinsames Session-Cookie, kein `SESSION_DOMAIN`-Leak).
- **Secrets** (API-Token, Mollie-Keys) nur im jeweiligen Backend, nie im Browser.
- Mollie-Status/-Betrag serverseitig, Webhook verifiziert (am Office).

---

## E. Migration vom aktuellen In-App-Gast

Der heutige Gast-Flow (Livewire im Modul) bleibt zunächst als Fallback/Dev bestehen und wird
schrittweise abgelöst:
1. Office-Gast-API (read) bauen + Token-Auth + Team-Scope + API-Resources.
2. **Autoritative Buchungs-Validierung + `POST /bookings`** (Preise einfrieren, Mollie-Init) –
   inkl. Schließen der Manipulations-Lücken. *(Diese Härtung lohnt sich unabhängig von der
   Trennung – ggf. vorziehen.)*
3. Gast-Repo anlegen: Seiten + API-Client + Branding.
4. Forge: Site `culinaria.pauseplus.de`, DNS/TLS, `.env`.
5. E2E-Test (inkl. **Handy**), dann In-App-Gast im Office deaktivieren.
6. Skalierungs-Doku: neuer Kunde = neue Forge-Site + `.env` + Token.

---

## F. Offene Entscheidungen (mit Empfehlung)
- **UI-Wiederverwendung:** Repo-eigene Views + API-Client zum Start (Empfehlung), Package später.
- **Auth:** vorhandenes **Passport-Endpoint-Token** aus platforms-core nutzen
  (`api:token:create-endpoint`) + `api.auth` via `ModuleRouter::apiGroup(requireAuth:true)` –
  **kein eigenes Token-System**. Zusätzlich HTTPS + Rate-Limit (+ optional IP-Allowlist).
- **Team-Scope:** aus Office-Config (`RESERVATION_GUEST_TEAM_ID`), nicht aus dem Token.
- **Payment-Return-URL:** auf der Gast-Subdomain (schöner); Status via API nachladen.
- **Reihenfolge:** Die API-Härtung (Schritt 2) ggf. **vor** der Repo-Trennung umsetzen,
  weil sie die eigentliche Sicherheit bringt und auch dem aktuellen In-App-Gast zugutekommt.
