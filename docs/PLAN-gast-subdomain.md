# Plan: Gast-Seite auf eigene Subdomain lösen (culinaria.pauseplus.de)

**Ziel:** Die öffentliche Gast-Terminübersicht läuft unter einer eigenen, pro Deployment
konfigurierbaren Subdomain (hier `culinaria.pauseplus.de`) – losgelöst vom Backoffice.
Das Backoffice bleibt unverändert auf dem Office-Host (`office.culinaria-wuppertal.de`)
unter dem Pfad-Prefix `/reservation`.

**Betriebsmodell (entschieden 14.07.2026):**
- Pro Kunde eine eigene Instanz des Moduls → eigene Subdomain, Host→Team per Config.
- Kein Multi-Tenant-Wildcard. Kein Slug-Mapping nötig (kann später ergänzt werden).
- `pauseplus.de` (Root) = separate Landingpage (Infos + Kontakt bhg.digital) → **nicht Teil dieses Moduls**.

---

## Ausgangslage (verifiziert im Code)

- `ModuleRouter::group()` (platforms-core, read-only) bindet **alle** Modul-Routen fest an
  `->domain($baseHost)` aus `APP_URL`. Der eingebaute `subdomain`-Modus würde das **ganze**
  Modul (inkl. Backoffice) auf `reservation.<host>` verschieben → nicht brauchbar.
  → **Gast-Routen müssen an `ModuleRouter` vorbei** auf einen eigenen Host registriert werden.
- `teams`-Tabelle hat **keinen** Slug/Subdomain (nur `name`, `user_id`, `personal_team`, …).
- `Event` hat `team_id` + `scopeForTeam($teamId)`, aber `EventOverview::events()` filtert
  aktuell **nicht** nach Team (zeigt alle published/upcoming) → muss gescoped werden.
- Gast-Branding ist bereits config-getrieben: `config('reservation.guest.*')`
  (logo_url, eyebrow, intro, accent).

---

## Schritte

### 1. Config: Gast-Host + Team
`config/reservation.php` → Block `guest` erweitern:
```php
'guest' => [
    'host'     => env('RESERVATION_GUEST_HOST', ''),      // '' = Fallback auf Pfad-Modus
    'team_id'  => env('RESERVATION_GUEST_TEAM_ID'),       // welches Team die Gast-Seite zeigt
    // ... bestehende logo_url / eyebrow / intro / accent
],
```
Deployment (.env Culinaria):
```
RESERVATION_GUEST_HOST=culinaria.pauseplus.de
RESERVATION_GUEST_TEAM_ID=<team-id-culinaria>
```

### 2. Routing: Gast-Routen auf eigenen Host
`src/ReservationServiceProvider.php` – den Gast-Block (aktuell `ModuleRouter::group(..., requireAuth:false)`)
ersetzen durch host-abhängige Registrierung:
```php
$guestHost = config('reservation.guest.host');
if ($guestHost) {
    // Dedizierte Subdomain – ohne Modul-Prefix, an ModuleRouter vorbei
    Route::middleware(['web'])
        ->domain($guestHost)
        ->group(fn () => $this->loadRoutesFrom(__DIR__ . '/../routes/guest.php'));
} else {
    // Fallback (lokal / kein Host gesetzt): bisheriger Pfad-Modus /reservation/termine
    \Platform\Core\Routing\ModuleRouter::group('reservation',
        fn () => $this->loadRoutesFrom(__DIR__ . '/../routes/guest.php'),
        requireAuth: false);
}
```

### 3. Routen: Root der Subdomain = Übersicht
`routes/guest.php`: bei dediziertem Host soll `/` direkt die Übersicht sein.
- `Route::get('/', EventOverview::class)->name('reservation.guest.events.index');`
  zusätzlich zu (oder statt) `/termine`.
- Prüfen, dass alle `route('reservation.guest.*')`-Aufrufe weiter absolute URLs mit dem
  Gast-Host erzeugen (durch `->domain()` automatisch) – Checkout-Links, brand/logo, payment/return.

### 4. Team-Scoping der Gast-Ansichten
- `src/Livewire/Guest/EventOverview.php`: `team_id` aus `config('reservation.guest.team_id')`
  auflösen und `->forTeam($teamId)` in `events()` anwenden. (TODO im Component-Kommentar wird damit gelöst.)
- `src/Livewire/Guest/CheckoutWizard.php`: sicherstellen, dass der geladene Event zum
  konfigurierten Team gehört (sonst 404) – kein Cross-Team-Zugriff über UUID.
- Zentrale Helper-Methode erwägen (z.B. `GuestContext::teamId()`), damit später ein
  Host→Team-Mapping leicht nachrüstbar ist (statt Config-ID).

### 5. Branding pro Deployment
- Bereits config-getrieben → nur ENV je Deployment setzen (logo/accent/eyebrow/intro).
- Für die Zukunft (mehrere Gast-Hosts auf einer Instanz) als host-keyed Map vorsehen – jetzt nicht nötig.

### 6. Backoffice unverändert
- Admin-Routen (`routes/web.php`) bleiben via `ModuleRouter::group('reservation')` auf dem
  Office-Host unter `/reservation`. Nicht anfassen.

---

## Deployment / Infra (nicht Code)
- DNS: `culinaria.pauseplus.de` → auf die Instanz zeigen (A/CNAME).
- TLS-Zertifikat für die Subdomain (bzw. Wildcard `*.pauseplus.de`).
- Webserver-Vhost akzeptiert den zusätzlichen Host.
- **Session/Cookies prüfen:** Läuft die Instanz sowohl unter Office-Host als auch
  `culinaria.pauseplus.de`, muss `SESSION_DOMAIN` so gesetzt sein, dass Livewire (CSRF/State)
  auf der Gast-Subdomain funktioniert. Default (`null` = aktueller Host) ist meist ok –
  vor Go-live testen (Checkout-Wizard klickbar, Livewire-Updates 200).

## Separat (eigener Task, nicht dieses Modul)
- Landingpage unter `pauseplus.de` (Root): Infos + Kontakt zu bhg.digital.

---

## Risiken / offene Punkte
- **Livewire auf Subdomain:** Update-Endpoint + Session-Cookie müssen auf dem Gast-Host greifen
  (siehe Session/Cookies). Wichtigster Testpunkt.
- **route()-URLs:** Nach Umstellung prüfen, dass keine Gast-Links auf den Office-Host zeigen
  (z.B. wenn `APP_URL` = Office-Host). Ggf. absolute Links im Gast-Kontext über den Gast-Host erzwingen.
- **brand/logo-Route:** liegt in guest.php → wird mit auf den Host registriert, ok.
- **Fallback-Modus** (`RESERVATION_GUEST_HOST` leer) muss lokal weiter unter
  `/reservation/termine` funktionieren (für Dev ohne Subdomain).

## Reihenfolge morgen
1. Config (Schritt 1) → 2. Routing-Umbau (2+3) → 3. Team-Scoping (4) → 4. lokal testen (Fallback + simulierter Host) → 5. ENV/DNS für Deployment → 6. Livewire/Session-Smoketest auf der Subdomain.
