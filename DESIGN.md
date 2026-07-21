# PausePlus — Design Brief

## Personality: Notion

Ruhig, warm, typografisch. Der Inhalt trägt — Chrome tritt zurück. Kaum Rahmen,
keine Schatten, viel Weißraum. Farbe nur dort, wo sie Bedeutung hat.
Vorbild: Notion. Referenz im Haus: das Forecast-Modul (gleiche Ruhe).

---

## Architektur: fixe Shell, custom Content

Die Page-Shell bleibt **unverändert** und trägt das Layout über alle Seiten:

| Slot | Element | PausePlus |
|------|---------|-----------|
| `navbar` | `x-ui-page-navbar` (Titel) | bleibt |
| `actionbar` | `x-ui-page-actionbar` (Breadcrumbs + Aktionen) | bleibt |
| *(default)* | `x-ui-page-container` (Content) | **hier lebt das PausePlus-Design** |
| `sidebar` | linke Seiten-Navigation (`x-ui-page-sidebar`) | erlaubt (z. B. Struktur-Baum) |
| `activity` | **rechte** Sidebar (`side="right"`) | **NIE füllen — es gibt keine rechte Sidebar in PausePlus** |

Dazu die globale Modul-Sidebar (`Livewire\Sidebar`) = Haupt-Navigation → bleibt.

**Regel:** Der `activity`-Slot wird in PausePlus grundsätzlich **nicht** gesetzt.
Beide linken Navigationen (globale Modul-Sidebar + optionaler `sidebar`-Slot)
bleiben erhalten.

---

## Der Notion-Layer (`.pp-*`)

Ein scoped Stil-Layer im Content-Bereich. Wrapper: `.pp-dash` (bzw. `.pp-page`)
setzt die Variablen, alles darunter nutzt sie. Bewusst eigenständig, damit die
Notion-Handschrift konsistent ist — aber der Akzent ist der Team-/Brand-Ton.

```
--pp-bg:       #faf9f7   /* warmes Off-White, Content-Grund              */
--pp-surface:  #ffffff   /* Karten/Flächen                               */
--pp-text:     #37352f   /* warmes Near-Black — nie #000                 */
--pp-muted:    #787774   /* Sekundärtext                                 */
--pp-faint:    #9b9a97   /* Meta, Captions                               */
--pp-line:     rgba(55,53,47,.09)   /* Hairline statt Rahmen             */
--pp-hover:    rgba(55,53,47,.045)  /* dezente Hover-Fläche              */
--pp-accent:   #285567   /* EINE Akzentfarbe (Brand-Teal)               */
```

Radius: 8px. **Keine** Schatten. Grau-Palette macht 90 %, Akzent nur für Aktion/
aktiv, Semantik-Farben (grün/rot) nur auf Zahlen.

---

## Muster

- **Karten:** `background:var(--pp-surface)`, `border:1px solid var(--pp-line)`,
  `border-radius:8px`, **kein** `box-shadow`. Oft reicht schon eine Hairline
  oben/unten statt eines ganzen Rahmens.
- **Titel:** groß & fett (`~1.9rem`, `letter-spacing:-.01em`), Near-Black.
- **Labels:** normale Größe, **Sentence-Case**, `var(--pp-muted)`. **Kein**
  `text-[11px] uppercase tracking-wider`-Spam.
- **Zahlen:** `font-variant-numeric:tabular-nums`; Semantik-Farbe (grün +, rot −)
  nur auf dem Wert, nicht auf dem Label.
- **Status/Kategorie:** kleiner farbiger Punkt/Pill, nicht als Kasten-Badge.
- **Hover:** `background:var(--pp-hover)`, kein Border-Farbwechsel, kein harter Rand.
- **Listen:** Zeilen mit `var(--pp-line)`-Hairline getrennt, großzügiges Padding.
- **Balken:** schlank (6px), Track `rgba(55,53,47,.06)`, Fill = Semantik/Akzent.

---

## Regeln

1. **Page-Shell nie umbauen** — nur den Content-Bereich stylen.
2. **`activity`-Slot nie setzen** (keine rechte Sidebar).
3. **Hairlines statt Rahmen, keine Schatten, viel Weißraum.**
4. **Sentence-Case** für Labels; Uppercase nur äußerst sparsam.
5. **Eine Akzentfarbe.** Semantik-Farben nur auf Zahlen/Status.
6. **Blade-Fallen meiden:** Direktiven nie an ein Wort kleben (`belegt @if`,
   nicht `belegt@if`); `:disabled` statt `@disabled` in Komponenten-Tags; CSS
   mit `@media` in `@verbatim` kapseln.

## Offen

- **Dark-Mode:** Der Layer ist aktuell light-first. Sobald geklärt ist, wie die
  Plattform den Theme-Toggle schaltet (Klasse/Attribut), Dark-Varianten der
  `--pp-*` ergänzen.
